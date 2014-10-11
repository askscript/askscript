<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-page-user-activity.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for user page showing recent activity


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
	require_once QA_INCLUDE_DIR.'qa-app-format.php';
	

//	$handle, $userhtml are already set by qa-page-user.php - also $userid if using external user integration

	
//	Find the recent activity for this user
	
	$loginuserid=as_get_logged_in_userid();
	$identifier=QA_FINAL_EXTERNAL_USERS ? $userid : $handle;

	list($useraccount, $questions, $answerqs, $commentqs, $editqs)=as_db_select_with_pending(
		QA_FINAL_EXTERNAL_USERS ? null : as_db_user_account_selectspec($handle, false),
		as_db_user_recent_qs_selectspec($loginuserid, $identifier, as_opt_if_loaded('page_size_activity')),
		as_db_user_recent_a_qs_selectspec($loginuserid, $identifier),
		as_db_user_recent_c_qs_selectspec($loginuserid, $identifier),
		as_db_user_recent_edit_qs_selectspec($loginuserid, $identifier)
	);
	
	if ((!QA_FINAL_EXTERNAL_USERS) && !is_array($useraccount)) // check the user exists
		return include QA_INCLUDE_DIR.'qa-page-not-found.php';


//	Get information on user references

	$questions=as_any_sort_and_dedupe(array_merge($questions, $answerqs, $commentqs, $editqs));
	$questions=array_slice($questions, 0, as_opt('page_size_activity'));
	$usershtml=as_userids_handles_html(as_any_get_userids_handles($questions), false);

	
//	Prepare content for theme
	
	$as_content=as_content_prepare(true);
	
	if (count($questions))
		$as_content['title']=as_lang_html_sub('profile/recent_activity_by_x', $userhtml);
	else
		$as_content['title']=as_lang_html_sub('profile/no_posts_by_x', $userhtml);


//	Recent activity by this user

	$as_content['q_list']['form']=array(
		'tags' => 'method="post" action="'.as_self_html().'"',

		'hidden' => array(
			'code' => as_get_form_security_code('vote'),
		),
	);
	
	$as_content['q_list']['qs']=array();
	
	$htmldefaults=as_post_html_defaults('Q');
	$htmldefaults['whoview']=false;
	$htmldefaults['voteview']=false;
	$htmldefaults['avatarsize']=0;
	
	foreach ($questions as $question)
		$as_content['q_list']['qs'][]=as_any_to_q_html_fields($question, $loginuserid, as_cookie_get(),
			$usershtml, null, array('voteview' => false) + as_post_html_options($question, $htmldefaults));


//	Sub menu for navigation in user pages

	$as_content['navigation']['sub']=as_user_sub_navigation($handle, 'activity',
		isset($loginuserid) && ($loginuserid==(QA_FINAL_EXTERNAL_USERS ? $userid : $useraccount['userid'])));


	return $as_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/