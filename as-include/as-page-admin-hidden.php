<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-admin-hidden.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for admin page showing hidden questions, answers and comments


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
	require_once AS_INCLUDE_DIR.'as-db-admin.php';
	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';

	
//	Find recently hidden questions, answers, comments

	$userid=as_get_logged_in_userid();
	
	list($hiddenquestions, $hiddenanswers, $hiddencomments)=as_db_select_with_pending(
		as_db_qs_selectspec($userid, 'created', 0, null, null, 'Q_HIDDEN', true),
		as_db_recent_a_qs_selectspec($userid, 0, null, null, 'A_HIDDEN', true),
		as_db_recent_c_qs_selectspec($userid, 0, null, null, 'C_HIDDEN', true)
	);
	
	
//	Check admin privileges (do late to allow one DB query)
	
	if (as_user_maximum_permit_error('permit_hide_show') && as_user_maximum_permit_error('permit_delete_hidden')) {
		$content=as_content_prepare();
		$content['error']=as_lang_html('users/no_permission');
		return $content;
	}
		
		
//	Check to see if any have been reshown or deleted

	$pageerror=as_admin_check_clicks();


//	Combine sets of questions and remove those this user has no permissions for

	$questions=as_any_sort_by_date(array_merge($hiddenquestions, $hiddenanswers, $hiddencomments));

	if (as_user_permit_error('permit_hide_show') && as_user_permit_error('permit_delete_hidden')) // not allowed to see all hidden posts
		foreach ($questions as $index => $question)
			if (as_user_post_permit_error('permit_hide_show', $question) && as_user_post_permit_error('permit_delete_hidden', $question))
				unset($questions[$index]);


//	Get information for users

	$usershtml=as_userids_handles_html(as_any_get_userids_handles($questions));


//	Create list of actual hidden postids and see which ones have dependents

	$qhiddenpostid=array();
	foreach ($questions as $key => $question)
		$qhiddenpostid[$key]=isset($question['opostid']) ? $question['opostid'] : $question['postid'];
		
	$dependcounts=as_db_postids_count_dependents($qhiddenpostid);
	

//	Prepare content for theme
	
	$content=as_content_prepare();

	$content['title']=as_lang_html('admin/recent_hidden_title');
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
		foreach ($questions as $key => $question) {
			$elementid='p'.$qhiddenpostid[$key];

			$htmloptions=as_post_html_options($question);
			$htmloptions['voteview']=false;
			$htmloptions['tagsview']=!isset($question['opostid']);
			$htmloptions['answersview']=false;
			$htmloptions['viewsview']=false;
			$htmloptions['updateview']=false;
			$htmloptions['contentview']=true;
			$htmloptions['flagsview']=true;
			$htmloptions['elementid']=$elementid;

			$htmlfields=as_any_to_q_html_fields($question, $userid, as_cookie_get(), $usershtml, null, $htmloptions);
			
			if (isset($htmlfields['what_url'])) // link directly to relevant content
				$htmlfields['url']=$htmlfields['what_url'];
				
			$htmlfields['what_2']=as_lang_html('main/hidden');

			if (@$htmloptions['whenview']) {
				$updated=@$question[isset($question['opostid']) ? 'oupdated' : 'updated'];
				if (isset($updated))
					$htmlfields['when_2']=as_when_to_html($updated, @$htmloptions['fulldatedays']);
			}
			
			$buttons=array();
			
			if (!as_user_post_permit_error('permit_hide_show', $question))
				$buttons['reshow']=array(
					'tags' => 'name="admin_'.as_html($qhiddenpostid[$key]).'_reshow" onclick="return as_admin_click(this);"',
					'label' => as_lang_html('question/reshow_button'),
				);
				
			if ((!as_user_post_permit_error('permit_delete_hidden', $question)) && !$dependcounts[$qhiddenpostid[$key]])
				$buttons['delete']=array(
					'tags' => 'name="admin_'.as_html($qhiddenpostid[$key]).'_delete" onclick="return as_admin_click(this);"',
					'label' => as_lang_html('question/delete_button'),
				);
				
			if (count($buttons))
				$htmlfields['form']=array(
					'style' => 'light',
					'buttons' => $buttons,
				);

			$content['q_list']['qs'][]=$htmlfields;
		}

	} else
		$content['title']=as_lang_html('admin/no_hidden_found');
		

	$content['navigation']['sub']=as_admin_sub_navigation();
	$content['script_rel'][]='as-content/as-admin.js?'.AS_VERSION;

	
	return $content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/