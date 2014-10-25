<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page.php
	Version: See define()s at top of as-include/as-base.php
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

	if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once AS_INCLUDE_DIR.'as-app-cookies.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';
	require_once AS_INCLUDE_DIR.'as-app-users.php';
	require_once AS_INCLUDE_DIR.'as-app-options.php';
	require_once AS_INCLUDE_DIR.'as-db-selects.php';


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
		
		require AS_INCLUDE_DIR.'as-install.php';
		
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
			if (!AS_FINAL_EXTERNAL_USERS)
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
		if ((!AS_FINAL_EXTERNAL_USERS) && !as_is_logged_in()) {
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
							require_once AS_INCLUDE_DIR.'as-app-votes.php';
							require_once AS_INCLUDE_DIR.'as-db-selects.php';
							
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
							require_once AS_INCLUDE_DIR.'as-app-favorites.php';
							
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
								setcookie('as_noticed', 1, time()+86400*3650, '/', AS_COOKIE_DOMAIN);
							
							elseif ($noticeid=='welcome') {
								require_once AS_INCLUDE_DIR.'as-db-users.php';
								as_db_user_set_flag(as_get_logged_in_userid(), AS_USER_FLAGS_WELCOME_NOTICE, false);
	
							} else {
								require_once AS_INCLUDE_DIR.'as-db-notices.php';
								as_db_usernotice_delete(as_get_logged_in_userid(), $noticeid);
							}
	
							as_redirect(as_request(), $_GET);
						}
					}
				}
			}
	}
	


//	Other functions which might be called from anywhere

	function as_page_routing(){
		/*
			Return an array of the default Q2A requests and which as-page-*.php file implements them
			If the key of an element ends in /, it should be used for any request with that key as its prefix
		*/
		return array(
			'account' => 'as-page-account.php',
			'activity/' => 'as-page-activity.php',
			'admin/' => 'as-page-admin-default.php',
			'admin/approve' => 'as-page-admin-approve.php',
			'admin/categories' => 'as-page-admin-categories.php',
			'admin/flagged' => 'as-page-admin-flagged.php',
			'admin/hidden' => 'as-page-admin-hidden.php',
			'admin/layoutwidgets' => 'as-page-admin-widgets.php',
			'admin/moderate' => 'as-page-admin-moderate.php',
			'admin/pages' => 'as-page-admin-pages.php',
			'admin/plugins' => 'as-page-admin-plugins.php',
			'admin/points' => 'as-page-admin-points.php',
			'admin/recalc' => 'as-page-admin-recalc.php',
			'admin/stats' => 'as-page-admin-stats.php',
			'admin/userfields' => 'as-page-admin-userfields.php',
			'admin/usertitles' => 'as-page-admin-usertitles.php',
			'answers/' => 'as-page-answers.php',
			'ask' => 'as-page-ask.php',
			'categories/' => 'as-page-categories.php',
			'comments/' => 'as-page-comments.php',
			'confirm' => 'as-page-confirm.php',
			'favorites' => 'as-page-favorites.php',
			'feedback' => 'as-page-feedback.php',
			'forgot' => 'as-page-forgot.php',
			'hot/' => 'as-page-hot.php',
			'ip/' => 'as-page-ip.php',
			'login' => 'as-page-login.php',
			'logout' => 'as-page-logout.php',
			'message/' => 'as-page-message.php',
			'questions/' => 'as-page-questions.php',
			'register' => 'as-page-register.php',
			'reset' => 'as-page-reset.php',
			'search' => 'as-page-search.php',
			'tag/' => 'as-page-tag.php',
			'tags' => 'as-page-tags.php',
			'unanswered/' => 'as-page-unanswered.php',
			'unsubscribe' => 'as-page-unsubscribe.php',
			'updates' => 'as-page-updates.php',
			'user/' => 'as-page-user.php',
			'users' => 'as-page-users.php',
			'users/blocked' => 'as-page-users-blocked.php',
			'users/special' => 'as-page-users-special.php',
		);
	}
	
	
	function as_set_template($template)
