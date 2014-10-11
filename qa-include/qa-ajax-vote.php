<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-ajax-vote.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Server-side response to Ajax voting requests


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
	require_once QA_INCLUDE_DIR.'qa-app-cookies.php';
	require_once QA_INCLUDE_DIR.'qa-app-votes.php';
	require_once QA_INCLUDE_DIR.'qa-app-format.php';
	require_once QA_INCLUDE_DIR.'qa-app-options.php';
	require_once QA_INCLUDE_DIR.'qa-db-selects.php';
	

	$postid=as_post_text('postid');
	$vote=as_post_text('vote');
	$code=as_post_text('code');
	
	$userid=as_get_logged_in_userid();
	$cookieid=as_cookie_get();

	if (!as_check_form_security_code('vote', $code))
		$voteerror=as_lang_html('misc/form_security_reload');
	
	else {
		$post=as_db_select_with_pending(as_db_full_post_selectspec($userid, $postid));
		$voteerror=as_vote_error_html($post, $vote, $userid, as_request());
	}
	
	if ($voteerror===false) {
		as_vote_set($post, $userid, as_get_logged_in_handle(), $cookieid, $vote);
		
		$post=as_db_select_with_pending(as_db_full_post_selectspec($userid, $postid));
		
		$fields=as_post_html_fields($post, $userid, $cookieid, array(), null, array(
			'voteview' => as_get_vote_view($post, true), // behave as if on question page since the vote succeeded
		));
		
		$themeclass=as_load_theme_class(as_get_site_theme(), 'voting', null, null);

		echo "QA_AJAX_RESPONSE\n1\n";
		$themeclass->voting_inner_html($fields);

	} else
		echo "QA_AJAX_RESPONSE\n0\n".$voteerror;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/