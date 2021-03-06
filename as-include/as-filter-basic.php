<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-filter-basic.php
	Version: See define()s at top of as-include/as-base.php
	Description: Basic module for validating form inputs


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

	require_once AS_INCLUDE_DIR.'as-db-maxima.php';
	require_once AS_INCLUDE_DIR.'as-util-string.php';

	
	

	class as_filter_basic {
	
		function filter_email(&$email, $olduser)
		{
			if (!strlen($email))
				return as_lang('users/email_required');
			
			if (!as_email_validate($email))
				return as_lang('users/email_invalid');
			
			if (as_strlen($email)>AS_DB_MAX_EMAIL_LENGTH)
				return as_lang_sub('main/max_length_x', AS_DB_MAX_EMAIL_LENGTH);
		}

		
		function filter_handle(&$handle, $olduser)
		{
			if (!strlen($handle))
				return as_lang('users/handle_empty');
	
			if (preg_match('/[\\@\\+\\/]/', $handle))
				return as_lang_sub('users/handle_has_bad', '@ + /');
			
			if (as_strlen($handle)>AS_DB_MAX_HANDLE_LENGTH)
				return as_lang_sub('main/max_length_x', AS_DB_MAX_HANDLE_LENGTH);
		}
		

		function filter_question(&$question, &$errors, $oldquestion)
		{
			$this->validate_length($errors, 'title', @$question['title'], as_opt('min_len_q_title'),
				max(as_opt('min_len_q_title'), min(as_opt('max_len_q_title'), AS_DB_MAX_TITLE_LENGTH)));
			
			$this->validate_length($errors, 'content', @$question['content'], 0, AS_DB_MAX_CONTENT_LENGTH); // for storage
			
			$this->validate_length($errors, 'content', @$question['text'], as_opt('min_len_q_content'), null); // for display
			
			if (isset($question['tags'])) {
				$counttags=count($question['tags']);
				$mintags=min(as_opt('min_num_q_tags'), as_opt('max_num_q_tags'));

				if ($counttags<$mintags)
					$errors['tags']=as_lang_sub('question/min_tags_x', $mintags);
				elseif ($counttags>as_opt('max_num_q_tags'))
					$errors['tags']=as_lang_sub('question/max_tags_x', as_opt('max_num_q_tags'));
				else
					$this->validate_length($errors, 'tags', as_tags_to_tagstring($question['tags']), 0, AS_DB_MAX_TAGS_LENGTH); // for storage
			}
			
			$this->validate_post_email($errors, $question);
		}

		
		function filter_answer(&$answer, &$errors, $question, $oldanswer)
		{
			$this->validate_length($errors, 'content', @$answer['content'], 0, AS_DB_MAX_CONTENT_LENGTH); // for storage
			$this->validate_length($errors, 'content', @$answer['text'], as_opt('min_len_a_content'), null); // for display
			$this->validate_post_email($errors, $answer);
		}

		
		function filter_comment(&$comment, &$errors, $question, $parent, $oldcomment)
		{
			$this->validate_length($errors, 'content', @$comment['content'], 0, AS_DB_MAX_CONTENT_LENGTH); // for storage
			$this->validate_length($errors, 'content', @$comment['text'], as_opt('min_len_c_content'), null); // for display
			$this->validate_post_email($errors, $comment);
		}

		
		function filter_profile(&$profile, &$errors, $user, $oldprofile)
		{
			foreach ($profile as $field => $value)
				$this->validate_length($errors, $field, $value, 0, AS_DB_MAX_PROFILE_CONTENT_LENGTH);
		}


	//	The definitions below are not part of a standard filter module, but just used within this one

		function validate_length(&$errors, $field, $input, $minlength, $maxlength)
	/*
		Add textual element $field to $errors if length of $input is not between $minlength and $maxlength
	*/
		{
			if (isset($input)) {
				$length=as_strlen($input);
				
				if ($length < $minlength)
					$errors[$field]=($minlength==1) ? as_lang('main/field_required') : as_lang_sub('main/min_length_x', $minlength);
				elseif (isset($maxlength) && ($length > $maxlength))
					$errors[$field]=as_lang_sub('main/max_length_x', $maxlength);
			}
		}

		
		function validate_post_email(&$errors, $post)
		{
			if (@$post['notify'] && strlen(@$post['email'])) {
				$error=$this->filter_email($post['email'], null);
				if (isset($error))
					$errors['email']=$error;
			}
		}
		
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/