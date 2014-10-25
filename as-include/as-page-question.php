<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-question.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for question page (only viewing functionality here)


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

	require_once AS_INCLUDE_DIR.'as-app-cookies.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';
	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-util-sort.php';
	require_once AS_INCLUDE_DIR.'as-util-string.php';
	require_once AS_INCLUDE_DIR.'as-app-captcha.php';
	require_once AS_INCLUDE_DIR.'as-page-question-view.php';
	require_once AS_INCLUDE_DIR.'as-app-updates.php';
	
	$questionid=as_request_part(0);
	$userid=as_get_logged_in_userid();
	$cookieid=as_cookie_get();


//	Get information about this question

	list($question, $childposts, $achildposts, $parentquestion, $closepost, $extravalue, $categories, $favorite)=as_db_select_with_pending(
		as_db_full_post_selectspec($userid, $questionid),
		as_db_full_child_posts_selectspec($userid, $questionid),
		as_db_full_a_child_posts_selectspec($userid, $questionid),
		as_db_post_parent_q_selectspec($questionid),
		as_db_post_close_post_selectspec($questionid),
		as_db_post_meta_selectspec($questionid, 'as_q_extra'),
		as_db_category_nav_selectspec($questionid, true, true, true),
		isset($userid) ? as_db_is_favorite_selectspec($userid, AS_ENTITY_QUESTION, $questionid) : null
	);
	
	if ($question['basetype']!='Q') // don't allow direct viewing of other types of post
		$question=null;

	if (isset($question)) {
		$question['extra']=$extravalue;
		
		$answers=as_page_q_load_as($question, $childposts);
		$commentsfollows=as_page_q_load_c_follows($question, $childposts, $achildposts);
		
		$question=$question+as_page_q_post_rules($question, null, null, $childposts); // array union
		
		if ($question['selchildid'] && (@$answers[$question['selchildid']]['type']!='A'))
			$question['selchildid']=null; // if selected answer is hidden or somehow not there, consider it not selected

		foreach ($answers as $key => $answer) {
			$answers[$key]=$answer+as_page_q_post_rules($answer, $question, $answers, $achildposts);
			$answers[$key]['isselected']=($answer['postid']==$question['selchildid']);
		}

		foreach ($commentsfollows as $key => $commentfollow) {
			$parent=($commentfollow['parentid']==$questionid) ? $question : @$answers[$commentfollow['parentid']];
			$commentsfollows[$key]=$commentfollow+as_page_q_post_rules($commentfollow, $parent, $commentsfollows, null);
		}
	}
	
//	Deal with question not found or not viewable, otherwise report the view event

	if (!isset($question))
		return include AS_INCLUDE_DIR.'as-page-not-found.php';

	if (!$question['viewable']) {
		$content=as_content_prepare();
		
		if ($question['queued'])
			$content['error']=as_lang_html('question/q_waiting_approval');
		elseif ($question['flagcount'] && !isset($question['lastuserid']))
			$content['error']=as_lang_html('question/q_hidden_flagged');
		elseif ($question['authorlast'])
			$content['error']=as_lang_html('question/q_hidden_author');
		else
			$content['error']=as_lang_html('question/q_hidden_other');

		$content['suggest_next']=as_html_suggest_qs_tags(as_using_tags());

		return $content;
	}
	
	$permiterror=as_user_post_permit_error('permit_view_q_page', $question, null, false);
	
	if ( $permiterror && (as_is_human_probably() || !as_opt('allow_view_q_bots')) ) {
		$content=as_content_prepare();
		$topage=as_q_request($questionid, $question['title']);
		
		switch ($permiterror) {
			case 'login':
				$content['error']=as_insert_login_links(as_lang_html('main/view_q_must_login'), $topage);
				break;
				
			case 'confirm':
				$content['error']=as_insert_login_links(as_lang_html('main/view_q_must_confirm'), $topage);
				break;
				
			case 'approve':
				$content['error']=as_lang_html('main/view_q_must_be_approved');
				break;
				
			default:
				$content['error']=as_lang_html('users/no_permission');
				break;
		}
		
		return $content;
	}


