<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-page-forgot.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for 'forgot my password' page


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

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once QA_INCLUDE_DIR.'qa-db-users.php';
	require_once QA_INCLUDE_DIR.'qa-app-captcha.php';


//	Check we're not using single-sign on integration and that we're not logged in
	
	if (QA_FINAL_EXTERNAL_USERS)
		as_fatal_error('User login is handled by external code');
		
	if (as_is_logged_in())
		as_redirect('');


//	Start the 'I forgot my password' process, sending email if appropriate
	
	if (as_clicked('doforgot')) {
		require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
		
		$inemailhandle=as_post_text('emailhandle');
		
		$errors=array();
		
		if (!as_check_form_security_code('forgot', as_post_text('code')))
			$errors['page']=as_lang_html('misc/form_security_again');
		
		else {
			if (strpos($inemailhandle, '@')===false) { // handles can't contain @ symbols
				$matchusers=as_db_user_find_by_handle($inemailhandle);
				$passemailhandle=!as_opt('allow_login_email_only');
				
			} else {
				$matchusers=as_db_user_find_by_email($inemailhandle);
				$passemailhandle=true;
			}
				
			if (count($matchusers)!=1) // if we get more than one match (should be impossible) also give an error
				$errors['emailhandle']=as_lang('users/user_not_found');
	
			if (as_opt('captcha_on_reset_password'))
				as_captcha_validate_post($errors);
	
			if (empty($errors)) {
				$inuserid=$matchusers[0];
				as_start_reset_user($inuserid);
				as_redirect('reset', $passemailhandle ? array('e' => $inemailhandle) : null); // redirect to page where code is entered
			}
		}			

	} else
		$inemailhandle=as_get('e');

	
//	Prepare content for theme
	
	$as_content=as_content_prepare();

	$as_content['title']=as_lang_html('users/reset_title');
	$as_content['error']=@$errors['page'];

	$as_content['form']=array(
		'tags' => 'method="post" action="'.as_self_html().'"',
		
		'style' => 'tall',
		
		'fields' => array(
			'email_handle' => array(
				'label' => as_lang_html('users/email_handle_label'),
				'tags' => 'name="emailhandle" id="emailhandle"',
				'value' => as_html(@$inemailhandle),
				'error' => as_html(@$errors['emailhandle']),
				'note' => as_lang_html('users/send_reset_note'),
			),
		),
		
		'buttons' => array(
			'send' => array(
				'label' => as_lang_html('users/send_reset_button'),
			),
		),
		
		'hidden' => array(
			'doforgot' => '1',
			'code' => as_get_form_security_code('forgot'),
		),
	);
	
	if (as_opt('captcha_on_reset_password'))
		as_set_up_captcha_field($as_content, $as_content['form']['fields'], @$errors);
	
	$as_content['focusid']='emailhandle';

	
	return $as_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/