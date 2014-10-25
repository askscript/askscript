<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-updates.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for page listing recent updates for a user


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
	

//	Check that we're logged in
	
	$userid=as_get_logged_in_userid();
	
	if (!isset($userid))
		as_redirect('login');
		

//	Find out which updates to show

	$forfavorites=as_get('show')!='content';
	$forcontent=as_get('show')!='favorites';


//	Get lists of recent updates for this user

	$questions=as_db_select_with_pending(
		as_db_user_updates_selectspec($userid, $forfavorites, $forcontent)
	);
	
	if ($forfavorites) {
		if ($forcontent) {
			$sometitle=as_lang_html('misc/recent_updates_title');
			$nonetitle=as_lang_html('misc/no_recent_updates');

		} else {
			$sometitle=as_lang_html('misc/recent_updates_favorites');
			$nonetitle=as_lang_html('misc/no_updates_favorites');
		}
	
	} else {
		$sometitle=as_lang_html('misc/recent_updates_content');
		$nonetitle=as_lang_html('misc/no_updates_content');
	}

	
//	Prepare and return content for theme

	$content=as_q_list_page_content(
		as_any_sort_and_dedupe($questions),
		null, // questions per page
		0, // start offset
		null, // total count (null to hide page links)
		$sometitle, // title if some questions
		$nonetitle, // title if no questions
		null, // categories for navigation
		null, // selected category id
		null, // show question counts in category navigation
		null, // prefix for links in category navigation
		null, // prefix for RSS feed paths (null to hide)
		$forfavorites ? strtr(as_lang_html('misc/suggest_update_favorites'), array(
			'^1' => '<a href="'.as_path_html('favorites').'">',
			'^2' => '</a>',
		)) : null // suggest what to do next
	);
	
	$content['navigation']['sub']=array(
		'all' => array(
			'label' => as_lang_html('misc/nav_all_my_updates'),
			'url' => as_path_html('updates'),
			'selected' => $forfavorites && $forcontent,
		),
		
		'favorites' => array(
			'label' => as_lang_html('misc/nav_my_favorites'),
			'url' => as_path_html('updates', array('show' => 'favorites')),
			'selected' => $forfavorites && !$forcontent,
		),
		
		'myposts' => array(
			'label' => as_lang_html('misc/nav_my_content'),
			'url' => as_path_html('updates', array('show' => 'content')),
			'selected' => $forcontent && !$forfavorites,
		),
	);
	
	
	return $content;


/*
	Omit PHP closing tag to help avoid accidental output
*/