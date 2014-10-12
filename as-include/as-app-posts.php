<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-app-posts.php
	Version: See define()s at top of as-include/as-base.php
	Description: Higher-level functions to create and manipulate posts


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
	
	require_once AS_INCLUDE_DIR.'as-db.php';
	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';
	require_once AS_INCLUDE_DIR.'as-app-post-create.php';
	require_once AS_INCLUDE_DIR.'as-app-post-update.php';
	require_once AS_INCLUDE_DIR.'as-util-string.php';


	function as_post_create($type, $parentid, $title, $content, $format='', $categoryid=null, $tags=null, $userid=null, $notify=null, $email=null, $extravalue=null, $name=null)
/*
	Create a new post in the database, and return its postid.
	
	Set $type to 'Q' for a new question, 'A' for an answer, or 'C' for a comment. You can also use 'Q_QUEUED',
	'A_QUEUED' or 'C_QUEUED' to create a post which is queued for moderator approval. For questions, set $parentid to
	the postid of the answer to which the question is related, or null if (as in most cases) the question is not related
	to an answer. For answers, set $parentid to the postid of the question being answered. For comments, set $parentid
	to the postid of the question or answer to which the comment relates. The $content and $format parameters go
	together - if $format is '' then $content should be in plain UTF-8 text, and if $format is 'html' then $content
	should be in UTF-8 HTML. Other values of $format may be allowed if an appropriate viewer module is installed. The
	$title, $categoryid and $tags parameters are only relevant when creating a question - $tags can either be an array
	of tags, or a string of tags separated by commas. The new post will be assigned to $userid if it is not null,
	otherwise it will be by a non-user. If $notify is true then the author will be sent notifications relating to the
	post - either to $email if it is specified and valid, or to the current email address of $userid if $email is '@'.
	If you're creating a question, the $extravalue parameter will be set as the custom extra field, if not null. For all
	post types you can specify the $name of the post's author, which is relevant if the $userid is null.
*/
	{
		$handle=as_post_userid_to_handle($userid);
		$text=as_post_content_to_text($content, $format);

		switch ($type) {
			case 'Q':
			case 'Q_QUEUED':
				$followanswer=isset($parentid) ? as_post_get_full($parentid, 'A') : null;
				$tagstring=as_post_tags_to_tagstring($tags);
				$postid=as_question_create($followanswer, $userid, $handle, null, $title, $content, $format, $text, $tagstring,
					$notify, $email, $categoryid, $extravalue, $type=='Q_QUEUED', $name);
				break;
				
			case 'A':
			case 'A_QUEUED':
				$question=as_post_get_full($parentid, 'Q');
				$postid=as_answer_create($userid, $handle, null, $content, $format, $text, $notify, $email, $question, $type=='A_QUEUED', $name);
				break;
				
			case 'C':
			case 'C_QUEUED':
				$parent=as_post_get_full($parentid, 'QA');
				$commentsfollows=as_db_single_select(as_db_full_child_posts_selectspec(null, $parentid));
				$question=as_post_parent_to_question($parent);
				$postid=as_comment_create($userid, $handle, null, $content, $format, $text, $notify, $email, $question, $parent, $commentsfollows, $type=='C_QUEUED', $name);
				break;
				
			default:
				as_fatal_error('Post type not recognized: '.$type);
				break;
		}
		
		return $postid;
	}
	
	
	function as_post_set_content($postid, $title, $content, $format=null, $tags=null, $notify=null, $email=null, $byuserid=null, $extravalue=null, $name=null)
