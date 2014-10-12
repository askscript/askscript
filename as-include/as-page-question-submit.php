<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-question-submit.php
	Version: See define()s at top of as-include/as-base.php
	Description: Common functions for question page form submission, either regular or via Ajax


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


	require_once AS_INCLUDE_DIR.'as-app-post-create.php';
	require_once AS_INCLUDE_DIR.'as-app-post-update.php';


	function as_page_q_single_click_q($question, $answers, $commentsfollows, $closepost, &$error)
/*
	Checks for a POSTed click on $question by the current user and returns true if it was permitted and processed. Pass
	in the question's $answers, all $commentsfollows from it or its answers, and its closing $closepost (or null if
	none). If there is an error to display, it will be passed out in $error.
*/
	{
		require_once AS_INCLUDE_DIR.'as-app-post-update.php';
		require_once AS_INCLUDE_DIR.'as-app-limits.php';

		$userid=as_get_logged_in_userid();
		$handle=as_get_logged_in_handle();
		$cookieid=as_cookie_get();
		
		if (as_clicked('q_doreopen') && $question['reopenable'] && as_page_q_click_check_form_code($question, $error) ) {
			as_question_close_clear($question, $closepost, $userid, $handle, $cookieid);
			return true;
		}
		
		if ( (as_clicked('q_dohide') && $question['hideable']) || (as_clicked('q_doreject') && $question['moderatable']) )
			if (as_page_q_click_check_form_code($question, $error)) {
				as_question_set_hidden($question, true, $userid, $handle, $cookieid, $answers, $commentsfollows, $closepost);
				return true;
			}
		
		if ( (as_clicked('q_doreshow') && $question['reshowable']) || (as_clicked('q_doapprove') && $question['moderatable']) )
			if (as_page_q_click_check_form_code($question, $error)) {
				if ($question['moderatable'] || $question['reshowimmed']) {
					$status=AS_POST_STATUS_NORMAL;

				} else {
					$in=as_page_q_prepare_post_for_filters($question);
					$filtermodules=as_load_modules_with('filter', 'filter_question'); // run through filters but only for queued status

					foreach ($filtermodules as $filtermodule) {
						$tempin=$in; // always pass original question in because we aren't modifying anything else
						$filtermodule->filter_question($tempin, $temperrors, $question);
						$in['queued']=$tempin['queued']; // only preserve queued status in loop
					}
					
					$status=$in['queued'] ? AS_POST_STATUS_QUEUED : AS_POST_STATUS_NORMAL;
				}
				
				as_question_set_status($question, $status, $userid, $handle, $cookieid, $answers, $commentsfollows, $closepost);
				return true;
			}
		
		if (as_clicked('q_doclaim') && $question['claimable'] && as_page_q_click_check_form_code($question, $error) ) {
			if (as_user_limits_remaining(AS_LIMIT_QUESTIONS)) { // already checked 'permit_post_q'
				as_question_set_userid($question, $userid, $handle, $cookieid);
				return true;
	
			} else
				$error=as_lang_html('question/ask_limit');
		}
		
		if (as_clicked('q_doflag') && $question['flagbutton'] && as_page_q_click_check_form_code($question, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
			
			$error=as_flag_error_html($question, $userid, as_request());
			if (!$error) {
				if (as_flag_set_tohide($question, $userid, $handle, $cookieid, $question))
					as_question_set_hidden($question, true, null, null, null, $answers, $commentsfollows, $closepost); // hiding not really by this user so pass nulls
				return true;
			}
		}
		
		if (as_clicked('q_dounflag') && $question['unflaggable'] && as_page_q_click_check_form_code($question, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
			
			as_flag_clear($question, $userid, $handle, $cookieid);
			return true;
		}
		
		if (as_clicked('q_doclearflags') && $question['clearflaggable'] && as_page_q_click_check_form_code($question, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
		
			as_flags_clear_all($question, $userid, $handle, $cookieid);
			return true;
		}
		
		return false;
	}
	
	
	function as_page_q_single_click_a($answer, $question, $answers, $commentsfollows, $allowselectmove, &$error)
/*
	Checks for a POSTed click on $answer by the current user and returns true if it was permitted and processed. Pass in
	the $question, all of its $answers, and all $commentsfollows from it or its answers. Set $allowselectmove to whether
	it is legitimate to change the selected answer for the question from one to another (this can't be done via Ajax).
	If there is an error to display, it will be passed out in $error.
*/
	{
		$userid=as_get_logged_in_userid();
		$handle=as_get_logged_in_handle();
		$cookieid=as_cookie_get();
		
		$prefix='a'.$answer['postid'].'_';
		
		if (as_clicked($prefix.'doselect') && $question['aselectable'] && ($allowselectmove || ( (!isset($question['selchildid'])) && !as_opt('do_close_on_select'))) && as_page_q_click_check_form_code($answer, $error) ) {
			as_question_set_selchildid($userid, $handle, $cookieid, $question, $answer['postid'], $answers);
			return true;
		}
		
		if (as_clicked($prefix.'dounselect') && $question['aselectable'] && ($question['selchildid']==$answer['postid']) && ($allowselectmove || !as_opt('do_close_on_select')) && as_page_q_click_check_form_code($answer, $error)) {
			as_question_set_selchildid($userid, $handle, $cookieid, $question, null, $answers);
			return true;
		}

		if ( (as_clicked($prefix.'dohide') && $answer['hideable']) || (as_clicked($prefix.'doreject') && $answer['moderatable']) )
			if (as_page_q_click_check_form_code($answer, $error)) {
				as_answer_set_hidden($answer, true, $userid, $handle, $cookieid, $question, $commentsfollows);
				return true;
			}
		
		if ( (as_clicked($prefix.'doreshow') && $answer['reshowable']) || (as_clicked($prefix.'doapprove') && $answer['moderatable']) )
			if (as_page_q_click_check_form_code($answer, $error)) {
				if ($answer['moderatable'] || $answer['reshowimmed']) {
					$status=AS_POST_STATUS_NORMAL;
					
				} else {
					$in=as_page_q_prepare_post_for_filters($answer);
					$filtermodules=as_load_modules_with('filter', 'filter_answer'); // run through filters but only for queued status
					
					foreach ($filtermodules as $filtermodule) {
						$tempin=$in; // always pass original answer in because we aren't modifying anything else
						$filtermodule->filter_answer($tempin, $temperrors, $question, $answer);
						$in['queued']=$tempin['queued']; // only preserve queued status in loop
					}
					
					$status=$in['queued'] ? AS_POST_STATUS_QUEUED : AS_POST_STATUS_NORMAL;
				}
				
				as_answer_set_status($answer, $status, $userid, $handle, $cookieid, $question, $commentsfollows);
				return true;
			}
		
		if (as_clicked($prefix.'dodelete') && $answer['deleteable'] && as_page_q_click_check_form_code($answer, $error)) {
			as_answer_delete($answer, $question, $userid, $handle, $cookieid);
			return true;
		}
		
		if (as_clicked($prefix.'doclaim') && $answer['claimable'] && as_page_q_click_check_form_code($answer, $error)) {
			if (as_user_limits_remaining(AS_LIMIT_ANSWERS)) { // already checked 'permit_post_a'
				as_answer_set_userid($answer, $userid, $handle, $cookieid);
				return true;
			
			} else
				$error=as_lang_html('question/answer_limit');
		}
		
		if (as_clicked($prefix.'doflag') && $answer['flagbutton'] && as_page_q_click_check_form_code($answer, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
			
			$error=as_flag_error_html($answer, $userid, as_request());
			if (!$error) {
				if (as_flag_set_tohide($answer, $userid, $handle, $cookieid, $question))
					as_answer_set_hidden($answer, true, null, null, null, $question, $commentsfollows); // hiding not really by this user so pass nulls
					
				return true;
			}
		}

		if (as_clicked($prefix.'dounflag') && $answer['unflaggable'] && as_page_q_click_check_form_code($answer, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
			
			as_flag_clear($answer, $userid, $handle, $cookieid);
			return true;
		}
		
		if (as_clicked($prefix.'doclearflags') && $answer['clearflaggable'] && as_page_q_click_check_form_code($answer, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
			
			as_flags_clear_all($answer, $userid, $handle, $cookieid);
			return true;
		}

		return false;
	}
	
	
	function as_page_q_single_click_c($comment, $question, $parent, &$error)
/*
	Checks for a POSTed click on $comment by the current user and returns true if it was permitted and processed. Pass
	in the antecedent $question and the comment's $parent post. If there is an error to display, it will be passed out
	in $error.
*/
	{
		$userid=as_get_logged_in_userid();
		$handle=as_get_logged_in_handle();
		$cookieid=as_cookie_get();
		
		$prefix='c'.$comment['postid'].'_';
		
		if ( (as_clicked($prefix.'dohide') && $comment['hideable']) || (as_clicked($prefix.'doreject') && $comment['moderatable']) )
			if (as_page_q_click_check_form_code($parent, $error)) {
				as_comment_set_hidden($comment, true, $userid, $handle, $cookieid, $question, $parent);
				return true;
			}
		
		if ( (as_clicked($prefix.'doreshow') && $comment['reshowable']) || (as_clicked($prefix.'doapprove') && $comment['moderatable']) )
			if (as_page_q_click_check_form_code($parent, $error)) {
				if ($comment['moderatable'] || $comment['reshowimmed']) {
					$status=AS_POST_STATUS_NORMAL;
					
				} else {
					$in=as_page_q_prepare_post_for_filters($comment);
					$filtermodules=as_load_modules_with('filter', 'filter_comment'); // run through filters but only for queued status
					
					foreach ($filtermodules as $filtermodule) {
						$tempin=$in; // always pass original comment in because we aren't modifying anything else
						$filtermodule->filter_comment($tempin, $temperrors, $question, $parent, $comment);
						$in['queued']=$tempin['queued']; // only preserve queued status in loop
					}
					
					$status=$in['queued'] ? AS_POST_STATUS_QUEUED : AS_POST_STATUS_NORMAL;
				}
				
				as_comment_set_status($comment, $status, $userid, $handle, $cookieid, $question, $parent);
				return true;
			}
		
		if (as_clicked($prefix.'dodelete') && $comment['deleteable'] && as_page_q_click_check_form_code($parent, $error)) {
			as_comment_delete($comment, $question, $parent, $userid, $handle, $cookieid);
			return true;
		}
			
		if (as_clicked($prefix.'doclaim') && $comment['claimable'] && as_page_q_click_check_form_code($parent, $error)) {
			if (as_user_limits_remaining(AS_LIMIT_COMMENTS)) {
				as_comment_set_userid($comment, $userid, $handle, $cookieid);
				return true;
				
			} else
				$error=as_lang_html('question/comment_limit');
		}
		
		if (as_clicked($prefix.'doflag') && $comment['flagbutton'] && as_page_q_click_check_form_code($parent, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
			
			$error=as_flag_error_html($comment, $userid, as_request());
			if (!$error) {
				if (as_flag_set_tohide($comment, $userid, $handle, $cookieid, $question))
					as_comment_set_hidden($comment, true, null, null, null, $question, $parent); // hiding not really by this user so pass nulls
				
				return true;
			}
		}

		if (as_clicked($prefix.'dounflag') && $comment['unflaggable'] && as_page_q_click_check_form_code($parent, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
			
			as_flag_clear($comment, $userid, $handle, $cookieid);
			return true;
		}
		
		if (as_clicked($prefix.'doclearflags') && $comment['clearflaggable'] && as_page_q_click_check_form_code($parent, $error)) {
			require_once AS_INCLUDE_DIR.'as-app-votes.php';
			
			as_flags_clear_all($comment, $userid, $handle, $cookieid);
			return true;
		}
		
		return false;
	}
	
	
	function as_page_q_click_check_form_code($post, &$error)
/*
	Check the form security (anti-CSRF protection) for one of the buttons shown for post $post. Return true if the
	security passed, otherwise return false and set an error message in $error
*/
	{
		$result=as_check_form_security_code('buttons-'.$post['postid'], as_post_text('code'));
		
		if (!$result)
			$error=as_lang_html('misc/form_security_again');
		
		return $result;
	}
	
	
	function as_page_q_add_a_submit($question, $answers, $usecaptcha, &$in, &$errors)
/*
	Processes a POSTed form to add an answer to $question, returning the postid if successful, otherwise null. Pass in
	other $answers to the question and whether a $usecaptcha is required. The form fields submitted will be passed out
	as an array in $in, as well as any $errors on those fields.
*/
	{
		$in=array(
			'name' => as_post_text('a_name'),
			'notify' => as_post_text('a_notify') ? true : false,
			'email' => as_post_text('a_email'),
			'queued' => as_user_moderation_reason(as_user_level_for_post($question)) ? true : false,
		);
		
		as_get_post_content('a_editor', 'a_content', $in['editor'], $in['content'], $in['format'], $in['text']);
		
		$errors=array();

		if (!as_check_form_security_code('answer-'.$question['postid'], as_post_text('code')))
			$errors['content']=as_lang_html('misc/form_security_again');
		
		else {
			$filtermodules=as_load_modules_with('filter', 'filter_answer');
			foreach ($filtermodules as $filtermodule) {
				$oldin=$in;
				$filtermodule->filter_answer($in, $errors, $question, null);
				as_update_post_text($in, $oldin);
			}
			
			if ($usecaptcha)
				as_captcha_validate_post($errors);
				
			if (empty($errors)) {
				$testwords=implode(' ', as_string_to_words($in['content']));
				
				foreach ($answers as $answer)
					if (!$answer['hidden'])
						if (implode(' ', as_string_to_words($answer['content'])) == $testwords)
							$errors['content']=as_lang_html('question/duplicate_content');
			}
			
			if (empty($errors)) {
				$userid=as_get_logged_in_userid();
				$handle=as_get_logged_in_handle();
				$cookieid=isset($userid) ? as_cookie_get() : as_cookie_get_create(); // create a new cookie if necessary
				
				$answerid=as_answer_create($userid, $handle, $cookieid, $in['content'], $in['format'], $in['text'], $in['notify'], $in['email'],
					$question, $in['queued'], $in['name']);
				
				return $answerid;
			}
		}
		
		return null;
	}
	

	function as_page_q_add_c_submit($question, $parent, $commentsfollows, $usecaptcha, &$in, &$errors)
/*
	Processes a POSTed form to add a comment, returning the postid if successful, otherwise null. Pass in the antecedent
	$question and the comment's $parent post. Set $usecaptcha to whether a captcha is required. Pass an array which
	includes the other comments with the same parent in $commentsfollows (it can contain other posts which are ignored).
	The form fields submitted will be passed out as an array in $in, as well as any $errors on those fields.
*/
	{
		$parentid=$parent['postid'];
		
		$prefix='c'.$parentid.'_';
		
		$in=array(
			'name' => as_post_text($prefix.'name'),
			'notify' => as_post_text($prefix.'notify') ? true : false,
			'email' => as_post_text($prefix.'email'),
			'queued' => as_user_moderation_reason(as_user_level_for_post($parent)) ? true : false,
		);
		
		as_get_post_content($prefix.'editor', $prefix.'content', $in['editor'], $in['content'], $in['format'], $in['text']);

		$errors=array();
		
		if (!as_check_form_security_code('comment-'.$parent['postid'], as_post_text($prefix.'code')))
			$errors['content']=as_lang_html('misc/form_security_again');
		
		else {
			$filtermodules=as_load_modules_with('filter', 'filter_comment');
			foreach ($filtermodules as $filtermodule) {
				$oldin=$in;
				$filtermodule->filter_comment($in, $errors, $question, $parent, null);
				as_update_post_text($in, $oldin);
			}
			
			if ($usecaptcha)
				as_captcha_validate_post($errors);
	
			if (empty($errors)) {
				$testwords=implode(' ', as_string_to_words($in['content']));
				
				foreach ($commentsfollows as $comment)
					if (($comment['basetype']=='C') && ($comment['parentid']==$parentid) && !$comment['hidden'])
						if (implode(' ', as_string_to_words($comment['content'])) == $testwords)
							$errors['content']=as_lang_html('question/duplicate_content');
			}
			
			if (empty($errors)) {
				$userid=as_get_logged_in_userid();
				$handle=as_get_logged_in_handle();
				$cookieid=isset($userid) ? as_cookie_get() : as_cookie_get_create(); // create a new cookie if necessary
							
				$commentid=as_comment_create($userid, $handle, $cookieid, $in['content'], $in['format'], $in['text'], $in['notify'], $in['email'],
					$question, $parent, $commentsfollows, $in['queued'], $in['name']);
				
				return $commentid;
			}
		}
		
		return null;
	}
	
	
	function as_page_q_prepare_post_for_filters($post)
/*
	Return the array of information to be passed to filter modules for the post in $post (from the database)
*/
	{
		$in=array(
			'content' => $post['content'],
			'format' => $post['format'],
			'text' => as_viewer_text($post['content'], $post['format']),
			'notify' => isset($post['notify']) ? true : false,
			'email' => as_email_validate($post['notify']) ? $post['notify'] : null,
			'queued' => as_user_moderation_reason(as_user_level_for_post($post)) ? true : false,
		);
		
		if ($post['basetype']=='Q') {
			$in['title']=$post['title'];
			$in['tags']=as_tagstring_to_tags($post['tags']);
			$in['categoryid']=$post['categoryid'];
			$in['extra']=$post['extra'];
		}
		
		return $in;
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/