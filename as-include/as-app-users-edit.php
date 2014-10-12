<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-app-users-edit.php
	Version: See define()s at top of as-include/as-base.php
	Description: User management (application level) for creating/modifying users


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

	@define('AS_MIN_PASSWORD_LEN', 4);
	@define('AS_NEW_PASSWORD_LEN', 8); // when resetting password


	function as_handle_email_filter(&$handle, &$email, $olduser=null)
/*
	Return $errors fields for any invalid aspect of user-entered $handle (username) and $email. Works by calling through
	to all filter modules and also rejects existing values in database unless they belongs to $olduser (if set).
*/
	{
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		
		$errors=array();
		
		$filtermodules=as_load_modules_with('filter', 'filter_handle');
		
		foreach ($filtermodules as $filtermodule) {
			$error=$filtermodule->filter_handle($handle, $olduser);
			if (isset($error)) {
				$errors['handle']=$error;
				break;
			}
		}

		if (!isset($errors['handle'])) { // first test through filters, then check for duplicates here
			$handleusers=as_db_user_find_by_handle($handle);
			if (count($handleusers) && ( (!isset($olduser['userid'])) || (array_search($olduser['userid'], $handleusers)===false) ) )
				$errors['handle']=as_lang('users/handle_exists');
		}
		
		$filtermodules=as_load_modules_with('filter', 'filter_email');
		
		$error=null;
		foreach ($filtermodules as $filtermodule) {
			$error=$filtermodule->filter_email($email, $olduser);
			if (isset($error)) {
				$errors['email']=$error;
				break;
			}
		}

		if (!isset($errors['email'])) {
			$emailusers=as_db_user_find_by_email($email);
			if (count($emailusers) && ( (!isset($olduser['userid'])) || (array_search($olduser['userid'], $emailusers)===false) ) )
				$errors['email']=as_lang('users/email_exists');
		}
		
		return $errors;
	}
	
	
	function as_handle_make_valid($handle)
/*
	Make $handle valid and unique in the database - if $allowuserid is set, allow it to match that user only
*/
	{
		require_once AS_INCLUDE_DIR.'as-util-string.php';
		require_once AS_INCLUDE_DIR.'as-db-maxima.php';
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		
		if (!strlen($handle))
			$handle=as_lang('users/registered_user');

		$handle=preg_replace('/[\\@\\+\\/]/', ' ', $handle);

		for ($attempt=0; $attempt<=99; $attempt++) {
			$suffix=$attempt ? (' '.$attempt) : '';
			$tryhandle=as_substr($handle, 0, AS_DB_MAX_HANDLE_LENGTH-strlen($suffix)).$suffix;

			$filtermodules=as_load_modules_with('filter', 'filter_handle');
			foreach ($filtermodules as $filtermodule)
				$filtermodule->filter_handle($tryhandle, null); // filter first without worrying about errors, since our goal is to get a valid one
			
			$haderror=false;
			
			foreach ($filtermodules as $filtermodule) {
				$error=$filtermodule->filter_handle($tryhandle, null); // now check for errors after we've filtered
				if (isset($error))
					$haderror=true;
			}
			
			if (!$haderror) {
				$handleusers=as_db_user_find_by_handle($tryhandle);
				if (!count($handleusers))
					return $tryhandle;
			}
		}
		
		as_fatal_error('Could not create a valid and unique handle from: '.$handle);
	}


	function as_password_validate($password, $olduser=null)
/*
	Return an array with a single element (key 'password') if user-entered $password is valid, otherwise an empty array.
	Works by calling through to all filter modules.
*/
	{
		$error=null;
		$filtermodules=as_load_modules_with('filter', 'validate_password');
		
		foreach ($filtermodules as $filtermodule) {
			$error=$filtermodule->validate_password($password, $olduser);
			if (isset($error))
				break;
		}
		
		if (!isset($error)) {
			$minpasslen=max(AS_MIN_PASSWORD_LEN, 1);
			if (as_strlen($password)<$minpasslen)
				$error=as_lang_sub('users/password_min', $minpasslen);
		}		

		if (isset($error))
			return array('password' => $error);

		return array();
	}

	
	function as_create_new_user($email, $password, $handle, $level=AS_USER_LEVEL_BASIC, $confirmed=false)
