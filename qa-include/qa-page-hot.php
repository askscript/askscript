<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-page-hot.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for page listing hot questions


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

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once QA_INCLUDE_DIR.'qa-db-selects.php';
	require_once QA_INCLUDE_DIR.'qa-app-q-list.php';
	

//	Get list of hottest questions, allow per-category if QA_ALLOW_UNINDEXED_QUERIES set in qa-config.php
	
	$categoryslugs=QA_ALLOW_UNINDEXED_QUERIES ? as_request_parts(1) : null;
	$countslugs=@count($categoryslugs);
	
	$start=as_get_start();
	$userid=as_get_logged_in_userid();
	
	list($questions, $categories, $categoryid)=as_db_select_with_pending(
		as_db_qs_selectspec($userid, 'hotness', $start, $categoryslugs, null, false, false, as_opt_if_loaded('page_size_hot_qs')),
		as_db_category_nav_selectspec($categoryslugs, false, false, true),
		$countslugs ? as_db_slugs_to_category_id_selectspec($categoryslugs) : null
	);

	if ($countslugs) {
		if (!isset($categoryid))
			return include QA_INCLUDE_DIR.'qa-page-not-found.php';
	
		$categorytitlehtml=as_html($categories[$categoryid]['title']);
		$sometitle=as_lang_html_sub('main/hot_qs_in_x', $categorytitlehtml);
		$nonetitle=as_lang_html_sub('main/no_questions_in_x', $categorytitlehtml);

	} else {
		$sometitle=as_lang_html('main/hot_qs_title');
		$nonetitle=as_lang_html('main/no_questions_found');
	}
	

//	Prepare and return content for theme

	return as_q_list_page_content(
		$questions, // questions
		as_opt('page_size_hot_qs'), // questions per page
		$start, // start offset
		$countslugs ? $categories[$categoryid]['qcount'] : as_opt('cache_qcount'), // total count
		$sometitle, // title if some questions
		$nonetitle, // title if no questions
		QA_ALLOW_UNINDEXED_QUERIES ? $categories : null, // categories for navigation
		$categoryid, // selected category id
		true, // show question counts in category navigation
		QA_ALLOW_UNINDEXED_QUERIES ? 'hot/' : null, // prefix for links in category navigation (null if no navigation)
		as_opt('feed_for_hot') ? 'hot' : null, // prefix for RSS feed paths (null to hide)
		as_html_suggest_ask() // suggest what to do next
	);
	

/*
	Omit PHP closing tag to help avoid accidental output
*/