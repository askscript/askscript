<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-page.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Routing and utility functions for page requests


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

	require_once QA_INCLUDE_DIR.'qa-app-cookies.php';
	require_once QA_INCLUDE_DIR.'qa-app-format.php';
	require_once QA_INCLUDE_DIR.'qa-app-users.php';
	require_once QA_INCLUDE_DIR.'qa-app-options.php';
	require_once QA_INCLUDE_DIR.'qa-db-selects.php';


//	Functions which are called at the bottom of this file

	function as_page_db_fail_handler($type, $errno=null, $error=null, $query=null)
/*
	Standard database failure handler function which bring up the install/repair/upgrade page
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		$pass_failure_type=$type;
		$pass_failure_errno=$errno;
		$pass_failure_error=$error;
		$pass_failure_query=$query;
		
		require QA_INCLUDE_DIR.'qa-install.php';
		
		as_exit('error');
	}
	
	
	function as_page_queue_pending()
/*
	Queue any pending requests which are required independent of which page will be shown
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		as_preload_options();
		$loginuserid=as_get_logged_in_userid();
		
		if (isset($loginuserid)) {
			if (!QA_FINAL_EXTERNAL_USERS)
				as_db_queue_pending_select('loggedinuser', as_db_user_account_selectspec($loginuserid, true));
				
			as_db_queue_pending_select('notices', as_db_user_notices_selectspec($loginuserid));
			as_db_queue_pending_select('favoritenonqs', as_db_user_favorite_non_qs_selectspec($loginuserid));
			as_db_queue_pending_select('userlimits', as_db_user_limits_selectspec($loginuserid));
			as_db_queue_pending_select('userlevels', as_db_user_levels_selectspec($loginuserid, true));
		}
	
		as_db_queue_pending_select('iplimits', as_db_ip_limits_selectspec(as_remote_ip_address()));
		as_db_queue_pending_select('navpages', as_db_pages_selectspec(array('B', 'M', 'O', 'F')));
		as_db_queue_pending_select('widgets', as_db_widgets_selectspec());
	}


	function as_load_state()
/*
	Check the page state parameter and then remove it from the $_GET array
*/
	{
		global $as_state;
		
		$as_state=as_get('state');
		unset($_GET['state']); // to prevent being passed through on forms
	}
	
	
	function as_check_login_modules()
/*
	If no user is logged in, call through to the login modules to see if they want to log someone in
*/
	{
		if ((!QA_FINAL_EXTERNAL_USERS) && !as_is_logged_in()) {
			$loginmodules=as_load_modules_with('login', 'check_login');

			foreach ($loginmodules as $loginmodule) {
				$loginmodule->check_login();
				if (as_is_logged_in()) // stop and reload page if it worked
					as_redirect(as_request(), $_GET);
			}
		}
	}
	

	function as_check_page_clicks()
