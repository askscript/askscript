<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-account.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for user account page


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

	if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once AS_INCLUDE_DIR.'as-db-users.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';
	require_once AS_INCLUDE_DIR.'as-app-users.php';
	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-util-image.php';
	
	
//	Check we're not using single-sign on integration, that we're logged in
	
	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User accounts are handled by external code');
	
	$userid=as_get_logged_in_userid();
	
	if (!isset($userid))
		as_redirect('login');
		

//	Get current information on user

	list($useraccount, $userprofile, $userpoints, $userfields)=as_db_select_with_pending(
		as_db_user_account_selectspec($userid, true),
		as_db_user_profile_selectspec($userid, true),
		as_db_user_points_selectspec($userid, true),
		as_db_userfields_selectspec()
	);
	
	$changehandle=as_opt('allow_change_usernames') || ((!$userpoints['qposts']) && (!$userpoints['aposts']) && (!$userpoints['cposts']));
	$doconfirms=as_opt('confirm_user_emails') && ($useraccount['level']<AS_USER_LEVEL_EXPERT);
	$isconfirmed=($useraccount['flags'] & AS_USER_FLAGS_EMAIL_CONFIRMED) ? true : false;
	$haspassword=isset($useraccount['passsalt']) && isset($useraccount['passcheck']);
	$isblocked=as_user_permit_error() ? true : false;

	
//	Process profile if saved

	if (as_clicked('dosaveprofile') && !$isblocked) {
		require_once AS_INCLUDE_DIR.'as-app-users-edit.php';
		
		$inhandle=$changehandle ? as_post_text('handle') : $useraccount['handle'];
		$inemail=as_post_text('email');
		$inmessages=as_post_text('messages');
		$inwallposts=as_post_text('wall');
		$inmailings=as_post_text('mailings');
		$inavatar=as_post_text('avatar');

		$inprofile=array();
		foreach ($userfields as $userfield)
			$inprofile[$userfield['fieldid']]=as_post_text('field_'.$userfield['fieldid']);		
		
		if (!as_check_form_security_code('account', as_post_text('code')))
			$errors['page']=as_lang_html('misc/form_security_again');
		
		else {
			$errors=as_handle_email_filter($inhandle, $inemail, $useraccount);
	
			if (!isset($errors['handle']))
				as_db_user_set($userid, 'handle', $inhandle);
	
			if (!isset($errors['email']))
				if ($inemail != $useraccount['email']) {
					as_db_user_set($userid, 'email', $inemail);
					as_db_user_set_flag($userid, AS_USER_FLAGS_EMAIL_CONFIRMED, false);
					$isconfirmed=false;
					
					if ($doconfirms)
						as_send_new_confirm($userid);
				}
				
			if (as_opt('allow_private_messages'))
				as_db_user_set_flag($userid, AS_USER_FLAGS_NO_MESSAGES, !$inmessages);
			
			if (as_opt('allow_user_walls'))
				as_db_user_set_flag($userid, AS_USER_FLAGS_NO_WALL_POSTS, !$inwallposts);
			
			if (as_opt('mailing_enabled'))
				as_db_user_set_flag($userid, AS_USER_FLAGS_NO_MAILINGS, !$inmailings);
			
			as_db_user_set_flag($userid, AS_USER_FLAGS_SHOW_AVATAR, ($inavatar=='uploaded'));
			as_db_user_set_flag($userid, AS_USER_FLAGS_SHOW_GRAVATAR, ($inavatar=='gravatar'));
	
			if (is_array(@$_FILES['file']) && $_FILES['file']['size']) {
				require_once AS_INCLUDE_DIR.'as-app-limits.php';
				
				switch (as_user_permit_error(null, AS_LIMIT_UPLOADS))
				{
					case 'limit':
						$errors['avatar']=as_lang('main/upload_limit');
						break;
					
					default:
						$errors['avatar']=as_lang('users/no_permission');
						break;
						
					case false:
						as_limits_increment($userid, AS_LIMIT_UPLOADS);
						
						$toobig=as_image_file_too_big($_FILES['file']['tmp_name'], as_opt('avatar_store_size'));
						
						if ($toobig)
							$errors['avatar']=as_lang_sub('main/image_too_big_x_pc', (int)($toobig*100));
						elseif (!as_set_user_avatar($userid, file_get_contents($_FILES['file']['tmp_name']), $useraccount['avatarblobid']))
							$errors['avatar']=as_lang_sub('main/image_not_read', implode(', ', as_gd_image_formats()));
						break;
				}
			}
	
			if (count($inprofile)) {
				$filtermodules=as_load_modules_with('filter', 'filter_profile');
				foreach ($filtermodules as $filtermodule)
					$filtermodule->filter_profile($inprofile, $errors, $useraccount, $userprofile);
			}
		
			foreach ($userfields as $userfield)
				if (!isset($errors[$userfield['fieldid']]))
					as_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);
			
			list($useraccount, $userprofile)=as_db_select_with_pending(
				as_db_user_account_selectspec($userid, true),
				as_db_user_profile_selectspec($userid, true)
			);
	
			as_report_event('u_save', $userid, $useraccount['handle'], as_cookie_get());
			
			if (empty($errors))
				as_redirect('account', array('state' => 'profile-saved'));
	
			as_logged_in_user_flush();
		}
	}


