<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-login.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for login page


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


//	Check we're not using Q2A's single-sign on integration and that we're not logged in
	
	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User login is handled by external code');
		
	if (as_is_logged_in())
		as_redirect('');
		

//	Process submitted form after checking we haven't reached rate limit
	
	$passwordsent=as_get('ps');
	$emailexists=as_get('ee');

	$inemailhandle=as_post_text('emailhandle');
	$inpassword=as_post_text('password');
	$inremember=as_post_text('remember');
			
	if (as_clicked('dologin') && (strlen($inemailhandle) || strlen($inpassword)) ) {
		require_once AS_INCLUDE_DIR.'as-app-limits.php';

		if (as_user_limits_remaining(AS_LIMIT_LOGINS)) {
			require_once AS_INCLUDE_DIR.'as-db-users.php';
			require_once AS_INCLUDE_DIR.'as-db-selects.php';
		
			if (!as_check_form_security_code('login', as_post_text('code')))
				$pageerror=as_lang_html('misc/form_security_again');
				
			else {
				as_limits_increment(null, AS_LIMIT_LOGINS);

				$errors=array();
				
				if (as_opt('allow_login_email_only') || (strpos($inemailhandle, '@')!==false)) // handles can't contain @ symbols
					$matchusers=as_db_user_find_by_email($inemailhandle);
				else
					$matchusers=as_db_user_find_by_handle($inemailhandle);
		
				if (count($matchusers)==1) { // if matches more than one (should be impossible), don't log in
					$inuserid=$matchusers[0];
					$userinfo=as_db_select_with_pending(as_db_user_account_selectspec($inuserid, true));
					
					if (strtolower(as_db_calc_passcheck($inpassword, $userinfo['passsalt'])) == strtolower($userinfo['passcheck'])) { // login and redirect
						require_once AS_INCLUDE_DIR.'as-app-users.php';
		
						as_set_logged_in_user($inuserid, $userinfo['handle'], $inremember ? true : false);
						
						$topath=as_get('to');
						
						if (isset($topath))
							as_redirect_raw(as_path_to_root().$topath); // path already provided as URL fragment
						elseif ($passwordsent)
							as_redirect('account');
						else
							as_redirect('');
		
					} else
						$errors['password']=as_lang('users/password_wrong');
		
				} else
					$errors['emailhandle']=as_lang('users/user_not_found');
			}
				
		} else
			$pageerror=as_lang('users/login_limit');
		
	} else
		$inemailhandle=as_get('e');

	
//	Prepare content for theme
	
	$content=as_content_prepare();

	$content['title']=as_lang_html('users/login_title');
	
	$content['error']=@$pageerror;

	if (empty($inemailhandle) || isset($errors['emailhandle']))
		$forgotpath=as_path('forgot');
	else
		$forgotpath=as_path('forgot', array('e' => $inemailhandle));
	
	$forgothtml='<a href="'.as_html($forgotpath).'">'.as_lang_html('users/forgot_link').'</a>';
	
	$content['form']=array(
		'tags' => 'method="post" action="'.as_self_html().'"',
		
		'style' => 'tall',
		
		'ok' => $passwordsent ? as_lang_html('users/password_sent') : ($emailexists ? as_lang_html('users/email_exists') : null),
		
		'fields' => array(
			'email_handle' => array(
				'label' => as_opt('allow_login_email_only') ? as_lang_html('users/email_label') : as_lang_html('users/email_handle_label'),
				'tags' => 'name="emailhandle" id="emailhandle"',
				'value' => as_html(@$inemailhandle),
				'error' => as_html(@$errors['emailhandle']),
			),
			
			'password' => array(
				'type' => 'password',
				'label' => as_lang_html('users/password_label'),
				'tags' => 'name="password" id="password"',
				'value' => as_html(@$inpassword),
				'error' => empty($errors['password']) ? '' : (as_html(@$errors['password']).' - '.$forgothtml),
				'note' => $passwordsent ? as_lang_html('users/password_sent') : $forgothtml,
			),
			
			'remember' => array(
				'type' => 'checkbox',
				'label' => as_lang_html('users/remember_label'),
				'tags' => 'name="remember"',
				'value' => @$inremember ? true : false,
			),
		),
		
		'buttons' => array(
			'login' => array(
				'label' => as_lang_html('users/login_button'),
			),
		),
		
		'hidden' => array(
			'dologin' => '1',
			'code' => as_get_form_security_code('login'),
		),
	);
	
	$loginmodules=as_load_modules_with('login', 'login_html');
	
	foreach ($loginmodules as $module) {
		ob_start();
		$module->login_html(as_opt('site_url').as_get('to'), 'login');
		$html=ob_get_clean();
		
		if (strlen($html))
			@$content['custom'].='<br>'.$html.'<br>';
	}

	$content['focusid']=(isset($inemailhandle) && !isset($errors['emailhandle'])) ? 'password' : 'emailhandle';
	

	return $content;


/*
	Omit PHP closing tag to help avoid accidental output
*/