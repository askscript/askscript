<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-users-special.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for admin page showing users with non-standard privileges


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

	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-app-users.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';

	
//	Check we're not using single-sign on integration
	
	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User accounts are handled by external code');
		

//	Get list of special users

	$users=as_db_select_with_pending(as_db_users_from_level_selectspec(AS_USER_LEVEL_EXPERT));


//	Check we have permission to view this page (moderator or above)

	if (as_get_logged_in_level() < AS_USER_LEVEL_MODERATOR) {
		$content=as_content_prepare();
		$content['error']=as_lang_html('users/no_permission');
		return $content;
	}


//	Get userids and handles of retrieved users

	$usershtml=as_userids_handles_html($users);


//	Prepare content for theme

	$content=as_content_prepare();

	$content['title']=as_lang_html('users/special_users');
	
	$content['ranking']=array(
		'items' => array(),
		'rows' => ceil(as_opt('page_size_users')/as_opt('columns_users')),
		'type' => 'users'
	);
	
	foreach ($users as $user) {
		$content['ranking']['items'][]=array(
			'label' => $usershtml[$user['userid']],
			'score' => as_html(as_user_level_string($user['level'])),
		);
	}

	$content['navigation']['sub']=as_users_sub_navigation();

	
	return $content;


/*
	Omit PHP closing tag to help avoid accidental output
*/