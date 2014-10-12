<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-comments.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for page listing recent comments on questions


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

	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';
	require_once AS_INCLUDE_DIR.'as-app-q-list.php';
	
	$categoryslugs=as_request_parts(1);
	$countslugs=count($categoryslugs);
	$userid=as_get_logged_in_userid();


//	Get list of comments with related questions, plus category information

	list($questions, $categories, $categoryid)=as_db_select_with_pending(
		as_db_recent_c_qs_selectspec($userid, 0, $categoryslugs),
		as_db_category_nav_selectspec($categoryslugs, false, false, true),
		$countslugs ? as_db_slugs_to_category_id_selectspec($categoryslugs) : null
	);
	
	if ($countslugs) {
		if (!isset($categoryid))
			return include AS_INCLUDE_DIR.'as-page-not-found.php';
	
		$categorytitlehtml=as_html($categories[$categoryid]['title']);
		$sometitle=as_lang_html_sub('main/recent_cs_in_x', $categorytitlehtml);
		$nonetitle=as_lang_html_sub('main/no_comments_in_x', $categorytitlehtml);

	} else {
		$sometitle=as_lang_html('main/recent_cs_title');
		$nonetitle=as_lang_html('main/no_comments_found');
	}

	
//	Prepare and return content for theme

	return as_q_list_page_content(
		as_any_sort_and_dedupe($questions), // questions
		as_opt('page_size_activity'), // questions per page
		0, // start offset
		null, // total count (null to hide page links)
		$sometitle, // title if some questions
		$nonetitle, // title if no questions
		$categories, // categories for navigation
		$categoryid, // selected category id
		false, // show question counts in category navigation
		'comments/', // prefix for links in category navigation
		as_opt('feed_for_activity') ? 'comments' : null, // prefix for RSS feed paths (null to hide)
		as_html_suggest_qs_tags(as_using_tags(), as_category_path_request($categories, $categoryid)) // suggest what to do next
	);


/*
	Omit PHP closing tag to help avoid accidental output
*/