//	Process change password if clicked

	if (as_clicked('dochangepassword')) {
		require_once AS_INCLUDE_DIR.'as-app-users-edit.php';
		
		$inoldpassword=as_post_text('oldpassword');
		$innewpassword1=as_post_text('newpassword1');
		$innewpassword2=as_post_text('newpassword2');
		
		if (!as_check_form_security_code('password', as_post_text('code')))
			$errors['page']=as_lang_html('misc/form_security_again');
		
		else {
			$errors=array();
			
			if ($haspassword && (strtolower(as_db_calc_passcheck($inoldpassword, $useraccount['passsalt'])) != strtolower($useraccount['passcheck'])))
				$errors['oldpassword']=as_lang('users/password_wrong');
			
			$useraccount['password']=$inoldpassword;
			$errors=$errors+as_password_validate($innewpassword1, $useraccount); // array union
	
			if ($innewpassword1 != $innewpassword2)
				$errors['newpassword2']=as_lang('users/password_mismatch');
				
			if (empty($errors)) {
				as_db_user_set_password($userid, $innewpassword1);
				as_db_user_set($userid, 'sessioncode', ''); // stop old 'Remember me' style logins from still working
				as_set_logged_in_user($userid, $useraccount['handle'], false, $useraccount['sessionsource']); // reinstate this specific session
	
				as_report_event('u_password', $userid, $useraccount['handle'], as_cookie_get());
			
				as_redirect('account', array('state' => 'password-changed'));
			}
		}
	}


//	Prepare content for theme

	$as_content=as_content_prepare();

	$as_content['title']=as_lang_html('profile/my_account_title');	
	$as_content['error']=@$errors['page'];
	
	$as_content['form_profile']=array(
		'tags' => 'enctype="multipart/form-data" method="post" action="'.as_self_html().'"',
		
		'style' => 'wide',
		
		'fields' => array(
			'duration' => array(
				'type' => 'static',
				'label' => as_lang_html('users/member_for'),
				'value' => as_time_to_string(as_opt('db_time')-$useraccount['created']),
			),
			
			'type' => array(
				'type' => 'static',
				'label' => as_lang_html('users/member_type'),
				'value' => as_html(as_user_level_string($useraccount['level'])),
				'note' => $isblocked ? as_lang_html('users/user_blocked') : null,
			),
			
			'handle' => array(
				'label' => as_lang_html('users/handle_label'),
				'tags' => 'name="handle"',
				'value' => as_html(isset($inhandle) ? $inhandle : $useraccount['handle']),
				'error' => as_html(@$errors['handle']),
				'type' => ($changehandle && !$isblocked) ? 'text' : 'static',
			),
			
			'email' => array(
				'label' => as_lang_html('users/email_label'),
				'tags' => 'name="email"',
				'value' => as_html(isset($inemail) ? $inemail : $useraccount['email']),
				'error' => isset($errors['email']) ? as_html($errors['email']) :
					(($doconfirms && !$isconfirmed) ? as_insert_login_links(as_lang_html('users/email_please_confirm')) : null),
				'type' => $isblocked ? 'static' : 'text',
			),
			
			'messages' => array(
				'label' => as_lang_html('users/private_messages'),
				'tags' => 'name="messages"',
				'type' => 'checkbox',
				'value' => !($useraccount['flags'] & AS_USER_FLAGS_NO_MESSAGES),
				'note' => as_lang_html('users/private_messages_explanation'),
			),
			
			'wall' => array(
				'label' => as_lang_html('users/wall_posts'),
				'tags' => 'name="wall"',
				'type' => 'checkbox',
				'value' => !($useraccount['flags'] & AS_USER_FLAGS_NO_WALL_POSTS),
				'note' => as_lang_html('users/wall_posts_explanation'),
			),
			
			'mailings' => array(
				'label' => as_lang_html('users/mass_mailings'),
				'tags' => 'name="mailings"',
				'type' => 'checkbox',
				'value' => !($useraccount['flags'] & AS_USER_FLAGS_NO_MAILINGS),
				'note' => as_lang_html('users/mass_mailings_explanation'),
			),
			
			'avatar' => null, // for positioning
		),
		
		'buttons' => array(
			'save' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false);"',
				'label' => as_lang_html('users/save_profile'),
			),
		),
		
		'hidden' => array(
			'dosaveprofile' => '1',
			'code' => as_get_form_security_code('account'),
		),
	);
	
	if (as_get_state()=='profile-saved')
		$as_content['form_profile']['ok']=as_lang_html('users/profile_saved');
	
	if (!as_opt('allow_private_messages'))
		unset($as_content['form_profile']['fields']['messages']);
		
	if (!as_opt('allow_user_walls'))
		unset($as_content['form_profile']['fields']['wall']);
		
	if (!as_opt('mailing_enabled'))
		unset($as_content['form_profile']['fields']['mailings']);
		
	if ($isblocked) {
		unset($as_content['form_profile']['buttons']['save']);
		$as_content['error']=as_lang_html('users/no_permission');
	}