/*
	Sets the template which should be passed to the theme class, telling it which type of page it's displaying
*/
	{
		global $template;
		$template=$template;
	}
	
	
	
	function as_content_prepare($voting=false, $categoryids=null)
/*
	Start preparing theme content in global $content variable, with or without $voting support,
	in the context of the categories in $categoryids (if not null)
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $template, $as_page_error_html;
		
		if (AS_DEBUG_PERFORMANCE)
			as_usage_mark('control');
		
		$request=as_request();
		$requestlower=as_request();
		$navpages=as_db_get_pending_result('navpages');
		$widgets=as_db_get_pending_result('widgets');
		
		if (isset($categoryids) && !is_array($categoryids)) // accept old-style parameter
			$categoryids=array($categoryids);
			
		$lastcategoryid=count($categoryids) ? end($categoryids) : null;
		
		$content=array(
			
			'site_title' => as_html(as_opt('site_title')),
			
			'navigation' => array(
				'user' => array(),

				'main' => array(),
				
			),

		);

		if (isset($categoryids))
			$content['categoryids']=$categoryids;
		
		foreach ($navpages as $page)
			if ($page['nav']=='B')
				as_navigation_add_page($content['navigation']['main'], $page);
		
		if (as_opt('nav_home') && as_opt('show_custom_home'))
			$content['navigation']['main']['$']=array(
				'url' => as_path_html(''),
				'label' => as_lang_html('main/nav_home'),
			);

		if (as_opt('nav_activity'))
			$content['navigation']['main']['activity']=array(
				'url' => as_path_html('activity'),
				'label' => as_lang_html('main/nav_activity'),
			);
			
		$hascustomhome=as_has_custom_home();
		
		
			
		// Only the 'level' permission error prevents the menu option being shown - others reported on as-page-ask.php

		if (as_opt('nav_ask') && (as_user_maximum_permit_error('permit_post_q')!='level'))
			$content['navigation']['main']['ask']=array(
				'url' => as_path_html('ask', (as_using_categories() && strlen($lastcategoryid)) ? array('cat' => $lastcategoryid) : null),
				'label' => as_lang_html('main/nav_ask'),
			);
		
		
		if (
			(as_get_logged_in_level()>=AS_USER_LEVEL_ADMIN) ||
			(!as_user_maximum_permit_error('permit_moderate')) ||
			(!as_user_maximum_permit_error('permit_hide_show')) ||
			(!as_user_maximum_permit_error('permit_delete_hidden'))
		)
			$content['navigation']['main']['admin']=array(
				'url' => as_path_html('admin'),
				'label' => as_lang_html('main/nav_admin'),
			);

		
		$content['search']=array(
			'form_tags' => 'method="get" action="'.as_path_html('search').'"',
			'form_extra' => as_path_form_html('search'),
			'title' => as_lang_html('main/search_title'),
			'field_tags' => 'name="q"',
			'button_label' => as_lang_html('main/search_button'),
		);
		
		if (!as_opt('feedback_enabled'))
			unset($content['navigation']['footer']['feedback']);
			
		foreach ($navpages as $page)
			if ( ($page['nav']=='M') || ($page['nav']=='O') || ($page['nav']=='F') )
				as_navigation_add_page($content['navigation'][($page['nav']=='F') ? 'footer' : 'main'], $page);
				
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
			if (is_numeric(strpos(','.$widget['tags'].',', ','.$template.',')) || is_numeric(strpos(','.$widget['tags'].',', ',all,'))) { // see if it has been selected for display on this template
				$region=@$regioncodes[substr($widget['place'], 0, 1)];
				$place=@$placecodes[substr($widget['place'], 1, 2)];
				
				if (isset($region) && isset($place)) { // check region/place codes recognized
					$module=as_load_module('widget', $widget['title']);
					
					if (
						isset($module) &&
						method_exists($module, 'allow_template') &&
						$module->allow_template((substr($template, 0, 7)=='custom-') ? 'custom' : $template) &&
						method_exists($module, 'allow_region') &&
						$module->allow_region($region) &&
						method_exists($module, 'output_widget')
					)
						$content['widgets'][$region][$place][]=$module; // if module loaded and happy to be displayed here, tell theme about it
				}
			}
			
		$logoshow=as_opt('logo_show');
		$logourl=as_opt('logo_url');
		$logowidth=as_opt('logo_width');
		$logoheight=as_opt('logo_height');
		
		if ($logoshow)
			$content['logo']='<a href="'.as_path_html('').'" class="as-logo-link" title="'.as_html(as_opt('site_title')).'">'.
				'<img src="'.as_html(is_numeric(strpos($logourl, '://')) ? $logourl : as_path_to_root().$logourl).'"'.
				($logowidth ? (' width="'.$logowidth.'"') : '').($logoheight ? (' height="'.$logoheight.'"') : '').
				' border="0" alt="'.as_html(as_opt('site_title')).'"/></a>';
		else
			$content['logo']='<a href="'.as_path_html('').'" class="as-logo-link">'.as_html(as_opt('site_title')).'</a>';

		$topath=as_get('to'); // lets user switch between login and register without losing destination page

		$userlinks=as_get_login_links(as_path_to_root(), isset($topath) ? $topath : as_path($request, $_GET, ''));
		
		$content['navigation']['user']=array();
			
		if (as_is_logged_in()) {
			$content['loggedin']=as_lang_html_sub_split('main/logged_in_x', AS_FINAL_EXTERNAL_USERS
				? as_get_logged_in_user_html(as_get_logged_in_user_cache(), as_path_to_root(), false)
				: as_get_one_user_html(as_get_logged_in_handle(), false)
			);
			
			$content['navigation']['user']['updates']=array(
				'url' => as_path_html('updates'),
				'label' => as_lang_html('main/nav_updates'),
			);
				
			if (!empty($userlinks['logout']))
				$content['navigation']['user']['logout']=array(
					'url' => as_html(@$userlinks['logout']),
					'label' => as_lang_html('main/nav_logout'),
				);
			
			if (!AS_FINAL_EXTERNAL_USERS) {
				$source=as_get_logged_in_source();
				
				if (strlen($source)) {
					$loginmodules=as_load_modules_with('login', 'match_source');
					
					foreach ($loginmodules as $module)
						if ($module->match_source($source) && method_exists($module, 'logout_html')) {
							ob_start();
							$module->logout_html(as_path('logout', array(), as_opt('site_url')));
							$content['navigation']['user']['logout']=array('label' => ob_get_clean());
						}
				}
			}
			
			$notices=as_db_get_pending_result('notices');
			foreach ($notices as $notice)
				$content['notices'][]=as_notice_form($notice['noticeid'], as_viewer_html($notice['content'], $notice['format']), $notice);
			
		} else {
			require_once AS_INCLUDE_DIR.'as-util-string.php';
			
			if (!AS_FINAL_EXTERNAL_USERS) {
				$loginmodules=as_load_modules_with('login', 'login_html');
				
				foreach ($loginmodules as $tryname => $module) {
					ob_start();
					$module->login_html(isset($topath) ? (as_opt('site_url').$topath) : as_path($request, $_GET, as_opt('site_url')), 'menu');
					$label=ob_get_clean();
	
					if (strlen($label))
						$content['navigation']['user'][implode('-', as_string_to_words($tryname))]=array('label' => $label);
				}
			}
			
			if (!empty($userlinks['login']))
				$content['navigation']['user']['login']=array(
					'url' => as_html(@$userlinks['login']),
					'label' => as_lang_html('main/nav_login'),
				);
				
			if (!empty($userlinks['register']))
				$content['navigation']['user']['register']=array(
					'url' => as_html(@$userlinks['register']),
					'label' => as_lang_html('main/nav_register'),
				);
		}

		if (AS_FINAL_EXTERNAL_USERS || !as_is_logged_in()) {
			if (as_opt('show_notice_visitor') && (!isset($topath)) && (!isset($_COOKIE['as_noticed'])))
				$content['notices'][]=as_notice_form('visitor', as_opt('notice_visitor'));

		} else {
			setcookie('as_noticed', 1, time()+86400*3650, '/', AS_COOKIE_DOMAIN); // don't show first-time notice if a user has logged in

			if (as_opt('show_notice_welcome') && (as_get_logged_in_flags() & AS_USER_FLAGS_WELCOME_NOTICE) )
				if ( ($requestlower!='confirm') && ($requestlower!='account') ) // let people finish registering in peace
					$content['notices'][]=as_notice_form('welcome', as_opt('notice_welcome'));
		}
		
		if ($voting)
			$content['error']=@$as_page_error_html;
			
		$content['script_var']=array(
			'as_root' => as_path_to_root(),
			'as_request' => $request,
		);
		
		return $content;
	}


	function as_get_start()
/*
	Get the start parameter which should be used, as constrained by the setting in as-config.php
*/
	{
		return min(max(0, (int)as_get('start')), AS_MAX_LIMIT_START);
	}
	
	
	function as_get_state()
