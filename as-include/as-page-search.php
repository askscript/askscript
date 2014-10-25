<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-search.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for search page


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
	require_once AS_INCLUDE_DIR.'as-app-options.php';
	require_once AS_INCLUDE_DIR.'as-app-search.php';


//	Perform the search if appropriate

	if (strlen(as_get('q'))) {
	
	//	Pull in input parameters
	
		$inquery=trim(as_get('q'));
		$userid=as_get_logged_in_userid();
		$start=as_get_start();
		
		$display=as_opt_if_loaded('page_size_search');
		$count=2*(isset($display) ? $display : AS_DB_RETRIEVE_QS_AS)+1;
			// get enough results to be able to give some idea of how many pages of search results there are
		
	//	Perform the search using appropriate module

		$results=as_get_search_results($inquery, $start, $count, $userid, false, false);
		
	//	Count and truncate results
		
		$pagesize=as_opt('page_size_search');
		$gotcount=count($results);
		$results=array_slice($results, 0, $pagesize);
		
	//	Retrieve extra information on users	
		
		$fullquestions=array();
		
		foreach ($results as $result)
			if (isset($result['question']))
				$fullquestions[]=$result['question'];
				
		$usershtml=as_userids_handles_html($fullquestions);
		
	//	Report the search event
		
		as_report_event('search', $userid, as_get_logged_in_handle(), as_cookie_get(), array(
			'query' => $inquery,
			'start' => $start,
		));
	}


//	Prepare content for theme

	$content=as_content_prepare(true);

	if (strlen(as_get('q'))) {
		$content['search']['value']=as_html($inquery);
	
		if (count($results))
			$content['title']=as_lang_html_sub('main/results_for_x', as_html($inquery));
		else
			$content['title']=as_lang_html_sub('main/no_results_for_x', as_html($inquery));
			
		$content['q_list']['form']=array(
			'tags' => 'method="post" action="'.as_self_html().'"',

			'hidden' => array(
				'code' => as_get_form_security_code('vote'),
			),
		);
		
		$content['q_list']['qs']=array();
		
		$qdefaults=as_post_html_defaults('Q');
		
		foreach ($results as $result)
			if (!isset($result['question'])) { // if we have any non-question results, display with less statistics
				$qdefaults['voteview']=false;
				$qdefaults['answersview']=false;
				$qdefaults['viewsview']=false;
				break;
			}
		
		foreach ($results as $result) {
			if (isset($result['question']))
				$fields=as_post_html_fields($result['question'], $userid, as_cookie_get(),
					$usershtml, null, as_post_html_options($result['question'], $qdefaults));
			
			elseif (isset($result['url']))
				$fields=array(
					'what' => as_html($result['url']),
					'meta_order' => as_lang_html('main/meta_order'),
				);

			else
				continue; // nothing to show here
			
			if (isset($qdefaults['blockwordspreg']))
				$result['title']=as_block_words_replace($result['title'], $qdefaults['blockwordspreg']);
				
			$fields['title']=as_html($result['title']);
			$fields['url']=as_html($result['url']);
			
			$content['q_list']['qs'][]=$fields;
		}

		$content['page_links']=as_html_page_links(as_request(), $start, $pagesize, $start+$gotcount,
			as_opt('pages_prev_next'), array('q' => $inquery), $gotcount>=$count);
		
		if (as_opt('feed_for_search'))
			$content['feed']=array(
				'url' => as_path_html(as_feed_request('search/'.$inquery)),
				'label' => as_lang_html_sub('main/results_for_x', as_html($inquery)),
			);

		if (empty($content['page_links']))
			$content['suggest_next']=as_html_suggest_qs_tags(as_using_tags());

	} else
		$content['error']=as_lang_html('main/search_explanation');
	

		
	return $content;


/*
	Omit PHP closing tag to help avoid accidental output
*/