/*
	Create a new user (application level) with $email, $password, $handle and $level.
	Set $confirmed to true if the email address has been confirmed elsewhere.
	Handles user points, notification and optional email confirmation.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		require_once AS_INCLUDE_DIR.'as-db-points.php';
		require_once AS_INCLUDE_DIR.'as-app-options.php';
		require_once AS_INCLUDE_DIR.'as-app-emails.php';
		require_once AS_INCLUDE_DIR.'as-app-cookies.php';

		$userid=as_db_user_create($email, $password, $handle, $level, as_remote_ip_address());
		as_db_points_update_ifuser($userid, null);
		as_db_uapprovecount_update();
		
		if ($confirmed)
			as_db_user_set_flag($userid, AS_USER_FLAGS_EMAIL_CONFIRMED, true);
			
		if (as_opt('show_notice_welcome'))
			as_db_user_set_flag($userid, AS_USER_FLAGS_WELCOME_NOTICE, true);
		
		$custom=as_opt('show_custom_welcome') ? trim(as_opt('custom_welcome')) : '';
		
		if (as_opt('confirm_user_emails') && ($level<AS_USER_LEVEL_EXPERT) && !$confirmed) {
			$confirm=strtr(as_lang('emails/welcome_confirm'), array(
				'^url' => as_get_new_confirm_url($userid, $handle)
			));
			
			if (as_opt('confirm_user_required'))
				as_db_user_set_flag($userid, AS_USER_FLAGS_MUST_CONFIRM, true);
				
		} else
			$confirm='';
		
		if (as_opt('moderate_users') && as_opt('approve_user_required') && ($level<AS_USER_LEVEL_EXPERT))
			as_db_user_set_flag($userid, AS_USER_FLAGS_MUST_APPROVE, true);
				
		as_send_notification($userid, $email, $handle, as_lang('emails/welcome_subject'), as_lang('emails/welcome_body'), array(
			'^password' => isset($password) ? as_lang('main/hidden') : as_lang('users/password_to_set'), // v 1.6.3: no longer email out passwords
			'^url' => as_opt('site_url'),
			'^custom' => strlen($custom) ? ($custom."\n\n") : '',
			'^confirm' => $confirm,
		));
		
		as_report_event('u_register', $userid, $handle, as_cookie_get(), array(
			'email' => $email,
			'level' => $level,
		));
		
		return $userid;
	}
	
	
	function as_delete_user($userid)
/*
	Delete $userid and all their votes and flags. Their posts will become anonymous.
	Handles recalculations of votes and flags for posts this user has affected.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-votes.php';
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		require_once AS_INCLUDE_DIR.'as-db-post-update.php';
		require_once AS_INCLUDE_DIR.'as-db-points.php';
		
		$postids=as_db_uservoteflag_user_get($userid); // posts this user has flagged or voted on, whose counts need updating
		
		as_db_user_delete($userid);
		as_db_uapprovecount_update();
		
		foreach ($postids as $postid) { // hoping there aren't many of these - saves a lot of new SQL code...
			as_db_post_recount_votes($postid);
			as_db_post_recount_flags($postid);
		}
		
		$postuserids=as_db_posts_get_userids($postids);
			
		foreach ($postuserids as $postuserid)
			as_db_points_update_ifuser($postuserid, array('avoteds','qvoteds', 'upvoteds', 'downvoteds'));
	}

	
	function as_send_new_confirm($userid)
/*
	Set a new email confirmation code for the user and send it out
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		require_once AS_INCLUDE_DIR.'as-db-selects.php';
		require_once AS_INCLUDE_DIR.'as-app-emails.php';

		$userinfo=as_db_select_with_pending(as_db_user_account_selectspec($userid, true));
		
		if (!as_send_notification($userid, $userinfo['email'], $userinfo['handle'], as_lang('emails/confirm_subject'), as_lang('emails/confirm_body'), array(
			'^url' => as_get_new_confirm_url($userid, $userinfo['handle']),
		)))
			as_fatal_error('Could not send email confirmation');
	}

	
	function as_get_new_confirm_url($userid, $handle)
/*
	Set a new email confirmation code for the user and return the corresponding link
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		
		$emailcode=as_db_user_rand_emailcode();
		as_db_user_set($userid, 'emailcode', $emailcode);
		
		return as_path_absolute('confirm', array('c' => $emailcode, 'u' => $handle));
	}

	
	function as_complete_confirm($userid, $email, $handle)
/*
	Complete the email confirmation process for the user
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		require_once AS_INCLUDE_DIR.'as-app-cookies.php';
		
		as_db_user_set_flag($userid, AS_USER_FLAGS_EMAIL_CONFIRMED, true);
		as_db_user_set_flag($userid, AS_USER_FLAGS_MUST_CONFIRM, false);
		as_db_user_set($userid, 'emailcode', ''); // to prevent re-use of the code

		as_report_event('u_confirmed', $userid, $handle, as_cookie_get(), array(
			'email' => $email,
		));
	}
	
	
	function as_set_user_level($userid, $handle, $level, $oldlevel)
/*
	Set the user level of user $userid with $handle to $level (one of the AS_USER_LEVEL_* constraints in as-app-users.php)
	Pass the previous user level in $oldlevel. Reports the appropriate event, assumes change performed by the logged in user.
*/
	{
		require_once AS_INCLUDE_DIR.'as-db-users.php';

		as_db_user_set($userid, 'level', $level);
		as_db_uapprovecount_update();
		
		if ($level>=AS_USER_LEVEL_APPROVED)
			as_db_user_set_flag($userid, AS_USER_FLAGS_MUST_APPROVE, false);

		as_report_event('u_level', as_get_logged_in_userid(), as_get_logged_in_handle(), as_cookie_get(), array(
			'userid' => $userid,
			'handle' => $handle,
			'level' => $level,
			'oldlevel' => $oldlevel,
		));
	}
	
	
	function as_set_user_blocked($userid, $handle, $blocked)
