<?php

class Menu {


}

function get_nav(){
	$navigation = array();
	if (as_opt($hascustomhome ? 'nav_as_not_home' : 'nav_as_is_home'))
		$navigation['main'][$hascustomhome ? 'as' : '$']=array(
			'url' => as_path_html($hascustomhome ? 'as' : ''),
			'label' => as_lang_html('main/nav_as'),
		);
		
	if (as_opt('nav_questions'))
		$content['navigation']['main']['questions']=array(
			'url' => as_path_html('questions'),
			'label' => as_lang_html('main/nav_qs'),
		);

	if (as_opt('nav_hot'))
		$content['navigation']['main']['hot']=array(
			'url' => as_path_html('hot'),
			'label' => as_lang_html('main/nav_hot'),
		);

	if (as_opt('nav_unanswered'))
		$content['navigation']['main']['unanswered']=array(
			'url' => as_path_html('unanswered'),
			'label' => as_lang_html('main/nav_unanswered'),
		);
		
	if (as_using_tags() && as_opt('nav_tags'))
		$content['navigation']['main']['tag']=array(
			'url' => as_path_html('tags'),
			'label' => as_lang_html('main/nav_tags'),
		);
		
	if (as_using_categories() && as_opt('nav_categories'))
		$content['navigation']['main']['categories']=array(
			'url' => as_path_html('categories'),
			'label' => as_lang_html('main/nav_categories'),
		);

	if (as_opt('nav_users'))
		$content['navigation']['main']['user']=array(
			'url' => as_path_html('users'),
			'label' => as_lang_html('main/nav_users'),
		);
}

function nav_menu ( $args = array() ){
	$menus = get_nav();
}