//	Avatar upload stuff

	if (as_opt('avatar_allow_gravatar') || as_opt('avatar_allow_upload')) {
		$avataroptions=array();
		
		if (as_opt('avatar_default_show') && strlen(as_opt('avatar_default_blobid'))) {
			$avataroptions['']='<span style="margin:2px 0; display:inline-block;">'.
				as_get_avatar_blob_html(as_opt('avatar_default_blobid'), as_opt('avatar_default_width'), as_opt('avatar_default_height'), 32).
				'</span> '.as_lang_html('users/avatar_default');
		} else
			$avataroptions['']=as_lang_html('users/avatar_none');

		$avatarvalue=$avataroptions[''];
	
		if (as_opt('avatar_allow_gravatar')) {
			$avataroptions['gravatar']='<span style="margin:2px 0; display:inline-block;">'.
				as_get_gravatar_html($useraccount['email'], 32).' '.strtr(as_lang_html('users/avatar_gravatar'), array(
					'^1' => '<a href="http://www.gravatar.com/" target="_blank">',
					'^2' => '</a>',
				)).'</span>';

			if ($useraccount['flags'] & AS_USER_FLAGS_SHOW_GRAVATAR)
				$avatarvalue=$avataroptions['gravatar'];
		}

		if (as_has_gd_image() && as_opt('avatar_allow_upload')) {
			$avataroptions['uploaded']='<input name="file" type="file">';

			if (isset($useraccount['avatarblobid']))
				$avataroptions['uploaded']='<span style="margin:2px 0; display:inline-block;">'.
					as_get_avatar_blob_html($useraccount['avatarblobid'], $useraccount['avatarwidth'], $useraccount['avatarheight'], 32).
					'</span>'.$avataroptions['uploaded'];

			if ($useraccount['flags'] & AS_USER_FLAGS_SHOW_AVATAR)
				$avatarvalue=$avataroptions['uploaded'];
		}
		
		$as_content['form_profile']['fields']['avatar']=array(
			'type' => 'select-radio',
			'label' => as_lang_html('users/avatar_label'),
			'tags' => 'name="avatar"',
			'options' => $avataroptions,
			'value' => $avatarvalue,
			'error' => as_html(@$errors['avatar']),
		);
		
	} else
		unset($as_content['form_profile']['fields']['avatar']);


//	Other profile fields

	foreach ($userfields as $userfield) {
		$value=@$inprofile[$userfield['fieldid']];
		if (!isset($value))
			$value=@$userprofile[$userfield['title']];
			
		$label=trim(as_user_userfield_label($userfield), ':');
		if (strlen($label))
			$label.=':';
			
		$as_content['form_profile']['fields'][$userfield['title']]=array(
			'label' => as_html($label),
			'tags' => 'name="field_'.$userfield['fieldid'].'"',
			'value' => as_html($value),
			'error' => as_html(@$errors[$userfield['fieldid']]),
			'rows' => ($userfield['flags'] & AS_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
			'type' => $isblocked ? 'static' : 'text',
		);
	}
	
	
//	Raw information for plugin layers to access

	$as_content['raw']['account']=$useraccount;
	$as_content['raw']['profile']=$userprofile;
	$as_content['raw']['points']=$userpoints;
	

//	Change password form

	$as_content['form_password']=array(
		'tags' => 'method="post" action="'.as_self_html().'"',
		
		'style' => 'wide',
		
		'title' => as_lang_html('users/change_password'),
		
		'fields' => array(
			'old' => array(
				'label' => as_lang_html('users/old_password'),
				'tags' => 'name="oldpassword"',
				'value' => as_html(@$inoldpassword),
				'type' => 'password',
				'error' => as_html(@$errors['oldpassword']),
			),
		
			'new_1' => array(
				'label' => as_lang_html('users/new_password_1'),
				'tags' => 'name="newpassword1"',
				'type' => 'password',
				'error' => as_html(@$errors['password']),
			),

			'new_2' => array(
				'label' => as_lang_html('users/new_password_2'),
				'tags' => 'name="newpassword2"',
				'type' => 'password',
				'error' => as_html(@$errors['newpassword2']),
			),
		),
		
		'buttons' => array(
			'change' => array(
				'label' => as_lang_html('users/change_password'),
			),
		),
		
		'hidden' => array(
			'dochangepassword' => '1',
			'code' => as_get_form_security_code('password'),
		),
	);
	
	if (!$haspassword) {
		$as_content['form_password']['fields']['old']['type']='static';
		$as_content['form_password']['fields']['old']['value']=as_lang_html('users/password_none');
	}
	
	if (as_get_state()=='password-changed')
		$as_content['form_profile']['ok']=as_lang_html('users/password_changed');
		

	$as_content['navigation']['sub']=as_user_sub_navigation($useraccount['handle'], 'account', true);
		
		
	return $as_content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/