/*
	Set the status of user $userid with $handle to blocked if $blocked is true, otherwise to unblocked. Reports the appropriate
	event, assumes change performed by the logged in user.
*/
	{
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		
		as_db_user_set_flag($userid, AS_USER_FLAGS_USER_BLOCKED, $blocked);
		as_db_uapprovecount_update();
		
		as_report_event($blocked ? 'u_block' : 'u_unblock', as_get_logged_in_userid(), as_get_logged_in_handle(), as_cookie_get(), array(
			'userid' => $userid,
			'handle' => $handle,
		));
	}

	
	function as_start_reset_user($userid)
/*
	Start the 'I forgot my password' process for $userid, sending reset code
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-users.php';
		require_once AS_INCLUDE_DIR.'as-app-options.php';
		require_once AS_INCLUDE_DIR.'as-app-emails.php';
		require_once AS_INCLUDE_DIR.'as-db-selects.php';

		as_db_user_set($userid, 'emailcode', as_db_user_rand_emailcode());

		$userinfo=as_db_select_with_pending(as_db_user_account_selectspec($userid, true));

		if (!as_send_notification($userid, $userinfo['email'], $userinfo['handle'], as_lang('emails/reset_subject'), as_lang('emails/reset_body'), array(
			'^code' => $userinfo['emailcode'],
			'^url' => as_path_absolute('reset', array('c' => $userinfo['emailcode'], 'e' => $userinfo['email'])),
		)))
			as_fatal_error('Could not send reset password email');
	}

	
	function as_complete_reset_user($userid)
/*
	Successfully finish the 'I forgot my password' process for $userid, sending new password
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-util-string.php';
		require_once AS_INCLUDE_DIR.'as-app-options.php';
		require_once AS_INCLUDE_DIR.'as-app-emails.php';
		require_once AS_INCLUDE_DIR.'as-app-cookies.php';
		require_once AS_INCLUDE_DIR.'as-db-selects.php';
	
		$password=as_random_alphanum(max(AS_MIN_PASSWORD_LEN, AS_NEW_PASSWORD_LEN));
		
		$userinfo=as_db_select_with_pending(as_db_user_account_selectspec($userid, true));
		
		if (!as_send_notification($userid, $userinfo['email'], $userinfo['handle'], as_lang('emails/new_password_subject'), as_lang('emails/new_password_body'), array(
			'^password' => $password,
			'^url' => as_opt('site_url'),
		)))
			as_fatal_error('Could not send new password - password not reset');
		
		as_db_user_set_password($userid, $password); // do this last, to be safe
		as_db_user_set($userid, 'emailcode', ''); // so can't be reused

		as_report_event('u_reset', $userid, $userinfo['handle'], as_cookie_get(), array(
			'email' => $userinfo['email'],
		));
	}

	
	function as_logged_in_user_flush()
/*
	Flush any information about the currently logged in user, so it is retrieved from database again
*/
	{
		global $as_cached_logged_in_user;
		
		$as_cached_logged_in_user=null;
	}
	
	
	function as_set_user_avatar($userid, $imagedata, $oldblobid=null)
/*
	Set the avatar of $userid to the image in $imagedata, and remove $oldblobid from the database if not null
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-util-image.php';
		
		$imagedata=as_image_constrain_data($imagedata, $width, $height, as_opt('avatar_store_size'));
		
		if (isset($imagedata)) {
			require_once AS_INCLUDE_DIR.'as-app-blobs.php';

			$newblobid=as_create_blob($imagedata, 'jpeg', null, $userid, null, as_remote_ip_address());
			
			if (isset($newblobid)) {
				as_db_user_set($userid, 'avatarblobid', $newblobid);
				as_db_user_set($userid, 'avatarwidth', $width);
				as_db_user_set($userid, 'avatarheight', $height);
				as_db_user_set_flag($userid, AS_USER_FLAGS_SHOW_AVATAR, true);
				as_db_user_set_flag($userid, AS_USER_FLAGS_SHOW_GRAVATAR, false);

				if (isset($oldblobid))
					as_delete_blob($oldblobid);

				return true;
			}
		}
		
		return false;
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/