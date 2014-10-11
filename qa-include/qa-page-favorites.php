<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-page-favorites.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for page listing user's favorites


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
	

//	Check that we're logged in
	
	$userid=as_get_logged_in_userid();

	if (!isset($userid))
		as_redirect('login');
		

//	Get lists of favorites for this user

	list($questions, $users, $tags, $categories)=as_db_select_with_pending(
		as_db_user_favorite_qs_selectspec($userid),
		QA_FINAL_EXTERNAL_USERS ? null : as_db_user_favorite_users_selectspec($userid),
		as_db_user_favorite_tags_selectspec($userid),
		as_db_user_favorite_categories_selectspec($userid)
	);
	
	$usershtml=as_userids_handles_html(QA_FINAL_EXTERNAL_USERS ? $questions : array_merge($questions, $users));

	
//	Prepare and return content for theme

	$as_content=as_content_prepare(true);

	$as_content['title']=as_lang_html('misc/my_favorites_title');
	

//	Favorite questions

	$as_content['q_list']=array(
		'title' => count($questions) ? as_lang_html('main/nav_qs') : as_lang_html('misc/no_favorite_qs'),
		
		'qs' => array(),
	);
	
	if (count($questions)) {
		$as_content['q_list']['form']=array(
			'tags' => 'method="post" action="'.as_self_html().'"',

			'hidden' => array(
				'code' => as_get_form_security_code('vote'),
			),
		);
		
		$defaults=as_post_html_defaults('Q');
			
		foreach ($questions as $question)
			$as_content['q_list']['qs'][]=as_post_html_fields($question, $userid, as_cookie_get(),
				$usershtml, null, as_post_html_options($question, $defaults));
	}
	
	
//	Favorite users

	if (!QA_FINAL_EXTERNAL_USERS) {
		$as_content['ranking_users']=array(
			'title' => count($users) ? as_lang_html('main/nav_users') : as_lang_html('misc/no_favorite_users'),
			'items' => array(),
			'rows' => ceil(count($users)/as_opt('columns_users')),
			'type' => 'users'
		);
		
		foreach ($users as $user)
			$as_content['ranking_users']['items'][]=array(
				'label' => as_get_user_avatar_html($user['flags'], $user['email'], $user['handle'],
					$user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], as_opt('avatar_users_size'), true).' '.$usershtml[$user['userid']],
				'score' => as_html(number_format($user['points'])),
			);
	}
	

//	Favorite tags

	if (as_using_tags()) {
		$as_content['ranking_tags']=array(
			'title' => count($tags) ? as_lang_html('main/nav_tags') : as_lang_html('misc/no_favorite_tags'),
			'items' => array(),
			'rows' => ceil(count($tags)/as_opt('columns_tags')),
			'type' => 'tags'
		);
		
		foreach ($tags as $tag)
			$as_content['ranking_tags']['items'][]=array(
				'label' => as_tag_html($tag['word'], false, true),
				'count' => number_format($tag['tagcount']),
			);
	}
	
	
//	Favorite categories

	if (as_using_categories()) {
		$as_content['nav_list_categories']=array(
			'title' => count($categories) ? as_lang_html('main/nav_categories') : as_lang_html('misc/no_favorite_categories'),
			'nav' => array(),
			'type' => 'browse-cat',
		);
		
		foreach ($categories as $category)
			$as_content['nav_list_categories']['nav'][$category['categoryid']]=array(
				'label' => as_html($category['title']),
				'state' => 'open',
				'favorited' => true,
				'note' => ' - <a href="'.as_path_html('questions/'.implode('/', array_reverse(explode('/', $category['backpath'])))).'">'.
					( ($category['qcount']==1)
						? as_lang_html_sub('main/1_question', '1', '1')
						: as_lang_html_sub('main/x_questions', number_format($category['qcount']))
					).'</a>'.
					(strlen($category['content']) ? as_html(' - '.$category['content']) : ''),
			);
	}


//	Sub navigation for account pages and suggestion
	
	$as_content['suggest_next']=as_lang_html_sub('misc/suggest_favorites_add', '<span class="qa-favorite-image">&nbsp;</span>');
	
	$as_content['navigation']['sub']=as_user_sub_navigation(as_get_logged_in_handle(), 'favorites', true);
	
	
	return $as_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/