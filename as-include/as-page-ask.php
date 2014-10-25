<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-ask.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for ask a question page


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


	require_once AS_INCLUDE_DIR.'as-app-format.php';
	require_once AS_INCLUDE_DIR.'as-app-limits.php';
	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-util-sort.php';


//	Check whether this is a follow-on question and get some info we need from the database

	$in=array();
	
	$followpostid=as_get('follow');
	$in['categoryid']=as_clicked('doask') ? as_get_category_field_value('category') : as_get('cat');
	$userid=as_get_logged_in_userid();
	
	list($categories, $followanswer, $completetags)=as_db_select_with_pending(
		as_db_category_nav_selectspec($in['categoryid'], true),
		isset($followpostid) ? as_db_full_post_selectspec($userid, $followpostid) : null,
		as_db_popular_tags_selectspec(0, AS_DB_RETRIEVE_COMPLETE_TAGS)
	);
	
	if (!isset($categories[$in['categoryid']]))
		$in['categoryid']=null;
	
	if (@$followanswer['basetype']!='A')
		$followanswer=null;
		

//	Check for permission error

	$permiterror=as_user_maximum_permit_error('permit_post_q', AS_LIMIT_QUESTIONS);

	if ($permiterror) {
		$content=as_content_prepare();
		
		// The 'approve', 'login', 'confirm', 'limit', 'userblock', 'ipblock' permission errors are reported to the user here
		// The other option ('level') prevents the menu option being shown, in as_content_prepare(...)

		switch ($permiterror) {
			case 'login':
				$content['error']=as_insert_login_links(as_lang_html('question/ask_must_login'), as_request(), isset($followpostid) ? array('follow' => $followpostid) : null);
				break;
				
			case 'confirm':
				$content['error']=as_insert_login_links(as_lang_html('question/ask_must_confirm'), as_request(), isset($followpostid) ? array('follow' => $followpostid) : null);
				break;
				
			case 'limit':
				$content['error']=as_lang_html('question/ask_limit');
				break;
				
			case 'approve':
				$content['error']=as_lang_html('question/ask_must_be_approved');
				break;
				
			default:
				$content['error']=as_lang_html('users/no_permission');
				break;
		}
		
		return $content;
	}
	

//	Process input
	
	$captchareason=as_user_captcha_reason();
	
	$in['title']=as_post_text('title'); // allow title and tags to be posted by an external form
	$in['extra']=as_opt('extra_field_active') ? as_post_text('extra') : null;
	if (as_using_tags())
		$in['tags']=as_get_tags_field_value('tags');

	if (as_clicked('doask')) {
		require_once AS_INCLUDE_DIR.'as-app-post-create.php';
		require_once AS_INCLUDE_DIR.'as-util-string.php';
		
		$categoryids=array_keys(as_category_path($categories, @$in['categoryid']));
		$userlevel=as_user_level_for_categories($categoryids);
		
		$in['name']=as_post_text('name');
		$in['notify']=as_post_text('notify') ? true : false;
		$in['email']=as_post_text('email');
		$in['queued']=as_user_moderation_reason($userlevel) ? true : false;
			
		as_get_post_content('editor', 'content', $in['editor'], $in['content'], $in['format'], $in['text']);
		
		$errors=array();

		if (!as_check_form_security_code('ask', as_post_text('code')))
			$errors['page']=as_lang_html('misc/form_security_again');
		
		else {
			$filtermodules=as_load_modules_with('filter', 'filter_question');
			foreach ($filtermodules as $filtermodule) {
				$oldin=$in;
				$filtermodule->filter_question($in, $errors, null);
				as_update_post_text($in, $oldin);
			}
			
			if (as_using_categories() && count($categories) && (!as_opt('allow_no_category')) && !isset($in['categoryid']))
				$errors['categoryid']=as_lang_html('question/category_required'); // check this here because we need to know count($categories)
			elseif (as_user_permit_error('permit_post_q', null, $userlevel))
				$errors['categoryid']=as_lang_html('question/category_ask_not_allowed');
			
			if ($captchareason) {
				require_once 'as-app-captcha.php';
				as_captcha_validate_post($errors);
			}
			
			if (empty($errors)) {
				$cookieid=isset($userid) ? as_cookie_get() : as_cookie_get_create(); // create a new cookie if necessary
				
				$questionid=as_question_create($followanswer, $userid, as_get_logged_in_handle(), $cookieid,
					$in['title'], $in['content'], $in['format'], $in['text'], isset($in['tags']) ? as_tags_to_tagstring($in['tags']) : '',
					$in['notify'], $in['email'], $in['categoryid'], $in['extra'], $in['queued'], $in['name']);
				
				as_redirect(as_q_request($questionid, $in['title'])); // our work is done here
			}
		}
	}