/*
	React to any of the common buttons on a page for voting, favorites and closing a notice
	If the user has Javascript on, these should come through Ajax rather than here.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_page_error_html;
		
		if (as_is_http_post())
			foreach ($_POST as $field => $value) {
				if (strpos($field, 'vote_')===0) { // voting...
					@list($dummy, $postid, $vote, $anchor)=explode('_', $field);
					
					if (isset($postid) && isset($vote)) {
						if (!as_check_form_security_code('vote', as_post_text('code')))
							$as_page_error_html=as_lang_html('misc/form_security_again');
						
						else {
							require_once QA_INCLUDE_DIR.'qa-app-votes.php';
							require_once QA_INCLUDE_DIR.'qa-db-selects.php';
							
							$userid=as_get_logged_in_userid();
							
							$post=as_db_select_with_pending(as_db_full_post_selectspec($userid, $postid));
							$as_page_error_html=as_vote_error_html($post, $vote, $userid, as_request());
		
							if (!$as_page_error_html) {
								as_vote_set($post, $userid, as_get_logged_in_handle(), as_cookie_get(), $vote);
								as_redirect(as_request(), $_GET, null, null, $anchor);
							}
							break;
						}
					}
				
				} elseif (strpos($field, 'favorite_')===0) { // favorites...
					@list($dummy, $entitytype, $entityid, $favorite)=explode('_', $field);
					
					if (isset($entitytype) && isset($entityid) && isset($favorite)) {
						if (!as_check_form_security_code('favorite-'.$entitytype.'-'.$entityid, as_post_text('code')))
							$as_page_error_html=as_lang_html('misc/form_security_again');
						
						else {
							require_once QA_INCLUDE_DIR.'qa-app-favorites.php';
							
							as_user_favorite_set(as_get_logged_in_userid(), as_get_logged_in_handle(), as_cookie_get(), $entitytype, $entityid, $favorite);
							as_redirect(as_request(), $_GET);
						}
					}
					
				} elseif (strpos($field, 'notice_')===0) { // notices...
					@list($dummy, $noticeid)=explode('_', $field);
					
					if (isset($noticeid)) {
						if (!as_check_form_security_code('notice-'.$noticeid, as_post_text('code')))
							$as_page_error_html=as_lang_html('misc/form_security_again');
							
						else {
							if ($noticeid=='visitor')
								setcookie('as_noticed', 1, time()+86400*3650, '/', QA_COOKIE_DOMAIN);
							
							elseif ($noticeid=='welcome') {
								require_once QA_INCLUDE_DIR.'qa-db-users.php';
								as_db_user_set_flag(as_get_logged_in_userid(), QA_USER_FLAGS_WELCOME_NOTICE, false);
	
							} else {
								require_once QA_INCLUDE_DIR.'qa-db-notices.php';
								as_db_usernotice_delete(as_get_logged_in_userid(), $noticeid);
							}
	
							as_redirect(as_request(), $_GET);
						}
					}
				}
			}
	}
	

	function as_get_request_content()
/*
	Run the appropriate qa-page-*.php file for this request and return back the $as_content it passed 
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		$requestlower=strtolower(as_request());
		$requestparts=as_request_parts();
		$firstlower=strtolower($requestparts[0]);
		$routing=as_page_routing();
		
		if (isset($routing[$requestlower])) {
			as_set_template($firstlower);
			$as_content=require QA_INCLUDE_DIR.$routing[$requestlower];
	
		} elseif (isset($routing[$firstlower.'/'])) {
			as_set_template($firstlower);
			$as_content=require QA_INCLUDE_DIR.$routing[$firstlower.'/'];
			
		} elseif (is_numeric($requestparts[0])) {
			as_set_template('question');
			$as_content=require QA_INCLUDE_DIR.'qa-page-question.php';
	
		} else {
			as_set_template(strlen($firstlower) ? $firstlower : 'qa'); // will be changed later
			$as_content=require QA_INCLUDE_DIR.'qa-page-default.php'; // handles many other pages, including custom pages and page modules
		}
	
		if ($firstlower=='admin') {
			$_COOKIE['as_admin_last']=$requestlower; // for navigation tab now...
			setcookie('as_admin_last', $_COOKIE['as_admin_last'], 0, '/', QA_COOKIE_DOMAIN); // ...and in future
		}
		
		as_set_form_security_key();

		return $as_content;
	}

	
	function as_output_content($as_content)
/*
	Output the $as_content via the theme class after doing some pre-processing, mainly relating to Javascript
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_template;
		
		$requestlower=strtolower(as_request());
		
	//	Set appropriate selected flags for navigation (not done in as_content_prepare() since it also applies to sub-navigation)
		
		foreach ($as_content['navigation'] as $navtype => $navigation)
			if (is_array($navigation) && ($navtype!='cat'))
				foreach ($navigation as $navprefix => $navlink)
					if (substr($requestlower.'$', 0, strlen($navprefix)) == $navprefix)
						$as_content['navigation'][$navtype][$navprefix]['selected']=true;
	
	//	Slide down notifications
	
		if (!empty($as_content['notices']))
			foreach ($as_content['notices'] as $notice) {
				$as_content['script_onloads'][]=array(
					"as_reveal(document.getElementById(".as_js($notice['id'])."), 'notice');",
				);
			}
	
	//	Handle maintenance mode
	
		if (as_opt('site_maintenance') && ($requestlower!='login')) {
			if (as_get_logged_in_level()>=QA_USER_LEVEL_ADMIN) {
				if (!isset($as_content['error']))
					$as_content['error']=strtr(as_lang_html('admin/maintenance_admin_only'), array(
						'^1' => '<a href="'.as_path_html('admin/general').'">',
						'^2' => '</a>',
					));
	
			} else {
				$as_content=as_content_prepare();
				$as_content['error']=as_lang_html('misc/site_in_maintenance');
			}
		}
	
	//	Handle new users who must confirm their email now, or must be approved before continuing
	
		$userid=as_get_logged_in_userid();
		if (isset($userid) && ($requestlower!='confirm') && ($requestlower!='account')) {
			$flags=as_get_logged_in_flags();
			
			if ( ($flags & QA_USER_FLAGS_MUST_CONFIRM) && (!($flags & QA_USER_FLAGS_EMAIL_CONFIRMED)) && as_opt('confirm_user_emails') ) {
				$as_content=as_content_prepare();
				$as_content['title']=as_lang_html('users/confirm_title');
				$as_content['error']=strtr(as_lang_html('users/confirm_required'), array(
					'^1' => '<a href="'.as_path_html('confirm').'">',
					'^2' => '</a>',
				));
			
			} elseif ( ($flags & QA_USER_FLAGS_MUST_APPROVE) && (as_get_logged_in_level()<QA_USER_LEVEL_APPROVED) && as_opt('moderate_users') ) {
				$as_content=as_content_prepare();
				$as_content['title']=as_lang_html('users/approve_title');
				$as_content['error']=strtr(as_lang_html('users/approve_required'), array(
					'^1' => '<a href="'.as_path_html('account').'">',
					'^2' => '</a>',
				));
			}
		}
	
	//	Combine various Javascript elements in $as_content into single array for theme layer
	
		$script=array('<script type="text/javascript">');
		
		if (isset($as_content['script_var']))
			foreach ($as_content['script_var'] as $var => $value)
				$script[]='var '.$var.'='.as_js($value).';';
				
		if (isset($as_content['script_lines']))
			foreach ($as_content['script_lines'] as $scriptlines) {
				$script[]='';
				$script=array_merge($script, $scriptlines);
			}
			
		if (isset($as_content['focusid']))
			$as_content['script_onloads'][]=array(
				"var elem=document.getElementById(".as_js($as_content['focusid']).");",
				"if (elem) {",
				"\telem.select();",
				"\telem.focus();",
				"}",
			);
			
		if (isset($as_content['script_onloads'])) {
			array_push($script,
				'',
				'var as_oldonload=window.onload;',
				'window.onload=function() {',
				"\tif (typeof as_oldonload=='function')",
				"\t\tas_oldonload();"
			);
			
			foreach ($as_content['script_onloads'] as $scriptonload) {
				$script[]="\t";
				
				foreach ((array)$scriptonload as $scriptline)
					$script[]="\t".$scriptline;
			}
	
			$script[]='};';
		}
		
		$script[]='</script>';
		
		if (isset($as_content['script_rel'])) {
			$uniquerel=array_unique($as_content['script_rel']); // remove any duplicates
			foreach ($uniquerel as $script_rel)
				$script[]='<script src="'.as_html(as_path_to_root().$script_rel).'" type="text/javascript"></script>';
		}
		
		if (isset($as_content['script_src'])) {
			$uniquesrc=array_unique($as_content['script_src']); // remove any duplicates
			foreach ($uniquesrc as $script_src)
				$script[]='<script src="'.as_html($script_src).'" type="text/javascript"></script>';
		}
	
		$as_content['script']=$script;

	//	Load the appropriate theme class and output the page
	
		$themeclass=as_load_theme_class(as_get_site_theme(), (substr($as_template, 0, 7)=='custom-') ? 'custom' : $as_template, $as_content, as_request());
	
		header('Content-type: '.$as_content['content_type']);
		
		$themeclass->doctype();
		$themeclass->html();
		$themeclass->finish();
	}


	function as_do_content_stats($as_content)
/*
	Update any statistics required by the fields in $as_content, and return true if something was done
*/
	{
		if (isset($as_content['inc_views_postid'])) {
			require_once QA_INCLUDE_DIR.'qa-db-hotness.php';
			as_db_hotness_update($as_content['inc_views_postid'], null, true);
			return true;
		}
		
		return false;
	}


