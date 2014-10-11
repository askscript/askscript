<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-page-users-special.php
	Version: See define()s at top of qa-include/qa-base.php
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

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once QA_INCLUDE_DIR.'qa-db-selects.php';
	require_once QA_INCLUDE_DIR.'qa-app-users.php';
	require_once QA_INCLUDE_DIR.'qa-app-format.php';

	
//	Check we're not using single-sign on integration
	
	if (QA_FINAL_EXTERNAL_USERS)
		as_fatal_error('User accounts are handled by external code');
		

//	Get list of special users

	$users=as_db_select_with_pending(as_db_users_from_level_selectspec(QA_USER_LEVEL_EXPERT));


//	Check we have permission to view this page (moderator or above)

	if (as_get_logged_in_level() < QA_USER_LEVEL_MODERATOR) {
		$as_content=as_content_prepare();
		$as_content['error']=as_lang_html('users/no_permission');
		return $as_content;
	}


//	Get userids and handles of retrieved users

	$usershtml=as_userids_handles_html($users);


//	Prepare content for theme

	$as_content=as_content_prepare();

	$as_content['title']=as_lang_html('users/special_users');
	
	$as_content['ranking']=array(
		'items' => array(),
		'rows' => ceil(as_opt('page_size_users')/as_opt('columns_users')),
		'type' => 'users'
	);
	
	foreach ($users as $user) {
		$as_content['ranking']['items'][]=array(
			'label' => $usershtml[$user['userid']],
			'score' => as_html(as_user_level_string($user['level'])),
		);
	}

	$as_content['navigation']['sub']=as_users_sub_navigation();

	
	return $as_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/