/*
	Get the state parameter which should be used, as set earlier in as_load_state()
*/
	{
		global $as_state;
		return $as_state;
	}
	
	function as_get_request_content()
/*
	Run the appropriate as-page-*.php file for this request and return back the $content it passed 
*/
	{
		
		$requestlower=strtolower(as_request());
		$requestparts=as_request_parts();
		$firstlower=strtolower($requestparts[0]);
		$routing=as_page_routing();
		
		if (isset($routing[$requestlower])) {
			as_set_template($firstlower);
			$content=require AS_INCLUDE_DIR.$routing[$requestlower];
	
		} elseif (isset($routing[$firstlower.'/'])) {
			as_set_template($firstlower);
			$content=require AS_INCLUDE_DIR.$routing[$firstlower.'/'];
			
		} elseif (is_numeric($requestparts[0])) {
			as_set_template('question');
			$content=require AS_INCLUDE_DIR.'as-page-question.php';
	
		} else {
			as_set_template(strlen($firstlower) ? $firstlower : 'as'); // will be changed later
			$content=require AS_INCLUDE_DIR.'as-page-default.php'; // handles many other pages, including custom pages and page modules
		}
	
		if ($firstlower=='admin') {
			$_COOKIE['as_admin_last']=$requestlower; // for navigation tab now...
			setcookie('as_admin_last', $_COOKIE['as_admin_last'], 0, '/', AS_COOKIE_DOMAIN); // ...and in future
		}
		
		as_set_form_security_key();

		return $content;
	}

	
	function as_output_content($content)
