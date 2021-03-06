<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-unsubscribe.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for unsubscribe page (unsubscribe link is sent in mass mailings)


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


//	Check we're not using single-sign on integration
	
	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User login is handled by external code');
		

//	Check the code and unsubscribe the user if appropriate

	$unsubscribed=false;
	$loginuserid=as_get_logged_in_userid();
	
	$incode=trim(as_get('c')); // trim to prevent passing in blank values to match uninitiated DB rows
	$inhandle=as_get('u');
	
	if (!empty($inhandle)) { // match based on code and handle provided on URL
		$userinfo=as_db_select_with_pending(as_db_user_account_selectspec($inhandle, false));

		if (strtolower(trim(@$userinfo['emailcode']))==strtolower($incode)) {
			as_db_user_set_flag($userinfo['userid'], AS_USER_FLAGS_NO_MAILINGS, true);
			$unsubscribed=true;
		}
	}
	
	if ( (!$unsubscribed) && isset($loginuserid)) { // as a backup, also unsubscribe logged in user
		as_db_user_set_flag($loginuserid, AS_USER_FLAGS_NO_MAILINGS, true);
		$unsubscribed=true;
	}


//	Prepare content for theme
	
	$content=as_content_prepare();
	
	$content['title']=as_lang_html('users/unsubscribe_title');

	if ($unsubscribed)
		$content['error']=strtr(as_lang_html('users/unsubscribe_complete'), array(
			'^0' => as_html(as_opt('site_title')),
			'^1' => '<a href="'.as_path_html('account').'">',
			'^2' => '</a>',
		));
	else
		$content['error']=as_insert_login_links(as_lang_html('users/unsubscribe_wrong_log_in'), 'unsubscribe');

		
	return $content;


/*
	Omit PHP closing tag to help avoid accidental output
*/