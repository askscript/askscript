<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-app-q-list.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for most question listing pages, plus custom pages and plugin pages


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

	
	function as_q_list_page_content($questions, $pagesize, $start, $count, $sometitle, $nonetitle,
		$navcategories, $categoryid, $categoryqcount, $categorypathprefix, $feedpathprefix, $suggest,
		$pagelinkparams=null, $categoryparams=null, $dummy=null)
/*
	Returns the $as_content structure for a question list page showing $questions retrieved from the
	database. If $pagesize is not null, it sets the max number of questions to display. If $count is
	not null, pagination is determined by $start and $count. The page title is $sometitle unless
	there are no questions shown, in which case it's $nonetitle. $navcategories should contain the
	categories retrived from the database using as_db_category_nav_selectspec(...) for $categoryid,
	which is the current category shown. If $categorypathprefix is set, category navigation will be
	shown, with per-category question counts if $categoryqcount is true. The nav links will have the
	prefix $categorypathprefix and possible extra $categoryparams. If $feedpathprefix is set, the
	page has an RSS feed whose URL uses that prefix. If there are no links to other pages, $suggest
	is used to suggest what the user should do. The $pagelinkparams are passed through to
	as_html_page_links(...) which creates links for page 2, 3, etc..
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-app-format.php';
		require_once AS_INCLUDE_DIR.'as-app-updates.php';
	
		$userid=as_get_logged_in_userid();
		
		
	//	Chop down to size, get user information for display

		if (isset($pagesize))
			$questions=array_slice($questions, 0, $pagesize);
	
		$usershtml=as_userids_handles_html(as_any_get_userids_handles($questions));


	//	Prepare content for theme
		
		$as_content=as_content_prepare(true, array_keys(as_category_path($navcategories, $categoryid)));
	
		$as_content['q_list']['form']=array(
			'tags' => 'method="post" action="'.as_self_html().'"',
			
			'hidden' => array(
				'code' => as_get_form_security_code('vote'),
			),
		);
		
		$as_content['q_list']['qs']=array();
		
		if (count($questions)) {
			$as_content['title']=$sometitle;
		
			$defaults=as_post_html_defaults('Q');
			if (isset($categorypathprefix))
				$defaults['categorypathprefix']=$categorypathprefix;
				
			foreach ($questions as $question)
				$as_content['q_list']['qs'][]=as_any_to_q_html_fields($question, $userid, as_cookie_get(),
					$usershtml, null, as_post_html_options($question, $defaults));

		} else
			$as_content['title']=$nonetitle;
		
		if (isset($userid) && isset($categoryid)) {
			$favoritemap=as_get_favorite_non_qs_map();
			$categoryisfavorite=@$favoritemap['category'][$navcategories[$categoryid]['backpath']] ? true : false;
			
			$as_content['favorite']=as_favorite_form(AS_ENTITY_CATEGORY, $categoryid, $categoryisfavorite,
				as_lang_sub($categoryisfavorite ? 'main/remove_x_favorites' : 'main/add_category_x_favorites', $navcategories[$categoryid]['title']));
		}
			
		if (isset($count) && isset($pagesize))
			$as_content['page_links']=as_html_page_links(as_request(), $start, $pagesize, $count, as_opt('pages_prev_next'), $pagelinkparams);
		
		if (empty($as_content['page_links']))
			$as_content['suggest_next']=$suggest;
			
		if (as_using_categories() && count($navcategories) && isset($categorypathprefix))
			$as_content['navigation']['cat']=as_category_navigation($navcategories, $categoryid, $categorypathprefix, $categoryqcount, $categoryparams);
		
		if (isset($feedpathprefix) && (as_opt('feed_per_category') || !isset($categoryid)) )
			$as_content['feed']=array(
				'url' => as_path_html(as_feed_request($feedpathprefix.(isset($categoryid) ? ('/'.as_category_path_request($navcategories, $categoryid)) : ''))),
				'label' => strip_tags($sometitle),
			);
			
		return $as_content;
	}
	
	
	function as_qs_sub_navigation($sort, $categoryslugs)
/*
	Return the sub navigation structure common to question listing pages
*/
	{
		$request='questions';

		if (isset($categoryslugs))
			foreach ($categoryslugs as $slug)
				$request.='/'.$slug;

		$navigation=array(
			'recent' => array(
				'label' => as_lang('main/nav_most_recent'),
				'url' => as_path_html($request),
			),
			
			'hot' => array(
				'label' => as_lang('main/nav_hot'),
				'url' => as_path_html($request, array('sort' => 'hot')),
			),
			
			'votes' => array(
				'label' => as_lang('main/nav_most_votes'),
				'url' => as_path_html($request, array('sort' => 'votes')),
			),

			'answers' => array(
				'label' => as_lang('main/nav_most_answers'),
				'url' => as_path_html($request, array('sort' => 'answers')),
			),

			'views' => array(
				'label' => as_lang('main/nav_most_views'),
				'url' => as_path_html($request, array('sort' => 'views')),
			),
		);
		
		if (isset($navigation[$sort]))
			$navigation[$sort]['selected']=true;
		else
			$navigation['recent']['selected']=true;
		
		if (!as_opt('do_count_q_views'))
			unset($navigation['views']);
		
		return $navigation;
	}
	
	
	function as_unanswered_sub_navigation($by, $categoryslugs)
/*
	Return the sub navigation structure common to unanswered pages
*/
	{
		$request='unanswered';

		if (isset($categoryslugs))
			foreach ($categoryslugs as $slug)
				$request.='/'.$slug;
		
		$navigation=array(
			'by-answers' => array(
				'label' => as_lang('main/nav_no_answer'),
				'url' => as_path_html($request),
			),
			
			'by-selected' => array(
				'label' => as_lang('main/nav_no_selected_answer'),
				'url' => as_path_html($request, array('by' => 'selected')),
			),
			
			'by-upvotes' => array(
				'label' => as_lang('main/nav_no_upvoted_answer'),
				'url' => as_path_html($request, array('by' => 'upvotes')),
			),
		);
		
		if (isset($navigation['by-'.$by]))
			$navigation['by-'.$by]['selected']=true;
		else
			$navigation['by-answers']['selected']=true;
			
		if (!as_opt('voting_on_as'))
			unset($navigation['by-upvotes']);

		return $navigation;
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/