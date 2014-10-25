<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-index.php
	Version: See define()s at top of as-include/as-base.php
	Description: The Grand Central of Q2A - most requests come through here


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

//	Try our best to set base path here just in case it wasn't set in index.php (pre version 1.0.1)
	
	if (!defined('BASE_DIR'))
		define('BASE_DIR', dirname(empty($_SERVER['SCRIPT_FILENAME']) ? dirname(__FILE__) : $_SERVER['SCRIPT_FILENAME']).'/');
	
	function get_base_url(){
		/* First we need to get the protocol the website is using */
		$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"], 0, 5)) == 'https' ? 'https://' : 'http://';

		$root = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT']);
		if(substr($root, -1) == '/')$root = substr($root, 0, -1);
		$base = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, rtrim(BASE_DIR, '/'));

			
		/* Returns localhost OR mysite.com */
		$host = $_SERVER['HTTP_HOST'];

		$url = $protocol . $host . '/' . str_replace($root, '', $base );
		
		return (substr($url, -1) == '/') ? substr($url, 0, -1) : $url;
	}
	
	define('BASE_URL', get_base_url());
	define('LIB_DIR_URL', BASE_URL.'/as-include/lib');
	define('THEME_DIR_URL', BASE_URL.'/as-theme');

//	If this is an special non-page request, branch off here

	if (@$_POST['as']=='ajax')
		require 'as-ajax.php';

	elseif (@$_GET['as']=='image')
		require 'as-image.php';

	elseif (@$_GET['as']=='blob')
		require 'as-blob.php';

	else {
		
	//	Otherwise, load the Q2A base file which sets up a bunch of crucial stuff
		
		// load hooking system	
		require_once 'hooks.php';

		global $as_request_map;
		
		define('AS_CATEGORY_DEPTH', 4); // you can't change this number!

		if (!defined('BASE_DIR'))
			define('BASE_DIR', dirname(dirname(__FILE__)).'/'); // try out best if not set in index.php or as-index.php - won't work with symbolic links
			
		define('AS_EXTERNAL_DIR', BASE_DIR.'as-external/');
		define('AS_INCLUDE_DIR', BASE_DIR.'as-include/');
		define('AS_LANG_DIR', BASE_DIR.'as-lang/');
		define('THEME_DIR', BASE_DIR.'as-theme/');
		define('AS_PLUGIN_DIR', BASE_DIR.'as-plugin/');

		if (!file_exists(BASE_DIR.'as-config.php'))
			as_fatal_error('The config file could not be found. Please read the instructions in as-config-example.php.');
		
		require_once BASE_DIR.'as-config.php';
		
		$as_request_map=is_array(@$AS_CONST_PATH_MAP) ? $AS_CONST_PATH_MAP : array();
		
		//	Default values if not set in as-config.php
	
		@define('AS_COOKIE_DOMAIN', '');
		@define('AS_HTML_COMPRESSION', true);
		@define('AS_MAX_LIMIT_START', 19999);
		@define('AS_IGNORED_WORDS_FREQ', 10000);
		@define('AS_ALLOW_UNINDEXED_QUERIES', false);
		@define('AS_OPTIMIZE_LOCAL_DB', false);
		@define('AS_OPTIMIZE_DISTANT_DB', false);
		@define('AS_PERSISTENT_CONN_DB', false);
		@define('AS_DEBUG_PERFORMANCE', false);
		
		//	Start performance monitoring
	
		if (AS_DEBUG_PERFORMANCE) {
			require_once 'as-util-debug.php';
			as_usage_init();
		}
		
		//	More for WordPress integration
		
		
		define('AS_FINAL_MYSQL_HOSTNAME', AS_MYSQL_HOSTNAME);
		define('AS_FINAL_MYSQL_USERNAME', AS_MYSQL_USERNAME);
		define('AS_FINAL_MYSQL_PASSWORD', AS_MYSQL_PASSWORD);
		define('AS_FINAL_MYSQL_DATABASE', AS_MYSQL_DATABASE);
		define('AS_FINAL_EXTERNAL_USERS', AS_EXTERNAL_USERS);
		
		
		//	Possible URL schemes for Q2A and the string used for url scheme testing

		define('AS_URL_FORMAT_INDEX', 0);  // http://...../index.php/123/why-is-the-sky-blue
		define('AS_URL_FORMAT_NEAT', 1);   // http://...../123/why-is-the-sky-blue [requires .htaccess]
		define('AS_URL_FORMAT_PARAM', 3);  // http://...../?as=123/why-is-the-sky-blue
		define('AS_URL_FORMAT_PARAMS', 4); // http://...../?as=123&as_1=why-is-the-sky-blue
		define('AS_URL_FORMAT_SAFEST', 5); // http://...../index.php?as=123&as_1=why-is-the-sky-blue

		define('AS_URL_TEST_STRING', '$&-_~#%\\@^*()=!()][`\';:|".{},<>?# p§½??'); // tests escaping, spaces, quote slashing and unicode - but not + and /
				
		require_once 'as-base.php';
		require_once 'as-app-format.php';
		
		global $current_theme;
		$current_theme = as_get_site_theme();
		
		if (file_exists(THEME_DIR.$current_theme.'/functions.php')) {
			require_once THEME_DIR.$current_theme.'/functions.php';
		}else{
			as_fatal_error('functions.php file missing in '.$current_theme.' theme');
		}
		
		require AS_INCLUDE_DIR.'theme.php';
		
		as_initialize_php();
	
		as_initialize_modularity();
		as_register_core_modules();
		as_load_plugin_files();
		as_load_override_files();
		
		as_index_set_request();
		
		
	//	Branch off to appropriate file for further handling
	
		$requestlower=strtolower(as_request());
		
		if ($requestlower=='install')
			require AS_INCLUDE_DIR.'as-install.php';
			
		elseif ($requestlower==('url/test/'.AS_URL_TEST_STRING))
			require AS_INCLUDE_DIR.'as-url-test.php';
		
		else {
	
		//	Enable gzip compression for output (needs to come early)
	
			/* if (AS_HTML_COMPRESSION) // on by default
				if (substr($requestlower, 0, 6)!='admin/') // not for admin pages since some of these contain lengthy processes
					if (extension_loaded('zlib') && !headers_sent())
						ob_start('ob_gzhandler'); */
						
			if (substr($requestlower, 0, 5)=='feed/')
				require AS_INCLUDE_DIR.'as-feed.php';
			else{
				require AS_INCLUDE_DIR.'template.php';
				
				global $content;

				as_report_process_stage('init_page');
				as_db_connect('as_page_db_fail_handler');
					
				as_page_queue_pending();
				as_load_state();
				as_check_login_modules();

				if (AS_DEBUG_PERFORMANCE)
					as_usage_mark('setup');

				as_check_page_clicks();

				$content=as_get_request_content();
				
				if (is_array($content)) {
					if (AS_DEBUG_PERFORMANCE)
						as_usage_mark('view');

					as_output_content($content);

					if (AS_DEBUG_PERFORMANCE)
						as_usage_mark('theme');
						
					if (as_do_content_stats($content))
						if (AS_DEBUG_PERFORMANCE)
							as_usage_mark('stats');

					if (AS_DEBUG_PERFORMANCE)
						as_usage_output();
				}
				
				as_db_disconnect();
				
			}
		}
	}
	
	as_report_process_stage('shutdown');


/*
	Omit PHP closing tag to help avoid accidental output
*/