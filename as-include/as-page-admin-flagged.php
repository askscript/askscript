<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-admin-flagged.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for admin page showing posts with the most flags


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

	require_once AS_INCLUDE_DIR.'as-app-admin.php';
	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';

	
//	Find most flagged questions, answers, comments

	$userid=as_get_logged_in_userid();
	
	$questions=as_db_select_with_pending(
		as_db_flagged_post_qs_selectspec($userid, 0, true)
	);
	
	
//	Check admin privileges (do late to allow one DB query)

	if (as_user_maximum_permit_error('permit_hide_show')) {
		$content=as_content_prepare();
		$content['error']=as_lang_html('users/no_permission');
		return $content;
	}
		
		
//	Check to see if any were cleared or hidden here

	$pageerror=as_admin_check_clicks();
	

//	Remove questions the user has no permission to hide/show

	if (as_user_permit_error('permit_hide_show')) // if user not allowed to show/hide all posts
		foreach ($questions as $index => $question)
			if (as_user_post_permit_error('permit_hide_show', $question))
				unset($questions[$index]);
	

//	Get information for users

	$usershtml=as_userids_handles_html(as_any_get_userids_handles($questions));


//	Prepare content for theme
	
	$content=as_content_prepare();

	$content['title']=as_lang_html('admin/most_flagged_title');
	$content['error']=isset($pageerror) ? $pageerror : as_admin_page_error();
	
	$content['q_list']=array(
		'form' => array(
			'tags' => 'method="post" action="'.as_self_html().'"',

			'hidden' => array(
				'code' => as_get_form_security_code('admin/click'),
			),
		),
		
		'qs' => array(),
	);
	
	
	if (count($questions)) {
		foreach ($questions as $question) {
			$postid=as_html(isset($question['opostid']) ? $question['opostid'] : $question['postid']);
			$elementid='p'.$postid;

			$htmloptions=as_post_html_options($question);
			$htmloptions['voteview']=false;
			$htmloptions['tagsview']=($question['obasetype']=='Q');
			$htmloptions['answersview']=false;
			$htmloptions['viewsview']=false;
			$htmloptions['contentview']=true;
			$htmloptions['flagsview']=true;
			$htmloptions['elementid']=$elementid;

			$htmlfields=as_any_to_q_html_fields($question, $userid, as_cookie_get(), $usershtml, null, $htmloptions);
			
			if (isset($htmlfields['what_url'])) // link directly to relevant content
				$htmlfields['url']=$htmlfields['what_url'];
			
			$htmlfields['form']=array(
				'style' => 'light',

				'buttons' => array(
					'clearflags' => array(
						'tags' => 'name="admin_'.$postid.'_clearflags" onclick="return as_admin_click(this);"',
						'label' => as_lang_html('question/clear_flags_button'),
					),
	
					'hide' => array(
						'tags' => 'name="admin_'.$postid.'_hide" onclick="return as_admin_click(this);"',
						'label' => as_lang_html('question/hide_button'),
					),
				),
			);

			$content['q_list']['qs'][]=$htmlfields;
		}

	} else
		$content['title']=as_lang_html('admin/no_flagged_found');


	$content['navigation']['sub']=as_admin_sub_navigation();
	$content['script_rel'][]='as-content/as-admin.js?'.AS_VERSION;

	
	return $content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/