//	Determine if captchas will be required
	
	$captchareason=as_user_captcha_reason(as_user_level_for_post($question));
	$usecaptcha=($captchareason!=false);


//	If we're responding to an HTTP POST, include file that handles all posting/editing/etc... logic
//	This is in a separate file because it's a *lot* of logic, and will slow down ordinary page views

	$pagestart=as_get_start();
	$pagestate=as_get_state();
	$showid=as_get('show');
	$pageerror=null;
	$formtype=null;
	$formpostid=null;
	$jumptoanchor=null;
	$commentsall=null;
	
	if (substr($pagestate, 0, 13)=='showcomments-') {
		$commentsall=substr($pagestate, 13);
		$pagestate=null;
	
	} elseif (isset($showid)) {
		foreach ($commentsfollows as $comment)
			if ($comment['postid']==$showid) {
				$commentsall=$comment['parentid'];
				break;
			}
	}
	
	if (as_is_http_post() || strlen($pagestate))
		require AS_INCLUDE_DIR.'as-page-question-post.php';
	
	$formrequested=isset($formtype);

	if ((!$formrequested) && $question['answerbutton']) {
		$immedoption=as_opt('show_a_form_immediate');

		if ( ($immedoption=='always') || (($immedoption=='if_no_as') && (!$question['isbyuser']) && (!$question['acount'])) )
			$formtype='a_add'; // show answer form by default
	}
	
	
//	Get information on the users referenced

	$usershtml=as_userids_handles_html(array_merge(array($question), $answers, $commentsfollows), true);
	
	
//	Prepare content for theme
	
	$content=as_content_prepare(true, array_keys(as_category_path($categories, $question['categoryid'])));
	
	if (isset($userid) && !$formrequested)
		$content['favorite']=as_favorite_form(AS_ENTITY_QUESTION, $questionid, $favorite, 
			as_lang($favorite ? 'question/remove_q_favorites' : 'question/add_q_favorites'));

	$content['script_rel'][]='as-content/as-question.js?'.AS_VERSION;

	if (isset($pageerror))
		$content['error']=$pageerror; // might also show voting error set in as-index.php
	
	elseif ($question['queued'])
		$content['error']=$question['isbyuser'] ? as_lang_html('question/q_your_waiting_approval') : as_lang_html('question/q_waiting_your_approval');
	
	if ($question['hidden'])
		$content['hidden']=true;
	
	as_sort_by($commentsfollows, 'created');


//	Prepare content for the question...
	
	if ($formtype=='q_edit') { // ...in edit mode
		$content['title']=as_lang_html($question['editable'] ? 'question/edit_q_title' :
			(as_using_categories() ? 'question/recat_q_title' : 'question/retag_q_title'));
		$content['form_q_edit']=as_page_q_edit_q_form($content, $question, @$qin, @$qerrors, $completetags, $categories);
		$content['q_view']['raw']=$question;

	} else { // ...in view mode
		$content['q_view']=as_page_q_question_view($question, $parentquestion, $closepost, $usershtml, $formrequested);

		$content['title']=$content['q_view']['title'];

		$content['description']=as_html(as_shorten_string_line(as_viewer_text($question['content'], $question['format']), 150));
		
		$categorykeyword=@$categories[$question['categoryid']]['title'];
		
		$content['keywords']=as_html(implode(',', array_merge(
			(as_using_categories() && strlen($categorykeyword)) ? array($categorykeyword) : array(),
			as_tagstring_to_tags($question['tags'])
		))); // as far as I know, META keywords have zero effect on search rankings or listings, but many people have asked for this
	}
	