//	Prepare content for theme

	$content=as_content_prepare(false, array_keys(as_category_path($categories, @$in['categoryid'])));
	
	$content['title']=as_lang_html(isset($followanswer) ? 'question/ask_follow_title' : 'question/ask_title');
	$content['error']=@$errors['page'];

	$editorname=isset($in['editor']) ? $in['editor'] : as_opt('editor_for_qs');
	$editor=as_load_editor(@$in['content'], @$in['format'], $editorname);

	$field=as_editor_load_field($editor, $content, @$in['content'], @$in['format'], 'content', 12, false);
	$field['label']=as_lang_html('question/q_content_label');
	$field['error']=as_html(@$errors['content']);
	
	$custom=as_opt('show_custom_ask') ? trim(as_opt('custom_ask')) : '';

	$content['form']=array(
		'tags' => 'name="ask" method="post" action="'.as_self_html().'"',
		
		'style' => 'tall',
		
		'fields' => array(
			'custom' => array(
				'type' => 'custom',
				'note' => $custom,
			),
			
			'title' => array(
				'label' => as_lang_html('question/q_title_label'),
				'tags' => 'name="title" id="title" autocomplete="off"',
				'value' => as_html(@$in['title']),
				'error' => as_html(@$errors['title']),
			),
			
			'similar' => array(
				'type' => 'custom',
				'html' => '<span id="similar"></span>',
			),
			
			'content' => $field,
		),
		
		'buttons' => array(
			'ask' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false); '.
					(method_exists($editor, 'update_script') ? $editor->update_script('content') : '').'"',
				'label' => as_lang_html('question/ask_button'),
			),
		),
		
		'hidden' => array(
			'editor' => as_html($editorname),
			'code' => as_get_form_security_code('ask'),
			'doask' => '1',
		),
	);
			
	if (!strlen($custom))
		unset($content['form']['fields']['custom']);

	if (as_opt('do_ask_check_qs') || as_opt('do_example_tags')) {
		$content['script_rel'][]='as-content/as-ask.js?'.AS_VERSION;
		$content['form']['fields']['title']['tags'].=' onchange="as_title_change(this.value);"';
		
		if (strlen(@$in['title']))
			$content['script_onloads'][]='as_title_change('.as_js($in['title']).');';
	}
	
	if (isset($followanswer)) {
		$viewer=as_load_viewer($followanswer['content'], $followanswer['format']);
		
		$field=array(
			'type' => 'static',
			'label' => as_lang_html('question/ask_follow_from_a'),
			'value' => $viewer->get_html($followanswer['content'], $followanswer['format'], array('blockwordspreg' => as_get_block_words_preg())),
		);
		
		as_array_insert($content['form']['fields'], 'title', array('follows' => $field));
	}
		
	if (as_using_categories() && count($categories)) {
		$field=array(
			'label' => as_lang_html('question/q_category_label'),
			'error' => as_html(@$errors['categoryid']),
		);
		
		as_set_up_category_field($content, $field, 'category', $categories, $in['categoryid'], true, as_opt('allow_no_sub_category'));
		
		if (!as_opt('allow_no_category')) // don't auto-select a category even though one is required
			$field['options']['']='';
			
		as_array_insert($content['form']['fields'], 'content', array('category' => $field));
	}
	
	if (as_opt('extra_field_active')) {
		$field=array(
			'label' => as_html(as_opt('extra_field_prompt')),
			'tags' => 'name="extra"',
			'value' => as_html(@$in['extra']),
			'error' => as_html(@$errors['extra']),
		);
		
		as_array_insert($content['form']['fields'], null, array('extra' => $field));
	}
	
	if (as_using_tags()) {
		$field=array(
			'error' => as_html(@$errors['tags']),
		);
		
		as_set_up_tag_field($content, $field, 'tags', isset($in['tags']) ? $in['tags'] : array(), array(),
			as_opt('do_complete_tags') ? array_keys($completetags) : array(), as_opt('page_size_ask_tags'));
	
		as_array_insert($content['form']['fields'], null, array('tags' => $field));
	}
	
	if (!isset($userid))
		as_set_up_name_field($content, $content['form']['fields'], @$in['name']);
	
	as_set_up_notify_fields($content, $content['form']['fields'], 'Q', as_get_logged_in_email(),
		isset($in['notify']) ? $in['notify'] : as_opt('notify_users_default'), @$in['email'], @$errors['email']);
	
	if ($captchareason) {
		require_once 'as-app-captcha.php';
		as_set_up_captcha_field($content, $content['form']['fields'], @$errors, as_captcha_reason_note($captchareason));
	}
			
	$content['focusid']='title';

	
	return $content;


/*
	Omit PHP closing tag to help avoid accidental output
*/