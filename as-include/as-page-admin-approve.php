<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-admin-approve.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for admin page showing new users waiting for approval


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

	require_once AS_INCLUDE_DIR.'as-app-admin.php';
	require_once AS_INCLUDE_DIR.'as-db-admin.php';

	
//	Check we're not using single-sign on integration
	
	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User accounts are handled by external code');


//	Find most flagged questions, answers, comments

	$userid=as_get_logged_in_userid();
	
	$users=as_db_get_unapproved_users(as_opt('page_size_users'));
	$userfields=as_db_select_with_pending(as_db_userfields_selectspec());


//	Check admin privileges (do late to allow one DB query)

	if (as_get_logged_in_level()<AS_USER_LEVEL_MODERATOR) {
		$content=as_content_prepare();
		$content['error']=as_lang_html('users/no_permission');
		return $content;
	}
		
		
//	Check to see if any were approved or blocked here

	$pageerror=as_admin_check_clicks();
	

//	Prepare content for theme
	
	$content=as_content_prepare();

	$content['title']=as_lang_html('admin/approve_users_title');
	$content['error']=isset($pageerror) ? $pageerror : as_admin_page_error();
	
	$content['message_list']=array(
		'form' => array(
			'tags' => 'method="post" action="'.as_self_html().'"',

			'hidden' => array(
				'code' => as_get_form_security_code('admin/click'),
			),
		),
		
		'messages' => array(),
	);
	
	
	if (count($users)) {
		foreach ($users as $user) {
			$message=array();
			
			$message['tags']='id="p'.as_html($user['userid']).'"'; // use p prefix for as_admin_click() in as-admin.js
						
			$message['content']=as_lang_html('users/registered_label').' '.
				strtr(as_lang_html('users/x_ago_from_y'), array(
					'^1' => as_time_to_string(as_opt('db_time')-$user['created']),
					'^2' => as_ip_anchor_html($user['createip']),
				)).'<br/>';
				
			$htmlemail=as_html($user['email']);
			
			$message['content'].=as_lang_html('users/email_label').' <a href="mailto:'.$htmlemail.'">'.$htmlemail.'</a>';
			
			if (as_opt('confirm_user_emails'))
				$message['content'].='<small> - '.as_lang_html(($user['flags'] & AS_USER_FLAGS_EMAIL_CONFIRMED) ? 'users/email_confirmed' : 'users/email_not_confirmed').'</small>';
			
			foreach ($userfields as $userfield)
				if (strlen(@$user['profile'][$userfield['title']]))
					$message['content'].='<br/>'.as_html($userfield['content'].': '.$user['profile'][$userfield['title']]);
				
			$message['meta_order']=as_lang_html('main/meta_order');
			$message['who']['data']=as_get_one_user_html($user['handle']);
			
			$message['form']=array(
				'style' => 'light',

				'buttons' => array(
					'approve' => array(
						'tags' => 'name="admin_'.$user['userid'].'_userapprove" onclick="return as_admin_click(this);"',
						'label' => as_lang_html('question/approve_button'),
					),

					'block' => array(
						'tags' => 'name="admin_'.$user['userid'].'_userblock" onclick="return as_admin_click(this);"',
						'label' => as_lang_html('admin/block_button'),
					),
				),
			);
			
			$content['message_list']['messages'][]=$message;
		}
		
	} else
		$content['title']=as_lang_html('admin/no_unapproved_found');


	$content['navigation']['sub']=as_admin_sub_navigation();
	$content['script_rel'][]='as-content/as-admin.js?'.AS_VERSION;

	
	return $content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/