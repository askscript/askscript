<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-confirm.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for email confirmation page (can also request a new code)


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


//	Check we're not using single-sign on integration, that we're not already confirmed, and that we're not blocked
	
	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User login is handled by external code');


//	Check if we've been asked to send a new link or have a successful email confirmation

	$incode=trim(as_get('c')); // trim to prevent passing in blank values to match uninitiated DB rows
	$inhandle=as_get('u');
	$loginuserid=as_get_logged_in_userid();
	$useremailed=false;
	$userconfirmed=false;
	
	if (isset($loginuserid) && as_clicked('dosendconfirm')) { // button clicked to send a link
		require_once AS_INCLUDE_DIR.'as-app-users-edit.php';
		
		if (!as_check_form_security_code('confirm', as_post_text('code')))
			$pageerror=as_lang_html('misc/form_security_again');
		
		else {
			as_send_new_confirm($loginuserid);
			$useremailed=true;
		}
	
	} elseif (strlen($incode)) { // non-empty code detected from the URL
		require_once AS_INCLUDE_DIR.'as-db-selects.php';
		require_once AS_INCLUDE_DIR.'as-app-users-edit.php';
	
		if (!empty($inhandle)) { // match based on code and handle provided on URL
			$userinfo=as_db_select_with_pending(as_db_user_account_selectspec($inhandle, false));
	
			if (strtolower(trim(@$userinfo['emailcode']))==strtolower($incode)) {
				as_complete_confirm($userinfo['userid'], $userinfo['email'], $userinfo['handle']);
				$userconfirmed=true;
			}
		}
		
		if ((!$userconfirmed) && isset($loginuserid)) { // as a backup, also match code on URL against logged in user
			$userinfo=as_db_select_with_pending(as_db_user_account_selectspec($loginuserid, true));
			$flags=$userinfo['flags'];
			
			if ( ($flags & AS_USER_FLAGS_EMAIL_CONFIRMED) && !($flags & AS_USER_FLAGS_MUST_CONFIRM) )
				$userconfirmed=true; // if they confirmed before, just show message as if it happened now
			
			elseif (strtolower(trim($userinfo['emailcode']))==strtolower($incode)) {
				as_complete_confirm($userinfo['userid'], $userinfo['email'], $userinfo['handle']);
				$userconfirmed=true;
			}
		}
	}


//	Prepare content for theme
	
	$as_content=as_content_prepare();
	
	$as_content['title']=as_lang_html('users/confirm_title');
	$as_content['error']=@$pageerror;

	if ($useremailed)
		$as_content['error']=as_lang_html('users/confirm_emailed'); // not an error, but display it prominently anyway
	
	elseif ($userconfirmed) {
		$as_content['error']=as_lang_html('users/confirm_complete');
		
		if (!isset($loginuserid))
			$as_content['suggest_next']=strtr(
				as_lang_html('users/log_in_to_access'),
				
				array(
					'^1' => '<a href="'.as_path_html('login', array('e' => $inhandle)).'">',
					'^2' => '</a>',
				)
			);

	} elseif (isset($loginuserid)) { // if logged in, allow sending a fresh link
		require_once AS_INCLUDE_DIR.'as-util-string.php';
		
		if (strlen($incode))
			$as_content['error']=as_lang_html('users/confirm_wrong_resend');
			
		$email=as_get_logged_in_email();
		
		$as_content['form']=array(
			'tags' => 'method="post" action="'.as_path_html('confirm').'"',
			
			'style' => 'tall',
			
			'fields' => array(
				'email' => array(
					'label' => as_lang_html('users/email_label'),
					'value' => as_html($email).strtr(as_lang_html('users/change_email_link'), array(
						'^1' => '<a href="'.as_path_html('account').'">',
						'^2' => '</a>',
					)),
					'type' => 'static',
				),
			),
			
			'buttons' => array(
				'send' => array(
					'tags' => 'name="dosendconfirm"',
					'label' => as_lang_html('users/send_confirm_button'),
				),
			),

			'hidden' => array(
				'code' => as_get_form_security_code('confirm'),
			),
		);
		
		if (!as_email_validate($email)) {
			$as_content['error']=as_lang_html('users/email_invalid');
			unset($as_content['form']['buttons']['send']);
		}

	} else
		$as_content['error']=as_insert_login_links(as_lang_html('users/confirm_wrong_log_in'), 'confirm');

		
	return $as_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/