//	Prepare content for an answer being edited (if any) or to be added

	if ($formtype=='a_edit') {
		$content['a_form']=as_page_q_edit_a_form($content, 'a'.$formpostid, $answers[$formpostid],
			$question, $answers, $commentsfollows, @$aeditin[$formpostid], @$aediterrors[$formpostid]);

		$content['a_form']['c_list']=as_page_q_comment_follow_list($question, $answers[$formpostid],
			$commentsfollows, true, $usershtml, $formrequested, $formpostid);

		$jumptoanchor='a'.$formpostid;
	
	} elseif (($formtype=='a_add') || ($question['answerbutton'] && !$formrequested)) {
		$content['a_form']=as_page_q_add_a_form($content, 'anew', $captchareason, $question, @$anewin, @$anewerrors, $formtype=='a_add', $formrequested);
		
		if ($formrequested)
			$jumptoanchor='anew';
		elseif ($formtype=='a_add')
			$content['script_onloads'][]=array(
				"as_element_revealed=document.getElementById('anew');"
			);
	}


//	Prepare content for comments on the question, plus add or edit comment forms

	if ($formtype=='q_close') {
		$content['q_view']['c_form']=as_page_q_close_q_form($content, $question, 'close', @$closein, @$closeerrors);
		$jumptoanchor='close';
	
	} elseif ((($formtype=='c_add') && ($formpostid==$questionid)) || ($question['commentbutton'] && !$formrequested) ) { // ...to be added
		$content['q_view']['c_form']=as_page_q_add_c_form($content, $question, $question, 'c'.$questionid,
			$captchareason, @$cnewin[$questionid], @$cnewerrors[$questionid], $formtype=='c_add');
		
		if (($formtype=='c_add') && ($formpostid==$questionid)) {
			$jumptoanchor='c'.$questionid;
			$commentsall=$questionid;
		}
		
	} elseif (($formtype=='c_edit') && (@$commentsfollows[$formpostid]['parentid']==$questionid)) { // ...being edited
		$content['q_view']['c_form']=as_page_q_edit_c_form($content, 'c'.$formpostid, $commentsfollows[$formpostid],
			@$ceditin[$formpostid], @$cediterrors[$formpostid]);

		$jumptoanchor='c'.$formpostid;
		$commentsall=$questionid;
	}

	$content['q_view']['c_list']=as_page_q_comment_follow_list($question, $question, $commentsfollows,
		$commentsall==$questionid, $usershtml, $formrequested, $formpostid); // ...for viewing
	