/*
	Change the data stored for post $postid based on any of the $title, $content, $format, $tags, $notify, $email,
	$extravalue and $name parameters passed which are not null. The meaning of these parameters is the same as for
	as_post_create() above. Pass the identify of the user making this change in $byuserid (or null for silent).
*/
	{
		$oldpost=as_post_get_full($postid, 'QAC');
		
		if (!isset($title))
			$title=$oldpost['title'];
		
		if (!isset($content))
			$content=$oldpost['content'];
			
		if (!isset($format))
			$format=$oldpost['format'];
			
		if (!isset($tags))
			$tags=as_tagstring_to_tags($oldpost['tags']);
			
		if (isset($notify) || isset($email))
			$setnotify=as_combine_notify_email($oldpost['userid'], isset($notify) ? $notify : isset($oldpost['notify']),
				isset($email) ? $email : $oldpost['notify']);
		else
			$setnotify=$oldpost['notify'];
	
		$byhandle=as_post_userid_to_handle($byuserid);
		$text=as_post_content_to_text($content, $format);
		
		switch ($oldpost['basetype']) {
			case 'Q':
				$tagstring=as_post_tags_to_tagstring($tags);
				as_question_set_content($oldpost, $title, $content, $format, $text, $tagstring, $setnotify, $byuserid, $byhandle, null, $extravalue, $name);
				break;
				
			case 'A':
				$question=as_post_get_full($oldpost['parentid'], 'Q');
				as_answer_set_content($oldpost, $content, $format, $text, $setnotify, $byuserid, $byhandle, null, $question, $name);
				break;
				
			case 'C':
				$parent=as_post_get_full($oldpost['parentid'], 'QA');
				$question=as_post_parent_to_question($parent);
				as_comment_set_content($oldpost, $content, $format, $text, $setnotify, $byuserid, $byhandle, null, $question, $parent, $name);
				break;
		}
	}

	
	function as_post_set_category($postid, $categoryid, $byuserid=null)
/*
	Change the category of $postid to $categoryid. The category of all related posts (shown together on the same
	question page) will also be changed. Pass the identify of the user making this change in $byuserid (or null for an
	anonymous change).
*/
	{
		$oldpost=as_post_get_full($postid, 'QAC');
		
		if ($oldpost['basetype']=='Q') {
			$byhandle=as_post_userid_to_handle($byuserid);
			$answers=as_post_get_question_answers($postid);
			$commentsfollows=as_post_get_question_commentsfollows($postid);
			$closepost=as_post_get_question_closepost($postid);
			as_question_set_category($oldpost, $categoryid, $byuserid, $byhandle, null, $answers, $commentsfollows, $closepost);

		} else
			as_post_set_category($oldpost['parentid'], $categoryid, $byuserid); // keep looking until we find the parent question
	}

	
	function as_post_set_selchildid($questionid, $answerid, $byuserid=null)
/*
	Set the selected best answer of $questionid to $answerid (or to none if $answerid is null). Pass the identify of the
	user in $byuserid (or null for an anonymous change).
*/
	{
		$oldquestion=as_post_get_full($questionid, 'Q');
		$byhandle=as_post_userid_to_handle($byuserid);
		$answers=as_post_get_question_answers($questionid);
		
		if (isset($answerid) && !isset($answers[$answerid]))
			as_fatal_error('Answer ID could not be found: '.$answerid);
		
		as_question_set_selchildid($byuserid, $byuserid, null, $oldquestion, $answerid, $answers);
	}

	
	function as_post_set_closed($questionid, $closed=true, $originalpostid=null, $note=null, $byuserid=null)
/*
	Closed $questionid if $closed is true, otherwise reopen it. If $closed is true, pass either the $originalpostid of
	the question that it is a duplicate of, or a $note to explain why it's closed. Pass the identify of the user in
	$byuserid (or null for an anonymous change).
*/
	{
		$oldquestion=as_post_get_full($questionid, 'Q');
		$oldclosepost=as_post_get_question_closepost($questionid);
		$byhandle=as_post_userid_to_handle($byuserid);
		
		if ($closed) {
			if (isset($originalpostid))
				as_question_close_duplicate($oldquestion, $oldclosepost, $originalpostid, $byuserid, $byhandle, null);
			elseif (isset($note))
				as_question_close_other($oldquestion, $oldclosepost, $note, $byuserid, $byhandle, null);
			else
				as_fatal_error('Question must be closed as a duplicate or with a note');
		
		} else
			as_question_close_clear($oldquestion, $oldclosepost, $byuserid, $byhandle, null);
	}
	
	
	function as_post_set_hidden($postid, $hidden=true, $byuserid=null)
