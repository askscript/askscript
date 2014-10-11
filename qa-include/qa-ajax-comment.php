<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-ajax-comment.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Server-side response to Ajax create comment requests


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

	require_once QA_INCLUDE_DIR.'qa-app-users.php';
	require_once QA_INCLUDE_DIR.'qa-app-limits.php';
	require_once QA_INCLUDE_DIR.'qa-db-selects.php';


//	Load relevant information about this question and the comment parent

	$questionid=as_post_text('c_questionid');
	$parentid=as_post_text('c_parentid');
	$userid=as_get_logged_in_userid();
	
	list($question, $parent, $children)=as_db_select_with_pending(
		as_db_full_post_selectspec($userid, $questionid),
		as_db_full_post_selectspec($userid, $parentid),
		as_db_full_child_posts_selectspec($userid, $parentid)
	);


//	Check if the question and parent exist, and whether the user has permission to do this

	if (
		(@$question['basetype']=='Q') &&
		((@$parent['basetype']=='Q') || (@$parent['basetype']=='A')) &&
		!as_user_post_permit_error('permit_post_c', $parent, QA_LIMIT_COMMENTS))
	{
		require_once QA_INCLUDE_DIR.'qa-app-captcha.php';
		require_once QA_INCLUDE_DIR.'qa-app-format.php';
		require_once QA_INCLUDE_DIR.'qa-app-post-create.php';
		require_once QA_INCLUDE_DIR.'qa-app-cookies.php';
		require_once QA_INCLUDE_DIR.'qa-page-question-view.php';
		require_once QA_INCLUDE_DIR.'qa-page-question-submit.php';
		require_once QA_INCLUDE_DIR.'qa-util-sort.php';


	//	Try to create the new comment
	
		$usecaptcha=as_user_use_captcha(as_user_level_for_post($question));
		$commentid=as_page_q_add_c_submit($question, $parent, $children, $usecaptcha, $in, $errors);
		

	//	If successful, page content will be updated via Ajax

		if (isset($commentid)) {
			$children=as_db_select_with_pending(as_db_full_child_posts_selectspec($userid, $parentid));
			
			$parent=$parent+as_page_q_post_rules($parent, ($questionid==$parentid) ? null : $question, null, $children);				
				// in theory we should retrieve the parent's siblings for the above, but they're not going to be relevant

			foreach ($children as $key => $child)
				$children[$key]=$child+as_page_q_post_rules($child, $parent, $children, null);
			
			$usershtml=as_userids_handles_html($children, true);
			
			as_sort_by($children, 'created');
			
			$c_list=as_page_q_comment_follow_list($question, $parent, $children, true, $usershtml, false, null);
			
			$themeclass=as_load_theme_class(as_get_site_theme(), 'ajax-comments', null, null);
			
			echo "QA_AJAX_RESPONSE\n1\n";
			

		//	Send back the ID of the new comment
		
			echo as_anchor('C', $commentid)."\n";
			

		//	Send back the HTML

			$themeclass->c_list_items($c_list['cs']);

			return;
		}
	}
	
	echo "QA_AJAX_RESPONSE\n0\n"; // fall back to non-Ajax submission if there were any problems

	
/*
	Omit PHP closing tag to help avoid accidental output
*/