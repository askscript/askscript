<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-page-register.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for register page


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

	require_once QA_INCLUDE_DIR.'qa-app-captcha.php';
	require_once QA_INCLUDE_DIR.'qa-db-users.php';


//	Check we're not using single-sign on integration, that we're not logged in, and we're not blocked

	if (QA_FINAL_EXTERNAL_USERS)
		as_fatal_error('User registration is handled by external code');
		
	if (as_is_logged_in())
		as_redirect('');


//	Get information about possible additional fields

	$userfields=as_db_select_with_pending(
		as_db_userfields_selectspec()
	);
	
	foreach ($userfields as $index => $userfield)
		if (!($userfield['flags'] & QA_FIELD_FLAGS_ON_REGISTER))
			unset($userfields[$index]);


//	Check we haven't suspended registration, and this IP isn't blocked
	
	if (as_opt('suspend_register_users')) {
		$as_content=as_content_prepare();
		$as_content['error']=as_lang_html('users/register_suspended');
		return $as_content;
	}
	
	if (as_user_permit_error()) {
		$as_content=as_content_prepare();
		$as_content['error']=as_lang_html('users/no_permission');
		return $as_content;
	}

	
//	Process submitted form

	if (as_clicked('doregister')) {
		require_once QA_INCLUDE_DIR.'qa-app-limits.php';
		
		if (as_user_limits_remaining(QA_LIMIT_REGISTRATIONS)) {
			require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
			
			$inemail=as_post_text('email');
			$inpassword=as_post_text('password');
			$inhandle=as_post_text('handle');
			
			$inprofile=array();
			foreach ($userfields as $userfield)
				$inprofile[$userfield['fieldid']]=as_post_text('field_'.$userfield['fieldid']);		
			
			if (!as_check_form_security_code('register', as_post_text('code')))
				$pageerror=as_lang_html('misc/form_security_again');
				
			else {
				$errors=array_merge(
					as_handle_email_filter($inhandle, $inemail),
					as_password_validate($inpassword)
				);
				
				if (count($inprofile)) {
					$filtermodules=as_load_modules_with('filter', 'filter_profile');
					foreach ($filtermodules as $filtermodule)
						$filtermodule->filter_profile($inprofile, $errors, null, null);
				}
				
				if (as_opt('captcha_on_register'))
					as_captcha_validate_post($errors);
			
				if (empty($errors)) { // register and redirect
					as_limits_increment(null, QA_LIMIT_REGISTRATIONS);
	
					$userid=as_create_new_user($inemail, $inpassword, $inhandle);
					
					foreach ($userfields as $userfield)
						as_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);
					
					as_set_logged_in_user($userid, $inhandle);
		
					$topath=as_get('to');
					
					if (isset($topath))
						as_redirect_raw(as_path_to_root().$topath); // path already provided as URL fragment
					else
						as_redirect('');
				}
			}
			
		} else
			$pageerror=as_lang('users/register_limit');
	}


//	Prepare content for theme

	$as_content=as_content_prepare();

	$as_content['title']=as_lang_html('users/register_title');
	
	$as_content['error']=@$pageerror;

	$custom=as_opt('show_custom_register') ? trim(as_opt('custom_register')) : '';
	
	$as_content['form']=array(
		'tags' => 'method="post" action="'.as_self_html().'"',
		
		'style' => 'tall',
		
		'fields' => array(
			'custom' => array(
				'type' => 'custom',
				'note' => $custom,
			),
			
			'handle' => array(
				'label' => as_lang_html('users/handle_label'),
				'tags' => 'name="handle" id="handle"',
				'value' => as_html(@$inhandle),
				'error' => as_html(@$errors['handle']),
			),
			
			'password' => array(
				'type' => 'password',
				'label' => as_lang_html('users/password_label'),
				'tags' => 'name="password" id="password"',
				'value' => as_html(@$inpassword),
				'error' => as_html(@$errors['password']),
			),

			'email' => array(
				'label' => as_lang_html('users/email_label'),
				'tags' => 'name="email" id="email"',
				'value' => as_html(@$inemail),
				'note' => as_opt('email_privacy'),
				'error' => as_html(@$errors['email']),
			),
		),
		
		'buttons' => array(
			'register' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false);"',
				'label' => as_lang_html('users/register_button'),
			),
		),
		
		'hidden' => array(
			'doregister' => '1',
			'code' => as_get_form_security_code('register'),
		),
	);
	
	if (!strlen($custom))
		unset($as_content['form']['fields']['custom']);
	
	foreach ($userfields as $userfield) {
		$value=@$inprofile[$userfield['fieldid']];	
		
		$label=trim(as_user_userfield_label($userfield), ':');
		if (strlen($label))
			$label.=':';
			
		$as_content['form']['fields'][$userfield['title']]=array(
			'label' => as_html($label),
			'tags' => 'name="field_'.$userfield['fieldid'].'"',
			'value' => as_html($value),
			'error' => as_html(@$errors[$userfield['fieldid']]),
			'rows' => ($userfield['flags'] & QA_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
		);
	}
	
	if (as_opt('captcha_on_register'))
		as_set_up_captcha_field($as_content, $as_content['form']['fields'], @$errors);
	
	$loginmodules=as_load_modules_with('login', 'login_html');
	
	foreach ($loginmodules as $module) {
		ob_start();
		$module->login_html(as_opt('site_url').as_get('to'), 'register');
		$html=ob_get_clean();
		
		if (strlen($html))
			@$as_content['custom'].='<br>'.$html.'<br>';
	}

	$as_content['focusid']=isset($errors['handle']) ? 'handle'
		: (isset($errors['password']) ? 'password'
			: (isset($errors['email']) ? 'email' : 'handle'));

			
	return $as_content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/