/*
	Hide $postid if $hidden is true, otherwise show the post. Pass the identify of the user making this change in
	$byuserid (or null for a silent change). This function is included mainly for backwards compatibility.
*/
	{
		as_post_set_status($postid, $hidden ? AS_POST_STATUS_HIDDEN : AS_POST_STATUS_NORMAL, $byuserid);
	}
	
	
	function as_post_set_status($postid, $status, $byuserid=null)
/*
	Change the status of $postid to $status, which should be one of the AS_POST_STATUS_* constants defined in
	as-app-post-update.php. Pass the identify of the user making this change in $byuserid (or null for a silent change).
*/
	{
		$oldpost=as_post_get_full($postid, 'QAC');
		$byhandle=as_post_userid_to_handle($byuserid);
		
		switch ($oldpost['basetype']) {
			case 'Q':
				$answers=as_post_get_question_answers($postid);
				$commentsfollows=as_post_get_question_commentsfollows($postid);
				$closepost=as_post_get_question_closepost($postid);
				as_question_set_status($oldpost, $status, $byuserid, $byhandle, null, $answers, $commentsfollows, $closepost);
				break;
				
			case 'A':
				$question=as_post_get_full($oldpost['parentid'], 'Q');
				$commentsfollows=as_post_get_answer_commentsfollows($postid);
				as_answer_set_status($oldpost, $status, $byuserid, $byhandle, null, $question, $commentsfollows);
				break;
				
			case 'C':
				$parent=as_post_get_full($oldpost['parentid'], 'QA');
				$question=as_post_parent_to_question($parent);
				as_comment_set_status($oldpost, $status, $byuserid, $byhandle, null, $question, $parent);
				break;
		}
	}

	
	function as_post_set_created($postid, $created)
/*
	Set the created date of $postid to $created, which is a unix timestamp.
*/
	{
		$oldpost=as_post_get_full($postid);
		
		as_db_post_set_created($postid, $created);
		
		switch ($oldpost['basetype']) {
			case 'Q':
				as_db_hotness_update($postid);
				break;
				
			case 'A':
				as_db_hotness_update($oldpost['parentid']);
				break;
		}
	}
	
	
	function as_post_delete($postid)
/*
	Delete $postid from the database, hiding it first if appropriate.
*/
	{
		$oldpost=as_post_get_full($postid, 'QAC');
		
		if (!$oldpost['hidden']) {
			as_post_set_hidden($postid, true, null);
			$oldpost=as_post_get_full($postid, 'QAC');
		}
		
		switch ($oldpost['basetype']) {
			case 'Q':
				$answers=as_post_get_question_answers($postid);
				$commentsfollows=as_post_get_question_commentsfollows($postid);
				$closepost=as_post_get_question_closepost($postid);
				
				if (count($answers) || count($commentsfollows))
					as_fatal_error('Could not delete question ID due to dependents: '.$postid);
					
				as_question_delete($oldpost, null, null, null, $closepost);
				break;
				
			case 'A':
				$question=as_post_get_full($oldpost['parentid'], 'Q');
				$commentsfollows=as_post_get_answer_commentsfollows($postid);

				if (count($commentsfollows))
					as_fatal_error('Could not delete answer ID due to dependents: '.$postid);

				as_answer_delete($oldpost, $question, null, null, null);
				break;
				
			case 'C':
				$parent=as_post_get_full($oldpost['parentid'], 'QA');
				$question=as_post_parent_to_question($parent);
				as_comment_delete($oldpost, $question, $parent, null, null, null);
				break;
		}
	}


	function as_post_get_full($postid, $requiredbasetypes=null)