//	Prepare content for existing answers (could be added to by Ajax)

	$content['a_list']=array(
		'tags' => 'id="a_list"',
		'as' => array(),
	);
	
	// sort according to the site preferences
	
	if (as_opt('sort_answers_by')=='votes') {
		foreach ($answers as $answerid => $answer)
			$answers[$answerid]['sortvotes']=$answer['downvotes']-$answer['upvotes'];

		as_sort_by($answers, 'sortvotes', 'created');

	} else
		as_sort_by($answers, 'created');
	
	// further changes to ordering to deal with queued, hidden and selected answers
	
	$countfortitle=$question['acount'];
	$nextposition=10000;
	$answerposition=array();
	
	foreach ($answers as $answerid => $answer)
		if ($answer['viewable']) {
			$position=$nextposition++;
			
			if ($answer['hidden'])
				$position+=10000;
			
			elseif ($answer['queued']) {
				$position-=10000;
				$countfortitle++; // include these in displayed count
			
			} elseif ($answer['isselected'] && as_opt('show_selected_first'))
				$position-=5000;
	
			$answerposition[$answerid]=$position;
		}
	
	asort($answerposition, SORT_NUMERIC);
	
	// extract IDs and prepare for pagination
	
	$answerids=array_keys($answerposition);
	$countforpages=count($answerids);
	$pagesize=as_opt('page_size_q_as');
	
	// see if we need to display a particular answer
	
	if (isset($showid)) {
		if (isset($commentsfollows[$showid]))
			$showid=$commentsfollows[$showid]['parentid'];
		
		$position=array_search($showid, $answerids);
		
		if (is_numeric($position))
			$pagestart=floor($position/$pagesize)*$pagesize;
	}
	
	// set the canonical url based on possible pagination
	
	$content['canonical']=as_path_html(as_q_request($question['postid'], $question['title']),
		($pagestart>0) ? array('start' => $pagestart) : null, as_opt('site_url'));
		
	// build the actual answer list

	$answerids=array_slice($answerids, $pagestart, $pagesize);
	
	foreach ($answerids as $answerid) {
		$answer=$answers[$answerid];
		
		if (!(($formtype=='a_edit') && ($formpostid==$answerid))) {
			$a_view=as_page_q_answer_view($question, $answer, $answer['isselected'], $usershtml, $formrequested);
			
		//	Prepare content for comments on this answer, plus add or edit comment forms
			
			if ((($formtype=='c_add') && ($formpostid==$answerid)) || ($answer['commentbutton'] && !$formrequested) ) { // ...to be added
				$a_view['c_form']=as_page_q_add_c_form($content, $question, $answer, 'c'.$answerid,
					$captchareason, @$cnewin[$answerid], @$cnewerrors[$answerid], $formtype=='c_add');

				if (($formtype=='c_add') && ($formpostid==$answerid)) {
					$jumptoanchor='c'.$answerid;
					$commentsall=$answerid;
				}

			} else if (($formtype=='c_edit') && (@$commentsfollows[$formpostid]['parentid']==$answerid)) { // ...being edited
				$a_view['c_form']=as_page_q_edit_c_form($content, 'c'.$formpostid, $commentsfollows[$formpostid],
					@$ceditin[$formpostid], @$cediterrors[$formpostid]);
					
				$jumptoanchor='c'.$formpostid;
				$commentsall=$answerid;
			}

			$a_view['c_list']=as_page_q_comment_follow_list($question, $answer, $commentsfollows,
				$commentsall==$answerid, $usershtml, $formrequested, $formpostid); // ...for viewing

		//	Add the answer to the list
				
			$content['a_list']['as'][]=$a_view;
		}
	}
	
	if ($question['basetype']=='Q') {
		$content['a_list']['title_tags']='id="a_list_title"';

		if ($countfortitle==1)
			$content['a_list']['title']=as_lang_html('question/1_answer_title');
		elseif ($countfortitle>0)
			$content['a_list']['title']=as_lang_html_sub('question/x_answers_title', $countfortitle);
		else
			$content['a_list']['title_tags'].=' style="display:none;" ';
	}

	if (!$formrequested)
		$content['page_links']=as_html_page_links(as_request(), $pagestart, $pagesize, $countforpages, as_opt('pages_prev_next'), array(), false, 'a_list_title');


//	Some generally useful stuff
	
	if (as_using_categories() && count($categories))
		$content['navigation']['cat']=as_category_navigation($categories, $question['categoryid']);

	if (isset($jumptoanchor))
		$content['script_onloads'][]=array(
			'as_scroll_page_to($("#"+'.as_js($jumptoanchor).').offset().top);'
		);
		
		
//	Determine whether this request should be counted for page view statistics
	
	if (
		as_opt('do_count_q_views') &&
		(!$formrequested) &&
		(!as_is_http_post()) &&
		as_is_human_probably() &&
		( (!$question['views']) || ( // if it has more than zero views
			( ($question['lastviewip']!=as_remote_ip_address()) || (!isset($question['lastviewip'])) ) && // then it must be different IP from last view
			( ($question['createip']!=as_remote_ip_address()) || (!isset($question['createip'])) ) && // and different IP from the creator
			( ($question['userid']!=$userid) || (!isset($question['userid'])) ) && // and different user from the creator
			( ($question['cookieid']!=$cookieid) || (!isset($question['cookieid'])) ) // and different cookieid from the creator
		) )
	)
		$content['inc_views_postid']=$questionid;

		
	return $content;


/*
	Omit PHP closing tag to help avoid accidental output
*/