<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-page-default.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for home page, Q&A listing page, custom pages and plugin pages


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
	require_once QA_INCLUDE_DIR.'qa-app-format.php';


//	Determine whether path begins with qa or not (question and answer listing can be accessed either way)

	$requestparts=explode('/', as_request());
	$explicitqa=(strtolower($requestparts[0])=='qa');
	
	if ($explicitqa)
		$slugs=array_slice($requestparts, 1);
	elseif (strlen($requestparts[0]))
		$slugs=$requestparts;
	else
		$slugs=array();
	
	$countslugs=count($slugs);
		
	
//	Get list of questions, other bits of information that might be useful
	
	$userid=as_get_logged_in_userid();
	
	list($questions1, $questions2, $categories, $categoryid, $custompage)=as_db_select_with_pending(
		as_db_qs_selectspec($userid, 'created', 0, $slugs, null, false, false, as_opt_if_loaded('page_size_activity')),
		as_db_recent_a_qs_selectspec($userid, 0, $slugs),
		as_db_category_nav_selectspec($slugs, false, false, true),
		$countslugs ? as_db_slugs_to_category_id_selectspec($slugs) : null,
		(($countslugs==1) && !$explicitqa) ? as_db_page_full_selectspec($slugs[0], false) : null
	);


//	First, if this matches a custom page, return immediately with that page's content
	
	if ( isset($custompage) && !($custompage['flags']&QA_PAGE_FLAGS_EXTERNAL) ) {
		as_set_template('custom-'.$custompage['pageid']);

		$as_content=as_content_prepare();
		
		$level=as_get_logged_in_level();

		if ( (!as_permit_value_error($custompage['permit'], $userid, $level, as_get_logged_in_flags())) || !isset($custompage['permit']) ) {
			$as_content['title']=as_html($custompage['heading']);
			$as_content['custom']=$custompage['content'];
			
			if ($level>=QA_USER_LEVEL_ADMIN) {
				$as_content['navigation']['sub']=array(
					'admin/pages' => array(
						'label' => as_lang('admin/edit_custom_page'),
						'url' => as_path_html('admin/pages', array('edit' => $custompage['pageid'])),
					),
				);
			}
		
		} else
			$as_content['error']=as_lang_html('users/no_permission');
		
		return $as_content;
	}


//	Then, see if we should redirect because the 'qa' page is the same as the home page

	if ($explicitqa && (!as_is_http_post()) && !as_has_custom_home())
		as_redirect(as_category_path_request($categories, $categoryid), $_GET);
		

//	Then, if there's a slug that matches no category, check page modules provided by plugins

	if ( (!$explicitqa) && $countslugs && !isset($categoryid) ) {
		$pagemodules=as_load_modules_with('page', 'match_request');
		$request=as_request();
		
		foreach ($pagemodules as $pagemodule)
			if ($pagemodule->match_request($request)) {
				as_set_template('plugin');
				return $pagemodule->process_request($request);
			}
	}
	
	
//	Then, check whether we are showing a custom home page

	if ( (!$explicitqa) && (!$countslugs) && as_opt('show_custom_home') ) {
		as_set_template('custom');
		$as_content=as_content_prepare();
		$as_content['title']=as_html(as_opt('custom_home_heading'));
		if (as_opt('show_home_description'))
			$as_content['description']=as_html(as_opt('home_description'));
		$as_content['custom']=as_opt('custom_home_content');
		return $as_content;
	}


//	If we got this far, it's a good old-fashioned Q&A listing page
	
	require_once QA_INCLUDE_DIR.'qa-app-q-list.php';

	as_set_template('qa');
	$questions=as_any_sort_and_dedupe(array_merge($questions1, $questions2));
	$pagesize=as_opt('page_size_home');
	
	if ($countslugs) {
		if (!isset($categoryid))
			return include QA_INCLUDE_DIR.'qa-page-not-found.php';

		$categorytitlehtml=as_html($categories[$categoryid]['title']);
		$sometitle=as_lang_html_sub('main/recent_qs_as_in_x', $categorytitlehtml);
		$nonetitle=as_lang_html_sub('main/no_questions_in_x', $categorytitlehtml);

	} else {
		$sometitle=as_lang_html('main/recent_qs_as_title');
		$nonetitle=as_lang_html('main/no_questions_found');
	}
	
	
//	Prepare and return content for theme for Q&A listing page

	$as_content=as_q_list_page_content(
		$questions, // questions
		$pagesize, // questions per page
		0, // start offset
		null, // total count (null to hide page links)
		$sometitle, // title if some questions
		$nonetitle, // title if no questions
		$categories, // categories for navigation
		$categoryid, // selected category id
		true, // show question counts in category navigation
		$explicitqa ? 'qa/' : '', // prefix for links in category navigation
		as_opt('feed_for_qa') ? 'qa' : null, // prefix for RSS feed paths (null to hide)
		(count($questions)<$pagesize) // suggest what to do next
			? as_html_suggest_ask($categoryid)
			: as_html_suggest_qs_tags(as_using_tags(), as_category_path_request($categories, $categoryid)),
		null, // page link params
		null // category nav params
	);
	
	if ( (!$explicitqa) && (!$countslugs) && as_opt('show_home_description') )
		$as_content['description']=as_html(as_opt('home_description'));

	
	return $as_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/