//	Other functions which might be called from anywhere

	function as_page_routing()
/*
	Return an array of the default Q2A requests and which qa-page-*.php file implements them
	If the key of an element ends in /, it should be used for any request with that key as its prefix
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return array(
			'account' => 'qa-page-account.php',
			'activity/' => 'qa-page-activity.php',
			'admin/' => 'qa-page-admin-default.php',
			'admin/approve' => 'qa-page-admin-approve.php',
			'admin/categories' => 'qa-page-admin-categories.php',
			'admin/flagged' => 'qa-page-admin-flagged.php',
			'admin/hidden' => 'qa-page-admin-hidden.php',
			'admin/layoutwidgets' => 'qa-page-admin-widgets.php',
			'admin/moderate' => 'qa-page-admin-moderate.php',
			'admin/pages' => 'qa-page-admin-pages.php',
			'admin/plugins' => 'qa-page-admin-plugins.php',
			'admin/points' => 'qa-page-admin-points.php',
			'admin/recalc' => 'qa-page-admin-recalc.php',
			'admin/stats' => 'qa-page-admin-stats.php',
			'admin/userfields' => 'qa-page-admin-userfields.php',
			'admin/usertitles' => 'qa-page-admin-usertitles.php',
			'answers/' => 'qa-page-answers.php',
			'ask' => 'qa-page-ask.php',
			'categories/' => 'qa-page-categories.php',
			'comments/' => 'qa-page-comments.php',
			'confirm' => 'qa-page-confirm.php',
			'favorites' => 'qa-page-favorites.php',
			'feedback' => 'qa-page-feedback.php',
			'forgot' => 'qa-page-forgot.php',
			'hot/' => 'qa-page-hot.php',
			'ip/' => 'qa-page-ip.php',
			'login' => 'qa-page-login.php',
			'logout' => 'qa-page-logout.php',
			'message/' => 'qa-page-message.php',
			'questions/' => 'qa-page-questions.php',
			'register' => 'qa-page-register.php',
			'reset' => 'qa-page-reset.php',
			'search' => 'qa-page-search.php',
			'tag/' => 'qa-page-tag.php',
			'tags' => 'qa-page-tags.php',
			'unanswered/' => 'qa-page-unanswered.php',
			'unsubscribe' => 'qa-page-unsubscribe.php',
			'updates' => 'qa-page-updates.php',
			'user/' => 'qa-page-user.php',
			'users' => 'qa-page-users.php',
			'users/blocked' => 'qa-page-users-blocked.php',
			'users/special' => 'qa-page-users-special.php',
		);
	}
	
	
	function as_set_template($template)
/*
	Sets the template which should be passed to the theme class, telling it which type of page it's displaying
*/
	{
		global $as_template;
		$as_template=$template;
	}
	
	
	function as_content_prepare($voting=false, $categoryids=null)
