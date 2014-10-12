<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-reset.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for password reset page (comes after forgot page)


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


//	Check we're not using single-sign on integration and that we're not logged in
	
	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User login is handled by external code');
		
	if (as_is_logged_in())
		as_redirect('');
		

//	Process incoming form

	if (as_clicked('doreset')) {
		require_once AS_INCLUDE_DIR.'as-app-users-edit.php';
		require_once AS_INCLUDE_DIR.'as-db-users.php';
	
		$inemailhandle=as_post_text('emailhandle');
		$incode=trim(as_post_text('code')); // trim to prevent passing in blank values to match uninitiated DB rows
		
		$errors=array();

		if (!as_check_form_security_code('reset', as_post_text('formcode')))
			$errors['page']=as_lang_html('misc/form_security_again');
		
		else {
			if (as_opt('allow_login_email_only') || (strpos($inemailhandle, '@')!==false)) // handles can't contain @ symbols
				$matchusers=as_db_user_find_by_email($inemailhandle);
			else
				$matchusers=as_db_user_find_by_handle($inemailhandle);
	
			if (count($matchusers)==1) { // if match more than one (should be impossible), consider it a non-match
				require_once AS_INCLUDE_DIR.'as-db-selects.php';
	
				$inuserid=$matchusers[0];
				$userinfo=as_db_select_with_pending(as_db_user_account_selectspec($inuserid, true));
				
				// strlen() check is vital otherwise we can reset code for most users by entering the empty string
				if (strlen($incode) && (strtolower(trim($userinfo['emailcode'])) == strtolower($incode))) {
					as_complete_reset_user($inuserid);
					as_redirect('login', array('e' => $inemailhandle, 'ps' => '1')); // redirect to login page
		
				} else
					$errors['code']=as_lang('users/reset_code_wrong');
				
			} else
				$errors['emailhandle']=as_lang('users/user_not_found');
		}

	} else {
		$inemailhandle=as_get('e');
		$incode=as_get('c');
	}
	
	
//	Prepare content for theme
	
	$as_content=as_content_prepare();

	$as_content['title']=as_lang_html('users/reset_title');
	$as_content['error']=@$errors['page'];

	if (empty($inemailhandle) || isset($errors['emailhandle']))
		$forgotpath=as_path('forgot');
	else
		$forgotpath=as_path('forgot',  array('e' => $inemailhandle));
	
	$as_content['form']=array(
		'tags' => 'method="post" action="'.as_self_html().'"',
		
		'style' => 'tall',
		
		'ok' => empty($incode) ? as_lang_html('users/reset_code_emailed') : null,
		
		'fields' => array(
			'email_handle' => array(
				'label' => as_opt('allow_login_email_only') ? as_lang_html('users/email_label') : as_lang_html('users/email_handle_label'),
				'tags' => 'name="emailhandle" id="emailhandle"',
				'value' => as_html(@$inemailhandle),
				'error' => as_html(@$errors['emailhandle']),
			),

			'code' => array(
				'label' => as_lang_html('users/reset_code_label'),
				'tags' => 'name="code" id="code"',
				'value' => as_html(@$incode),
				'error' => as_html(@$errors['code']),
				'note' => as_lang_html('users/reset_code_emailed').' - '.
					'<a href="'.as_html($forgotpath).'">'.as_lang_html('users/reset_code_another').'</a>',
			),
		),
		
		'buttons' => array(
			'reset' => array(
				'label' => as_lang_html('users/send_password_button'),
			),
		),
		
		'hidden' => array(
			'doreset' => '1',
			'formcode' => as_get_form_security_code('reset'),
		),
	);
	
	$as_content['focusid']=(isset($errors['emailhandle']) || !strlen(@$inemailhandle)) ? 'emailhandle' : 'code';

	
	return $as_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/