/*
	Return the full information from the database for $postid in an array.
*/
	{
		$post=as_db_single_select(as_db_full_post_selectspec(null, $postid));
			
		if (!is_array($post))
			as_fatal_error('Post ID could not be found: '.$postid);
		
		if (isset($requiredbasetypes) && !is_numeric(strpos($requiredbasetypes, $post['basetype'])))
			as_fatal_error('Post of wrong type: '.$post['basetype']);
		
		return $post;
	}

	
	function as_post_userid_to_handle($userid)
/*
	Return the handle corresponding to $userid, unless it is null in which case return null.
*/
	{
		if (isset($userid)) {
			if (AS_FINAL_EXTERNAL_USERS) {
				require_once AS_INCLUDE_DIR.'as-app-users.php';
				
				$handles=as_get_public_from_userids(array($userid));
				
				return @$handles[$userid];
			
			} else {
				$user=as_db_single_select(as_db_user_account_selectspec($userid, true));
				
				if (!is_array($user))
					as_fatal_error('User ID could not be found: '.$userid);

				return $user['handle'];
			}
		}
		
		return null;
	}


	function as_post_content_to_text($content, $format)
/*
	Return the textual rendition of $content in $format (used for indexing).
*/
	{
		$viewer=as_load_viewer($content, $format);
		
		if (!isset($viewer))
			as_fatal_error('Content could not be parsed in format: '.$format);
			
		return $viewer->get_text($content, $format, array());
	}

	
	function as_post_tags_to_tagstring($tags)
/*
	Return tagstring to store in the database based on $tags as an array or a comma-separated string.
*/
	{
		if (is_array($tags))
			$tags=implode(',', $tags);
		
		return as_tags_to_tagstring(array_unique(preg_split('/\s*,\s*/', as_strtolower(strtr($tags, '/', ' ')), -1, PREG_SPLIT_NO_EMPTY)));
	}

	
	function as_post_get_question_answers($questionid)
/*
	Return the full database records for all answers to question $questionid
*/
	{
		$answers=array();
		
		$childposts=as_db_single_select(as_db_full_child_posts_selectspec(null, $questionid));
		
		foreach ($childposts as $postid => $post)
			if ($post['basetype']=='A')
				$answers[$postid]=$post;
		
		return $answers;
	}

	
	function as_post_get_question_commentsfollows($questionid)
/*
	Return the full database records for all comments or follow-on questions for question $questionid or its answers
*/
	{
		$commentsfollows=array();
		
		list($childposts, $achildposts)=as_db_multi_select(array(
			as_db_full_child_posts_selectspec(null, $questionid),
			as_db_full_a_child_posts_selectspec(null, $questionid),
		));

		foreach ($childposts as $postid => $post)
			if ($post['basetype']=='C')
				$commentsfollows[$postid]=$post;
		
		foreach ($achildposts as $postid => $post)
			if ( ($post['basetype']=='Q') || ($post['basetype']=='C') )
				$commentsfollows[$postid]=$post;
		
		return $commentsfollows;
	}
	
	
	function as_post_get_question_closepost($questionid)
/*
	Return the full database record for the post which closed $questionid, if there is any
*/
	{
		return as_db_single_select(as_db_post_close_post_selectspec($questionid));
	}

	
	function as_post_get_answer_commentsfollows($answerid)
/*
	Return the full database records for all comments or follow-on questions for answer $answerid
*/
	{
		$commentsfollows=array();
		
		$childposts=as_db_single_select(as_db_full_child_posts_selectspec(null, $answerid));

		foreach ($childposts as $postid => $post)
			if ( ($post['basetype']=='Q') || ($post['basetype']=='C') )
				$commentsfollows[$postid]=$post;
				
		return $commentsfollows;
	}
	

	function as_post_parent_to_question($parent)
/*
	Return $parent if it's the database record for a question, otherwise return the database record for its parent
*/
	{
		if ($parent['basetype']=='Q')
			$question=$parent;
		else
			$question=as_post_get_full($parent['parentid'], 'Q');
		
		return $question;
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/