/*
	Start preparing theme content in global $as_content variable, with or without $voting support,
	in the context of the categories in $categoryids (if not null)
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_template, $as_page_error_html;
		
		if (QA_DEBUG_PERFORMANCE)
			as_usage_mark('control');
		
		$request=as_request();
		$requestlower=as_request();
		$navpages=as_db_get_pending_result('navpages');
		$widgets=as_db_get_pending_result('widgets');
		
		if (isset($categoryids) && !is_array($categoryids)) // accept old-style parameter
			$categoryids=array($categoryids);
			
		$lastcategoryid=count($categoryids) ? end($categoryids) : null;
		
		$as_content=array(
			'content_type' => 'text/html; charset=utf-8',
			
			'site_title' => as_html(as_opt('site_title')),
			
			'head_lines' => array(),
			
			'navigation' => array(
				'user' => array(),

				'main' => array(),
				
				'footer' => array(
					'feedback' => array(
						'url' => as_path_html('feedback'),
						'label' => as_lang_html('main/nav_feedback'),
					),
				),
	
			),
			
			'sidebar' => as_opt('show_custom_sidebar') ? as_opt('custom_sidebar') : null,
			
			'sidepanel' => as_opt('show_custom_sidepanel') ? as_opt('custom_sidepanel') : null,
			
			'widgets' => array(),
		);

		if (as_opt('show_custom_in_head'))
			$as_content['head_lines'][]=as_opt('custom_in_head');
		
		if (as_opt('show_custom_header'))
			$as_content['body_header']=as_opt('custom_header');
	
		if (as_opt('show_custom_footer'))
			$as_content['body_footer']=as_opt('custom_footer');

		if (isset($categoryids))
			$as_content['categoryids']=$categoryids;
		
		foreach ($navpages as $page)
			if ($page['nav']=='B')
				as_navigation_add_page($as_content['navigation']['main'], $page);
		
		if (as_opt('nav_home') && as_opt('show_custom_home'))
			$as_content['navigation']['main']['$']=array(
				'url' => as_path_html(''),
				'label' => as_lang_html('main/nav_home'),
			);

		if (as_opt('nav_activity'))
			$as_content['navigation']['main']['activity']=array(
				'url' => as_path_html('activity'),
				'label' => as_lang_html('main/nav_activity'),
			);
			
		$hascustomhome=as_has_custom_home();
		
		if (as_opt($hascustomhome ? 'nav_as_not_home' : 'nav_as_is_home'))
			$as_content['navigation']['main'][$hascustomhome ? 'qa' : '$']=array(
				'url' => as_path_html($hascustomhome ? 'qa' : ''),
				'label' => as_lang_html('main/nav_qa'),
			);
			
		if (as_opt('nav_questions'))
			$as_content['navigation']['main']['questions']=array(
				'url' => as_path_html('questions'),
				'label' => as_lang_html('main/nav_qs'),
			);

		if (as_opt('nav_hot'))
			$as_content['navigation']['main']['hot']=array(
				'url' => as_path_html('hot'),
				'label' => as_lang_html('main/nav_hot'),
			);

		if (as_opt('nav_unanswered'))
			$as_content['navigation']['main']['unanswered']=array(
				'url' => as_path_html('unanswered'),
				'label' => as_lang_html('main/nav_unanswered'),
			);
			
		if (as_using_tags() && as_opt('nav_tags'))
			$as_content['navigation']['main']['tag']=array(
				'url' => as_path_html('tags'),
				'label' => as_lang_html('main/nav_tags'),
			);
			
		if (as_using_categories() && as_opt('nav_categories'))
			$as_content['navigation']['main']['categories']=array(
				'url' => as_path_html('categories'),
				'label' => as_lang_html('main/nav_categories'),
			);

		if (as_opt('nav_users'))
			$as_content['navigation']['main']['user']=array(
				'url' => as_path_html('users'),
				'label' => as_lang_html('main/nav_users'),
			);
			
		// Only the 'level' permission error prevents the menu option being shown - others reported on qa-page-ask.php

		if (as_opt('nav_ask') && (as_user_maximum_permit_error('permit_post_q')!='level'))
			$as_content['navigation']['main']['ask']=array(
				'url' => as_path_html('ask', (as_using_categories() && strlen($lastcategoryid)) ? array('cat' => $lastcategoryid) : null),
				'label' => as_lang_html('main/nav_ask'),
			);
		
		
		if (
			(as_get_logged_in_level()>=QA_USER_LEVEL_ADMIN) ||
			(!as_user_maximum_permit_error('permit_moderate')) ||
			(!as_user_maximum_permit_error('permit_hide_show')) ||
			(!as_user_maximum_permit_error('permit_delete_hidden'))
		)
			$as_content['navigation']['main']['admin']=array(
				'url' => as_path_html('admin'),
				'label' => as_lang_html('main/nav_admin'),
			);

		
		$as_content['search']=array(
			'form_tags' => 'method="get" action="'.as_path_html('search').'"',
			'form_extra' => as_path_form_html('search'),
			'title' => as_lang_html('main/search_title'),
			'field_tags' => 'name="q"',
			'button_label' => as_lang_html('main/search_button'),
		);
		
		if (!as_opt('feedback_enabled'))
			unset($as_content['navigation']['footer']['feedback']);
			
		foreach ($navpages as $page)
			if ( ($page['nav']=='M') || ($page['nav']=='O') || ($page['nav']=='F') )
				as_navigation_add_page($as_content['navigation'][($page['nav']=='F') ? 'footer' : 'main'], $page);
				
		$regioncodes=array(
			'F' => 'full',
			'M' => 'main',
			'S' => 'side',
		);
		
		$placecodes=array(
			'T' => 'top',
			'H' => 'high',
			'L' => 'low',
			'B' => 'bottom',
		);
		
		foreach ($widgets as $widget)
			if (is_numeric(strpos(','.$widget['tags'].',', ','.$as_template.',')) || is_numeric(strpos(','.$widget['tags'].',', ',all,'))) { // see if it has been selected for display on this template
				$region=@$regioncodes[substr($widget['place'], 0, 1)];
				$place=@$placecodes[substr($widget['place'], 1, 2)];
				
				if (isset($region) && isset($place)) { // check region/place codes recognized
					$module=as_load_module('widget', $widget['title']);
					
					if (
						isset($module) &&
						method_exists($module, 'allow_template') &&
						$module->allow_template((substr($as_template, 0, 7)=='custom-') ? 'custom' : $as_template) &&
						method_exists($module, 'allow_region') &&
						$module->allow_region($region) &&
						method_exists($module, 'output_widget')
					)
						$as_content['widgets'][$region][$place][]=$module; // if module loaded and happy to be displayed here, tell theme about it
				}
			}
			
		$logoshow=as_opt('logo_show');
		$logourl=as_opt('logo_url');
		$logowidth=as_opt('logo_width');
		$logoheight=as_opt('logo_height');
		
		if ($logoshow)
			$as_content['logo']='<a href="'.as_path_html('').'" class="qa-logo-link" title="'.as_html(as_opt('site_title')).'">'.
				'<img src="'.as_html(is_numeric(strpos($logourl, '://')) ? $logourl : as_path_to_root().$logourl).'"'.
				($logowidth ? (' width="'.$logowidth.'"') : '').($logoheight ? (' height="'.$logoheight.'"') : '').
				' border="0" alt="'.as_html(as_opt('site_title')).'"/></a>';
		else
			$as_content['logo']='<a href="'.as_path_html('').'" class="qa-logo-link">'.as_html(as_opt('site_title')).'</a>';

		$topath=as_get('to'); // lets user switch between login and register without losing destination page

		$userlinks=as_get_login_links(as_path_to_root(), isset($topath) ? $topath : as_path($request, $_GET, ''));
		
		$as_content['navigation']['user']=array();
			
		if (as_is_logged_in()) {
			$as_content['loggedin']=as_lang_html_sub_split('main/logged_in_x', QA_FINAL_EXTERNAL_USERS
				? as_get_logged_in_user_html(as_get_logged_in_user_cache(), as_path_to_root(), false)
				: as_get_one_user_html(as_get_logged_in_handle(), false)
			);
			
			$as_content['navigation']['user']['updates']=array(
				'url' => as_path_html('updates'),
				'label' => as_lang_html('main/nav_updates'),
			);
				
			if (!empty($userlinks['logout']))
				$as_content['navigation']['user']['logout']=array(
					'url' => as_html(@$userlinks['logout']),
					'label' => as_lang_html('main/nav_logout'),
				);
			
			if (!QA_FINAL_EXTERNAL_USERS) {
				$source=as_get_logged_in_source();
				
				if (strlen($source)) {
					$loginmodules=as_load_modules_with('login', 'match_source');
					
					foreach ($loginmodules as $module)
						if ($module->match_source($source) && method_exists($module, 'logout_html')) {
							ob_start();
							$module->logout_html(as_path('logout', array(), as_opt('site_url')));
							$as_content['navigation']['user']['logout']=array('label' => ob_get_clean());
						}
				}
			}
			
			$notices=as_db_get_pending_result('notices');
			foreach ($notices as $notice)
				$as_content['notices'][]=as_notice_form($notice['noticeid'], as_viewer_html($notice['content'], $notice['format']), $notice);
			
		} else {
			require_once QA_INCLUDE_DIR.'qa-util-string.php';
			
			if (!QA_FINAL_EXTERNAL_USERS) {
				$loginmodules=as_load_modules_with('login', 'login_html');
				
				foreach ($loginmodules as $tryname => $module) {
					ob_start();
					$module->login_html(isset($topath) ? (as_opt('site_url').$topath) : as_path($request, $_GET, as_opt('site_url')), 'menu');
					$label=ob_get_clean();
	
					if (strlen($label))
						$as_content['navigation']['user'][implode('-', as_string_to_words($tryname))]=array('label' => $label);
				}
			}
			
			if (!empty($userlinks['login']))
				$as_content['navigation']['user']['login']=array(
					'url' => as_html(@$userlinks['login']),
					'label' => as_lang_html('main/nav_login'),
				);
				
			if (!empty($userlinks['register']))
				$as_content['navigation']['user']['register']=array(
					'url' => as_html(@$userlinks['register']),
					'label' => as_lang_html('main/nav_register'),
				);
		}

		if (QA_FINAL_EXTERNAL_USERS || !as_is_logged_in()) {
			if (as_opt('show_notice_visitor') && (!isset($topath)) && (!isset($_COOKIE['as_noticed'])))
				$as_content['notices'][]=as_notice_form('visitor', as_opt('notice_visitor'));

		} else {
			setcookie('as_noticed', 1, time()+86400*3650, '/', QA_COOKIE_DOMAIN); // don't show first-time notice if a user has logged in

			if (as_opt('show_notice_welcome') && (as_get_logged_in_flags() & QA_USER_FLAGS_WELCOME_NOTICE) )
				if ( ($requestlower!='confirm') && ($requestlower!='account') ) // let people finish registering in peace
					$as_content['notices'][]=as_notice_form('welcome', as_opt('notice_welcome'));
		}
		
		$as_content['script_rel']=array('qa-content/jquery-1.7.2.min.js');
		$as_content['script_rel'][]='qa-content/qa-page.js?'.QA_VERSION;
		
		if ($voting)
			$as_content['error']=@$as_page_error_html;
			
		$as_content['script_var']=array(
			'as_root' => as_path_to_root(),
			'as_request' => $request,
		);
		
		return $as_content;
	}


	function as_get_start()
/*
	Get the start parameter which should be used, as constrained by the setting in qa-config.php
*/
	{
		return min(max(0, (int)as_get('start')), QA_MAX_LIMIT_START);
	}
	
	
	function as_get_state()
/*
	Get the state parameter which should be used, as set earlier in as_load_state()
*/
	{
		global $as_state;
		return $as_state;
	}
	

//	Below are the steps that actually execute for this file - all the above are function definitions

	as_report_process_stage('init_page');
	as_db_connect('as_page_db_fail_handler');
		
	as_page_queue_pending();
	as_load_state();
	as_check_login_modules();
	
	if (QA_DEBUG_PERFORMANCE)
		as_usage_mark('setup');

	as_check_page_clicks();

	$as_content=as_get_request_content();
	
	if (is_array($as_content)) {
		if (QA_DEBUG_PERFORMANCE)
			as_usage_mark('view');

		as_output_content($as_content);

		if (QA_DEBUG_PERFORMANCE)
			as_usage_mark('theme');
			
		if (as_do_content_stats($as_content))
			if (QA_DEBUG_PERFORMANCE)
				as_usage_mark('stats');

		if (QA_DEBUG_PERFORMANCE)
			as_usage_output();
	}

	as_db_disconnect();


/*
	Omit PHP closing tag to help avoid accidental output
*/