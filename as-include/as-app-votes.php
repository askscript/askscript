<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-app-votes.php
	Version: See define()s at top of as-include/as-base.php
	Description: Handling incoming votes (application level)


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


	function as_vote_error_html($post, $vote, $userid, $topage)
/*
	Check if $userid can vote on $post, on the page $topage.
	Return an HTML error to display if there was a problem, or false if it's OK.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		// The 'login', 'confirm', 'limit', 'userblock' and 'ipblock' permission errors are reported to the user here.
		// Others ('approve', 'level') prevent the buttons being clickable in the first place, in as_get_vote_view(...)

		require_once AS_INCLUDE_DIR.'as-app-users.php';
		require_once AS_INCLUDE_DIR.'as-app-limits.php';
		
		if (
			is_array($post) &&
			( ($post['basetype']=='Q') || ($post['basetype']=='A') ) &&
			as_opt(($post['basetype']=='Q') ? 'voting_on_qs' : 'voting_on_as') &&
			( (!isset($post['userid'])) || (!isset($userid)) || ($post['userid']!=$userid) )
		) {
			$permiterror=as_user_post_permit_error(($post['basetype']=='Q') ? 'permit_vote_q' : 'permit_vote_a', $post, AS_LIMIT_VOTES);
			
			$errordownonly=(!$permiterror) && ($vote<0);
			if ($errordownonly)
				$permiterror=as_user_post_permit_error('permit_vote_down', $post);
				
			switch ($permiterror) {
				case 'login':
					return as_insert_login_links(as_lang_html('main/vote_must_login'), $topage);
					break;
					
				case 'confirm':
					return as_insert_login_links(as_lang_html($errordownonly ? 'main/vote_down_must_confirm' : 'main/vote_must_confirm'), $topage);
					break;
					
				case 'limit':
					return as_lang_html('main/vote_limit');
					break;
					
				default:
					return as_lang_html('users/no_permission');
					break;
					
				case false:
					return false;
			}
		
		} else
			return as_lang_html('main/vote_not_allowed'); // voting option should not have been presented (but could happen due to options change)
	}

	
	function as_vote_set($post, $userid, $handle, $cookieid, $vote)
/*
	Actually set (application level) the $vote (-1/0/1) by $userid (with $handle and $cookieid) on $postid.
	Handles user points, recounting and event reports as appropriate.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-points.php';
		require_once AS_INCLUDE_DIR.'as-db-hotness.php';
		require_once AS_INCLUDE_DIR.'as-db-votes.php';
		require_once AS_INCLUDE_DIR.'as-db-post-create.php';
		require_once AS_INCLUDE_DIR.'as-app-limits.php';
		
		$vote=(int)min(1, max(-1, $vote));
		$oldvote=(int)as_db_uservote_get($post['postid'], $userid);

		as_db_uservote_set($post['postid'], $userid, $vote);
		as_db_post_recount_votes($post['postid']);
		
		$postisanswer=($post['basetype']=='A');
		
		if ($postisanswer) {
			as_db_post_acount_update($post['parentid']);
			as_db_unupaqcount_update();
		}
		
		$columns=array();
		
		if ( ($vote>0) || ($oldvote>0) )
			$columns[]=$postisanswer ? 'aupvotes' : 'qupvotes';

		if ( ($vote<0) || ($oldvote<0) )
			$columns[]=$postisanswer ? 'adownvotes' : 'qdownvotes';
			
		as_db_points_update_ifuser($userid, $columns);
		
		as_db_points_update_ifuser($post['userid'], array($postisanswer ? 'avoteds' : 'qvoteds', 'upvoteds', 'downvoteds'));
		
		if ($post['basetype']=='Q')
			as_db_hotness_update($post['postid']);
		
		if ($vote<0)
			$event=$postisanswer ? 'a_vote_down' : 'q_vote_down';
		elseif ($vote>0)
			$event=$postisanswer ? 'a_vote_up' : 'q_vote_up';
		else
			$event=$postisanswer ? 'a_vote_nil' : 'q_vote_nil';
		
		as_report_event($event, $userid, $handle, $cookieid, array(
			'postid' => $post['postid'],
			'vote' => $vote,
			'oldvote' => $oldvote,
		));
	}
	
	
	function as_flag_error_html($post, $userid, $topage)
/*
	Check if $userid can flag $post, on the page $topage.
	Return an HTML error to display if there was a problem, or false if it's OK.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		// The 'login', 'confirm', 'limit', 'userblock' and 'ipblock' permission errors are reported to the user here.
		// Others ('approve', 'level') prevent the flag button being shown, in as_page_q_post_rules(...)

		require_once AS_INCLUDE_DIR.'as-db-selects.php';
		require_once AS_INCLUDE_DIR.'as-app-options.php';
		require_once AS_INCLUDE_DIR.'as-app-users.php';
		require_once AS_INCLUDE_DIR.'as-app-limits.php';

		if (
			is_array($post) &&
			as_opt('flagging_of_posts') &&
			( (!isset($post['userid'])) || (!isset($userid)) || ($post['userid']!=$userid) )
		) {
		
			switch (as_user_post_permit_error('permit_flag', $post, AS_LIMIT_FLAGS)) {
				case 'login':
					return as_insert_login_links(as_lang_html('question/flag_must_login'), $topage);
					break;
					
				case 'confirm':
					return as_insert_login_links(as_lang_html('question/flag_must_confirm'), $topage);
					break;
					
				case 'limit':
					return as_lang_html('question/flag_limit');
					break;
					
				default:
					return as_lang_html('users/no_permission');
					break;
					
				case false:
					return false;
			}
		
		} else
			return as_lang_html('question/flag_not_allowed'); // flagging option should not have been presented
	}
	

	function as_flag_set_tohide($oldpost, $userid, $handle, $cookieid, $question)
/*
	Set (application level) a flag by $userid (with $handle and $cookieid) on $oldpost which belongs to $question.
	Handles recounting, admin notifications and event reports as appropriate.
	Returns true if the post should now be hidden because it has accumulated enough flags.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-votes.php';
		require_once AS_INCLUDE_DIR.'as-app-limits.php';
		require_once AS_INCLUDE_DIR.'as-db-post-update.php';
		
		as_db_userflag_set($oldpost['postid'], $userid, true);
		as_db_post_recount_flags($oldpost['postid']);
		as_db_flaggedcount_update();
		
		switch ($oldpost['basetype']) {
			case 'Q':
				$event='q_flag';
				break;
				
			case 'A':
				$event='a_flag';
				break;

			case 'C':
				$event='c_flag';
				break;
		}
		
		$post=as_db_select_with_pending(as_db_full_post_selectspec(null, $oldpost['postid']));
		
		as_report_event($event, $userid, $handle, $cookieid, array(
			'postid' => $oldpost['postid'],
			'oldpost' => $oldpost,
			'flagcount' => $post['flagcount'],
			'questionid' => $question['postid'],
			'question' => $question,
		));
		
		return ($post['flagcount']>=as_opt('flagging_hide_after')) && !$post['hidden'];
	}


	function as_flag_clear($oldpost, $userid, $handle, $cookieid)
/*
	Clear (application level) a flag on $oldpost by $userid (with $handle and $cookieid).
	Handles recounting and event reports as appropriate.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-votes.php';
		require_once AS_INCLUDE_DIR.'as-app-limits.php';
		require_once AS_INCLUDE_DIR.'as-db-post-update.php';
		
		as_db_userflag_set($oldpost['postid'], $userid, false);
		as_db_post_recount_flags($oldpost['postid']);
		as_db_flaggedcount_update();
		
		switch ($oldpost['basetype']) {
			case 'Q':
				$event='q_unflag';
				break;
				
			case 'A':
				$event='a_unflag';
				break;

			case 'C':
				$event='c_unflag';
				break;
		}
		
		as_report_event($event, $userid, $handle, $cookieid, array(
			'postid' => $oldpost['postid'],
			'oldpost' => $oldpost,
		));
	}
	
	
	function as_flags_clear_all($oldpost, $userid, $handle, $cookieid)
/*
	Clear (application level) all flags on $oldpost by $userid (with $handle and $cookieid).
	Handles recounting and event reports as appropriate.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-votes.php';
		require_once AS_INCLUDE_DIR.'as-app-limits.php';
		require_once AS_INCLUDE_DIR.'as-db-post-update.php';
		
		as_db_userflags_clear_all($oldpost['postid']);
		as_db_post_recount_flags($oldpost['postid']);
		as_db_flaggedcount_update();

		switch ($oldpost['basetype']) {
			case 'Q':
				$event='q_clearflags';
				break;
				
			case 'A':
				$event='a_clearflags';
				break;

			case 'C':
				$event='c_clearflags';
				break;
		}

		as_report_event($event, $userid, $handle, $cookieid, array(
			'postid' => $oldpost['postid'],
			'oldpost' => $oldpost,
		));
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/