/*
	Output the $content via the theme class after doing some pre-processing, mainly relating to Javascript
*/
	{
		
		global $template;
		
		$requestlower=strtolower(as_request());
		
	//	Set appropriate selected flags for navigation (not done in as_content_prepare() since it also applies to sub-navigation)
		
		foreach ($content['navigation'] as $navtype => $navigation)
			if (is_array($navigation) && ($navtype!='cat'))
				foreach ($navigation as $navprefix => $navlink)
					if (substr($requestlower.'$', 0, strlen($navprefix)) == $navprefix)
						$content['navigation'][$navtype][$navprefix]['selected']=true;
	
	//	Slide down notifications
	
		if (!empty($content['notices']))
			foreach ($content['notices'] as $notice) {
				$content['script_onloads'][]=array(
					"as_reveal(document.getElementById(".as_js($notice['id'])."), 'notice');",
				);
			}
	
	//	Handle maintenance mode
	
		if (as_opt('site_maintenance') && ($requestlower!='login')) {
			if (as_get_logged_in_level()>=AS_USER_LEVEL_ADMIN) {
				if (!isset($content['error']))
					$content['error']=strtr(as_lang_html('admin/maintenance_admin_only'), array(
						'^1' => '<a href="'.as_path_html('admin/general').'">',
						'^2' => '</a>',
					));
	
			} else {
				$content=as_content_prepare();
				$content['error']=as_lang_html('misc/site_in_maintenance');
			}
		}
	
	//	Handle new users who must confirm their email now, or must be approved before continuing
	
		$userid=as_get_logged_in_userid();
		if (isset($userid) && ($requestlower!='confirm') && ($requestlower!='account')) {
			$flags=as_get_logged_in_flags();
			
			if ( ($flags & AS_USER_FLAGS_MUST_CONFIRM) && (!($flags & AS_USER_FLAGS_EMAIL_CONFIRMED)) && as_opt('confirm_user_emails') ) {
				$content=as_content_prepare();
				$content['title']=as_lang_html('users/confirm_title');
				$content['error']=strtr(as_lang_html('users/confirm_required'), array(
					'^1' => '<a href="'.as_path_html('confirm').'">',
					'^2' => '</a>',
				));
			
			} elseif ( ($flags & AS_USER_FLAGS_MUST_APPROVE) && (as_get_logged_in_level()<AS_USER_LEVEL_APPROVED) && as_opt('moderate_users') ) {
				$content=as_content_prepare();
				$content['title']=as_lang_html('users/approve_title');
				$content['error']=strtr(as_lang_html('users/approve_required'), array(
					'^1' => '<a href="'.as_path_html('account').'">',
					'^2' => '</a>',
				));
			}
		}
	
	//	Combine various Javascript elements in $content into single array for theme layer
	
		/* $script=array('<script type="text/javascript">');
		
		if (isset($content['script_var']))
			foreach ($content['script_var'] as $var => $value)
				$script[]='var '.$var.'='.as_js($value).';';
				
		if (isset($content['script_lines']))
			foreach ($content['script_lines'] as $scriptlines) {
				$script[]='';
				$script=array_merge($script, $scriptlines);
			}
			
		if (isset($content['focusid']))
			$content['script_onloads'][]=array(
				"var elem=document.getElementById(".as_js($content['focusid']).");",
				"if (elem) {",
				"\telem.select();",
				"\telem.focus();",
				"}",
			);
			
		if (isset($content['script_onloads'])) {
			array_push($script,
				'',
				'var as_oldonload=window.onload;',
				'window.onload=function() {',
				"\tif (typeof as_oldonload=='function')",
				"\t\tas_oldonload();"
			);
			
			foreach ($content['script_onloads'] as $scriptonload) {
				$script[]="\t";
				
				foreach ((array)$scriptonload as $scriptline)
					$script[]="\t".$scriptline;
			}
	
			$script[]='};';
		}
		
		$script[]='</script>';
		
		if (isset($content['script_rel'])) {
			$uniquerel=array_unique($content['script_rel']); // remove any duplicates
			foreach ($uniquerel as $script_rel)
				$script[]='<script src="'.as_html(as_path_to_root().$script_rel).'" type="text/javascript"></script>';
		}
		
		if (isset($content['script_src'])) {
			$uniquesrc=array_unique($content['script_src']); // remove any duplicates
			foreach ($uniquesrc as $script_src)
				$script[]='<script src="'.as_html($script_src).'" type="text/javascript"></script>';
		}
	
		$content['script']=$script; */

	//	Load the appropriate theme class and output the page
	
		$themeclass=as_load_theme_class(as_get_site_theme(), (substr($template, 0, 7)=='custom-') ? 'custom' : $template, $content, as_request());
	
		
		header('Content-type: '.get_content_type());
		//$themeclass->doctype();
		//$themeclass->html();
		//$themeclass->finish();
		
		include get_index_file();
	}


	function as_do_content_stats($content)
/*
	Update any statistics required by the fields in $content, and return true if something was done
*/
	{
		if (isset($content['inc_views_postid'])) {
			require_once AS_INCLUDE_DIR.'as-db-hotness.php';
			as_db_hotness_update($content['inc_views_postid'], null, true);
			return true;
		}
		
		return false;
	}
