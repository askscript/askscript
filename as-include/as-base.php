<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-base.php
	Version: See define()s at top of as-include/as-base.php
	Description: Sets up Q2A environment, plus many globally useful functions


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

	
	define('AS_VERSION', '1.6.3'); // also used as suffix for .js and .css requests
	define('AS_BUILD_DATE', '2014-01-19');

//	Execution section of this file - remainder contains function definitions

	as_initialize_php();
	as_initialize_constants_1();

	if (defined('AS_WORDPRESS_LOAD_FILE')) // if relevant, load WordPress integration in global scope
		require_once AS_WORDPRESS_LOAD_FILE;

	as_initialize_constants_2();
	as_initialize_modularity();
	as_register_core_modules();
	as_load_plugin_files();
	as_load_override_files();

	require_once AS_INCLUDE_DIR.'as-db.php';

	as_db_allow_connect();
	

//	Version comparison functions

	function as_version_to_float($version)
/*
	Converts the $version string (e.g. 1.6.2.2) to a floating point that can be used for greater/lesser comparisons
	(PHP's version_compare() function is not quite suitable for our needs)
*/
	{
		$value=0.0;

		if (preg_match('/[0-9\.]+/', $version, $matches)) {
			$parts=explode('.', $matches[0]);
			$units=1.0;
			
			foreach ($parts as $part) {
				$value+=min($part, 999)*$units;
				$units/=1000;
			}
		}

		return $value;
	}
	
	
	function as_as_version_below($version)
/*
	Returns true if the current Q2A version is lower than $version, if both are valid version strings for as_version_to_float()
*/
	{
		$minqa=as_version_to_float($version);
		$thisqa=as_version_to_float(AS_VERSION);
		
		return $minqa && $thisqa && ($thisqa<$minqa);
	}
	
	
	function as_php_version_below($version)
/*
	Returns true if the current PHP version is lower than $version, if both are valid version strings for as_version_to_float()
*/
	{
		$minphp=as_version_to_float($version);
		$thisphp=as_version_to_float(phpversion());
		
		return $minphp && $thisphp && ($thisphp<$minphp);
	}
	

//	Initialization functions called above

	function as_initialize_php()
/*
	Set up and verify the PHP environment for Q2A, including unregistering globals if necessary
*/
	{
		if (as_php_version_below('4.3'))
			as_fatal_error('This requires PHP 4.3 or later');
	
		error_reporting(E_ALL); // be ultra-strict about error checking
		
		@ini_set('magic_quotes_runtime', 0);
		
		@setlocale(LC_CTYPE, 'C'); // prevent strtolower() et al affecting non-ASCII characters (appears important for IIS)
		
		if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get'))
			@date_default_timezone_set(@date_default_timezone_get()); // prevent PHP notices where default timezone not set
			
		if (ini_get('register_globals')) {
			$checkarrays=array('_ENV', '_GET', '_POST', '_COOKIE', '_SERVER', '_FILES', '_REQUEST', '_SESSION'); // unregister globals if they're registered
			$keyprotect=array_flip(array_merge($checkarrays, array('GLOBALS')));
			
			foreach ($checkarrays as $checkarray)
				if ( isset(${$checkarray}) && is_array(${$checkarray}) )
					foreach (${$checkarray} as $checkkey => $checkvalue)
						if (isset($keyprotect[$checkkey]))
							as_fatal_error('My superglobals are not for overriding');
						else
							unset($GLOBALS[$checkkey]);
		}
	}
	
	
	function as_initialize_constants_1()
/*
	First stage of setting up Q2A constants, before (if necessary) loading WordPress integration
*/
	{
		global $as_request_map;
		
		define('AS_CATEGORY_DEPTH', 4); // you can't change this number!

		if (!defined('AS_BASE_DIR'))
			define('AS_BASE_DIR', dirname(dirname(__FILE__)).'/'); // try out best if not set in index.php or as-index.php - won't work with symbolic links
			
		define('AS_EXTERNAL_DIR', AS_BASE_DIR.'as-external/');
		define('AS_INCLUDE_DIR', AS_BASE_DIR.'as-include/');
		define('AS_LANG_DIR', AS_BASE_DIR.'as-lang/');
		define('AS_THEME_DIR', AS_BASE_DIR.'as-theme/');
		define('AS_PLUGIN_DIR', AS_BASE_DIR.'as-plugin/');

		if (!file_exists(AS_BASE_DIR.'as-config.php'))
			as_fatal_error('The config file could not be found. Please read the instructions in as-config-example.php.');
		
		require_once AS_BASE_DIR.'as-config.php';
		
		$as_request_map=is_array(@$AS_CONST_PATH_MAP) ? $AS_CONST_PATH_MAP : array();

		if (defined('AS_WORDPRESS_INTEGRATE_PATH') && strlen(AS_WORDPRESS_INTEGRATE_PATH)) {
			define('AS_FINAL_WORDPRESS_INTEGRATE_PATH', AS_WORDPRESS_INTEGRATE_PATH.((substr(AS_WORDPRESS_INTEGRATE_PATH, -1)=='/') ? '' : '/'));
			define('AS_WORDPRESS_LOAD_FILE', AS_FINAL_WORDPRESS_INTEGRATE_PATH.'wp-load.php');
	
			if (!is_readable(AS_WORDPRESS_LOAD_FILE))
				as_fatal_error('Could not find wp-load.php file for WordPress integration - please check AS_WORDPRESS_INTEGRATE_PATH in as-config.php');
		}
	}
	
	
	function as_initialize_constants_2()
/*
	Second stage of setting up Q2A constants, after (if necessary) loading WordPress integration
*/
	{
	
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
		
		if (defined('AS_FINAL_WORDPRESS_INTEGRATE_PATH')) {
			define('AS_FINAL_MYSQL_HOSTNAME', DB_HOST);
			define('AS_FINAL_MYSQL_USERNAME', DB_USER);
			define('AS_FINAL_MYSQL_PASSWORD', DB_PASSWORD);
			define('AS_FINAL_MYSQL_DATABASE', DB_NAME);
			define('AS_FINAL_EXTERNAL_USERS', true);
			
			// Undo WordPress's addition of magic quotes to various things (leave $_COOKIE as is since WP code might need that)

			function as_undo_wordpress_quoting($param, $isget)
			{
				if (is_array($param)) { // 
					foreach ($param as $key => $value)
						$param[$key]=as_undo_wordpress_quoting($value, $isget);
					
				} else {
					$param=stripslashes($param);
					if ($isget)
						$param=strtr($param, array('\\\'' => '\'', '\"' => '"')); // also compensate for WordPress's .htaccess file
				}
				
				return $param;
			}
			
			$_GET=as_undo_wordpress_quoting($_GET, true);
			$_POST=as_undo_wordpress_quoting($_POST, false);
			$_SERVER['PHP_SELF']=stripslashes($_SERVER['PHP_SELF']);
		
		} else {
			define('AS_FINAL_MYSQL_HOSTNAME', AS_MYSQL_HOSTNAME);
			define('AS_FINAL_MYSQL_USERNAME', AS_MYSQL_USERNAME);
			define('AS_FINAL_MYSQL_PASSWORD', AS_MYSQL_PASSWORD);
			define('AS_FINAL_MYSQL_DATABASE', AS_MYSQL_DATABASE);
			define('AS_FINAL_EXTERNAL_USERS', AS_EXTERNAL_USERS);
		}
		
	//	Possible URL schemes for Q2A and the string used for url scheme testing

		define('AS_URL_FORMAT_INDEX', 0);  // http://...../index.php/123/why-is-the-sky-blue
		define('AS_URL_FORMAT_NEAT', 1);   // http://...../123/why-is-the-sky-blue [requires .htaccess]
		define('AS_URL_FORMAT_PARAM', 3);  // http://...../?as=123/why-is-the-sky-blue
		define('AS_URL_FORMAT_PARAMS', 4); // http://...../?as=123&as_1=why-is-the-sky-blue
		define('AS_URL_FORMAT_SAFEST', 5); // http://...../index.php?as=123&as_1=why-is-the-sky-blue

		define('AS_URL_TEST_STRING', '$&-_~#%\\@^*()=!()][`\';:|".{},<>?# π§½Жש'); // tests escaping, spaces, quote slashing and unicode - but not + and /
	}


	function as_initialize_modularity()
/*
	Gets everything ready to start using modules, layers and overrides
*/
	{
		global $as_modules, $as_layers, $as_override_files, $as_overrides, $as_direct;

		$as_modules=array();
		$as_layers=array();
		$as_override_files=array();
		$as_overrides=array();
		$as_direct=array();
	}
	
	
	function as_register_core_modules()
/*
	Register all modules that come as part of the Q2A core (as opposed to plugins)
*/
	{
		as_register_module('filter', 'as-filter-basic.php', 'as_filter_basic', '');
		as_register_module('editor', 'as-editor-basic.php', 'as_editor_basic', '');
		as_register_module('viewer', 'as-viewer-basic.php', 'as_viewer_basic', '');
		as_register_module('event', 'as-event-limits.php', 'as_event_limits', 'Q2A Event Limits');
		as_register_module('event', 'as-event-notify.php', 'as_event_notify', 'Q2A Event Notify');
		as_register_module('event', 'as-event-updates.php', 'as_event_updates', 'Q2A Event Updates');
		as_register_module('search', 'as-search-basic.php', 'as_search_basic', '');
		as_register_module('widget', 'as-widget-activity-count.php', 'as_activity_count', 'Activity Count');
		as_register_module('widget', 'as-widget-ask-box.php', 'as_ask_box', 'Ask Box');
		as_register_module('widget', 'as-widget-related-qs.php', 'as_related_qs', 'Related Questions');
	}
	
	
	function as_load_plugin_files()
/*
	Load all the as-plugin.php files from plugins that are compatible with this version of Q2A
*/
	{
		global $as_plugin_directory, $as_plugin_urltoroot;
		
		$pluginfiles=glob(AS_PLUGIN_DIR.'*/as-plugin.php');

		foreach ($pluginfiles as $pluginfile)
			if (file_exists($pluginfile)) {
				$contents=file_get_contents($pluginfile);
				
				if (preg_match('/Plugin[ \t]*Minimum[ \t]*Question2Answer[ \t]*Version\:[ \t]*([0-9\.]+)\s/i', $contents, $matches))
					if (as_as_version_below($matches[1]))
						continue; // skip plugin which requires a later version of Q2A
				
				if (preg_match('/Plugin[ \t]*Minimum[ \t]*PHP[ \t]*Version\:[ \t]*([0-9\.]+)\s/i', $contents, $matches))
					if (as_php_version_below($matches[1]))
						continue; // skip plugin which requires a later version of PHP
				
				$as_plugin_directory=dirname($pluginfile).'/';
				$as_plugin_urltoroot=substr($as_plugin_directory, strlen(AS_BASE_DIR));
				
				require_once $pluginfile;
				
				$as_plugin_directory=null;
				$as_plugin_urltoroot=null;
			}
	}


	function as_load_override_files()
/*
	Apply all the function overrides in override files that have been registered by plugins
*/
	{
		global $as_override_files, $as_overrides;
		
		$functionindex=array();

		foreach ($as_override_files as $index => $override) {
			$filename=$override['directory'].$override['include'];
			$functionsphp=file_get_contents($filename);
			
			preg_match_all('/\Wfunction\s+(as_[a-z_]+)\s*\(/im', $functionsphp, $rawmatches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE);
			
			$reversematches=array_reverse($rawmatches[1], true); // reverse so offsets remain correct as we step through
			$postreplace=array();
			$suffix='_in_'.preg_replace('/[^A-Za-z0-9_]+/', '_', basename($override['include']));
				// include file name in defined function names to make debugging easier if there is an error
			
			foreach ($reversematches as $rawmatch) {
				$function=strtolower($rawmatch[0]);
				$position=$rawmatch[1];

				if (isset($as_overrides[$function]))
					$postreplace[$function.'_base']=$as_overrides[$function];
					
				$newname=$function.'_override_'.(@++$functionindex[$function]).$suffix;
				$functionsphp=substr_replace($functionsphp, $newname, $position, strlen($function));
				$as_overrides[$function]=$newname;
			}
			
			foreach ($postreplace as $oldname => $newname)
				if (preg_match_all('/\W('.preg_quote($oldname).')\s*\(/im', $functionsphp, $matches, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE)) {
					$searchmatches=array_reverse($matches[1]);
					foreach ($searchmatches as $searchmatch)
						$functionsphp=substr_replace($functionsphp, $newname, $searchmatch[1], strlen($searchmatch[0]));
				}
			
		//	echo '<pre style="text-align:left;">'.htmlspecialchars($functionsphp).'</pre>'; // to debug munged code
			
			as_eval_from_file($functionsphp, $filename);
		}
	}
	

//	Functions for registering different varieties of Q2A modularity
	
	function as_register_module($type, $include, $class, $name, $directory=AS_INCLUDE_DIR, $urltoroot=null)
/*
	Register a module of $type named $name, whose class named $class is defined in file $include (or null if no include necessary)
	If this module comes from a plugin, pass in the local plugin $directory and the $urltoroot relative url for that directory 
*/
	{
		global $as_modules;
		
		$previous=@$as_modules[$type][$name];
		
		if (isset($previous))
			as_fatal_error('A '.$type.' module named '.$name.' already exists. Please check there are no duplicate plugins. '.
				"\n\nModule 1: ".$previous['directory'].$previous['include']."\nModule 2: ".$directory.$include);
		
		$as_modules[$type][$name]=array(
			'directory' => $directory,
			'urltoroot' => $urltoroot,
			'include' => $include,
			'class' => $class,
		);
	}
	
	
	function as_register_layer($include, $name, $directory=AS_INCLUDE_DIR, $urltoroot=null)
/*
	Register a layer named $name, defined in file $include. If this layer comes from a plugin (as all currently do),
	pass in the local plugin $directory and the $urltoroot relative url for that directory 
*/
	{
		global $as_layers;
		
		$previous=@$as_layers[$name];
		
		if (isset($previous))
			as_fatal_error('A layer named '.$name.' already exists. Please check there are no duplicate plugins. '.
				"\n\nLayer 1: ".$previous['directory'].$previous['include']."\nLayer 2: ".$directory.$include);
			
		$as_layers[$name]=array(
			'directory' => $directory,
			'urltoroot' => $urltoroot,
			'include' => $include,
		);
	}
	
	
	function as_register_overrides($include, $directory=AS_INCLUDE_DIR, $urltoroot=null)
/*
	Register a file $include containing override functions. If this file comes from a plugin (as all currently do),
	pass in the local plugin $directory and the $urltoroot relative url for that directory 
*/
	{
		global $as_override_files;
		
		$as_override_files[]=array(
			'directory' => $directory,
			'urltoroot' => $urltoroot,
			'include' => $include
		);
	}
	
	
	function as_register_phrases($pattern, $name)
/*
	Register a set of language phrases, which should be accessed by the prefix $name/ in the as_lang_*() functions.
	Pass in the $pattern representing the PHP files that define these phrases, where * in the pattern is replaced with
	the language code (e.g. 'fr') and/or 'default'. These files should be formatted like Q2A's as-lang-*.php files.
*/
	{
		global $as_lang_file_pattern;
		
		if (file_exists(AS_INCLUDE_DIR.'as-lang-'.$name.'.php'))
			as_fatal_error('The name "'.$name.'" for phrases is reserved and cannot be used by plugins.'."\n\nPhrases: ".$pattern);

		if (isset($as_lang_file_pattern[$name]))
			as_fatal_error('A set of phrases named '.$name.' already exists. Please check there are no duplicate plugins. '.
				"\n\nPhrases 1: ".$as_lang_file_pattern[$name]."\nPhrases 2: ".$pattern);
			
		$as_lang_file_pattern[$name]=$pattern;
	}


//	Function for registering varieties of Q2A modularity, which are (only) called from as-plugin.php files	

	function as_register_plugin_module($type, $include, $class, $name)
/*
	Register a plugin module of $type named $name, whose class named $class is defined in file $include (or null if no include necessary)
	This function relies on some global variable values and can only be called from a plugin's as-plugin.php file
*/
	{
		global $as_plugin_directory, $as_plugin_urltoroot;
		
		if (empty($as_plugin_directory) || empty($as_plugin_urltoroot))
			as_fatal_error('as_register_plugin_module() can only be called from a plugin as-plugin.php file');

		as_register_module($type, $include, $class, $name, $as_plugin_directory, $as_plugin_urltoroot);
	}

	
	function as_register_plugin_layer($include, $name)
/*
	Register a plugin layer named $name, defined in file $include. Can only be called from a plugin's as-plugin.php file
*/
	{
		global $as_plugin_directory, $as_plugin_urltoroot;
		
		if (empty($as_plugin_directory) || empty($as_plugin_urltoroot))
			as_fatal_error('as_register_plugin_layer() can only be called from a plugin as-plugin.php file');

		as_register_layer($include, $name, $as_plugin_directory, $as_plugin_urltoroot);
	}
	
	
	function as_register_plugin_overrides($include)
/*
	Register a plugin file $include containing override functions. Can only be called from a plugin's as-plugin.php file
*/
	{
		global $as_plugin_directory, $as_plugin_urltoroot;

		if (empty($as_plugin_directory) || empty($as_plugin_urltoroot))
			as_fatal_error('as_register_plugin_overrides() can only be called from a plugin as-plugin.php file');
			
		as_register_overrides($include, $as_plugin_directory, $as_plugin_urltoroot);
	}
	
	
	function as_register_plugin_phrases($pattern, $name)
/*
	Register a file name $pattern within a plugin directory containing language phrases accessed by the prefix $name
*/
	{
		global $as_plugin_directory, $as_plugin_urltoroot;
		
		if (empty($as_plugin_directory) || empty($as_plugin_urltoroot))
			as_fatal_error('as_register_plugin_phrases() can only be called from a plugin as-plugin.php file');

		as_register_phrases($as_plugin_directory.$pattern, $name);
	}
	
	
//	Low-level functions used throughout Q2A

	function as_eval_from_file($eval, $filename)
/*
	Calls eval() on the PHP code in $eval which came from the file $filename. It supplements PHP's regular error reporting by
	displaying/logging (as appropriate) the original source filename, if an error occurred when evaluating the code.
*/
	{
		// could also use ini_set('error_append_string') but apparently it doesn't work for errors logged on disk
		
		global $php_errormsg;
		
		$oldtrackerrors=@ini_set('track_errors', 1);
		$php_errormsg=null; 
		
		eval('?'.'>'.$eval);
		
		if (strlen($php_errormsg)) {
			switch (strtolower(@ini_get('display_errors'))) {
				case 'on': case '1': case 'yes': case 'true': case 'stdout': case 'stderr':
					echo ' of '.as_html($filename)."\n";
					break;
			}

			@error_log('PHP Question2Answer more info: '.$php_errormsg." in eval()'d code from ".as_html($filename));
		}
		
		@ini_set('track_errors', $oldtrackerrors);
	}
	
	
	function as_call($function, $args)
/*
	Call $function with the arguments in the $args array (doesn't work with call-by-reference functions)
*/
	{
		switch (count($args)) { // call_user_func_array(...) is very slow, so we break out most cases
			case 0: return $function();
			case 1: return $function($args[0]);
			case 2: return $function($args[0], $args[1]);
			case 3: return $function($args[0], $args[1], $args[2]);
			case 4: return $function($args[0], $args[1], $args[2], $args[3]);
			case 5: return $function($args[0], $args[1], $args[2], $args[3], $args[4]);
		}
		
		return call_user_func_array($function, $args);
	}

	
	function as_to_override($function)
/*
	If $function has been overridden by a plugin override, return the name of the overriding function, otherwise return
	null. But if the function is being called with the _base suffix, any override will be bypassed due to $as_direct
*/
	{
		global $as_overrides, $as_direct;
		
		if (strpos($function, '_override_')!==false)
			as_fatal_error('Override functions should not be calling as_to_override()!');
		
		if (isset($as_overrides[$function])) {
			if (@$as_direct[$function])
				unset($as_direct[$function]); // bypass the override just this once
			else
				return $as_overrides[$function];
		}
		
		return null;
	}
	
	
	function as_call_override($function, $args)
/*
	Call the function which immediately overrides $function with the arguments in the $args array
*/
	{
		global $as_overrides;
		
		if (strpos($function, '_override_')!==false)
			as_fatal_error('Override functions should not be calling as_call_override()!');
		
		if (!function_exists($function.'_base')) // define the base function the first time that it's needed
			eval('function '.$function.'_base() { global $as_direct; $as_direct[\''.$function.'\']=true; $args=func_get_args(); return as_call(\''.$function.'\', $args); }');
		
		return as_call($as_overrides[$function], $args);
	}
	
	
	function as_exit($reason=null)
/*
	Exit PHP immediately after reporting a shutdown with $reason to any installed process modules
*/
	{
		as_report_process_stage('shutdown', $reason);
		exit;
	}


	function as_fatal_error($message)
/*
	Display $message in the browser, write it to server error log, and then stop abruptly
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		echo 'Question2Answer fatal error:<p><font color="red">'.as_html($message, true).'</font></p>';
		@error_log('PHP Question2Answer fatal error: '.$message);
		echo '<p>Stack trace:<p>';

		$backtrace=array_reverse(array_slice(debug_backtrace(), 1));
		foreach ($backtrace as $trace)
			echo '<font color="#'.((strpos(@$trace['file'], '/as-plugin/')!==false) ? 'f00' : '999').'">'.
				as_html(@$trace['function'].'() in '.basename(@$trace['file']).':'.@$trace['line']).'</font><br>';	
		
		as_exit('error');
	}
	

//	Functions for listing, loading and getting info on modules

	function as_list_module_types()
/*
	Return an array of all the module types for which at least one module has been registered
*/
	{
		global $as_modules;
		
		return array_keys($as_modules);
	}

	
	function as_list_modules($type)
/*
	Return a list of names of registered modules of $type
*/
	{
		global $as_modules;
		
		return is_array(@$as_modules[$type]) ? array_keys($as_modules[$type]) : array();
	}
	
	
	function as_get_module_info($type, $name)
/*
	Return an array containing information about the module of $type named $name
*/
	{
		global $as_modules;
		return @$as_modules[$type][$name];
	}
	
	
	function as_load_module($type, $name)
/*
	Return an instantiated class for module of $type named $name, whose functions can be called, or null if it doesn't exist
*/
	{
		global $as_modules;
		
		$module=@$as_modules[$type][$name];
		
		if (is_array($module)) {
			if (isset($module['object']))
				return $module['object'];
			
			if (strlen(@$module['include']))
				require_once $module['directory'].$module['include'];
			
			if (strlen(@$module['class'])) {
				$object=new $module['class'];
				
				if (method_exists($object, 'load_module'))
					$object->load_module($module['directory'], as_path_to_root().$module['urltoroot'], $type, $name);
				
				$as_modules[$type][$name]['object']=$object;
				return $object;
			}
		}
		
		return null;
	}
	
	
	function as_load_modules_with($type, $method)
/*
	Return an array of instantiated clases for modules of $type which have defined $method
	(other modules of that type are also loaded but not included in the returned array)
*/
	{
		$modules=array();
		
		$trynames=as_list_modules($type);
		
		foreach ($trynames as $tryname) {
			$module=as_load_module($type, $tryname);
			
			if (method_exists($module, $method))
				$modules[$tryname]=$module;
		}
		
		return $modules;
	}
	
	
//	HTML and Javascript escaping and sanitization

	function as_html($string, $multiline=false)
/*
	Return HTML representation of $string, work well with blocks of text if $multiline is true
*/
	{
		$html=htmlspecialchars((string)$string);
		
		if ($multiline) {
			$html=preg_replace('/\r\n?/', "\n", $html);
			$html=preg_replace('/(?<=\s) /', '&nbsp;', $html);
			$html=str_replace("\t", '&nbsp; &nbsp; ', $html);
			$html=nl2br($html);
		}
		
		return $html;
	}

	
	function as_sanitize_html($html, $linksnewwindow=false, $storage=false)
/*
	Return $html after ensuring it is safe, i.e. removing Javascripts and the like - uses htmLawed library
	Links open in a new window if $linksnewwindow is true. Set $storage to true if sanitization is for
	storing in the database, rather than immediate display to user - some think this should be less strict.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once 'as-htmLawed.php';
		
		global $as_sanitize_html_newwindow;
		
		$as_sanitize_html_newwindow=$linksnewwindow;
		
		$safe=htmLawed($html, array(
			'safe' => 1,
			'elements' => '*+embed+object-form',
			'schemes' => 'href: aim, feed, file, ftp, gopher, http, https, irc, mailto, news, nntp, sftp, ssh, telnet; *:file, http, https; style: !; classid:clsid',
			'keep_bad' => 0,
			'anti_link_spam' => array('/.*/', ''),
			'hook_tag' => 'as_sanitize_html_hook_tag',
		));
		
		return $safe;
	}
	
	
	function as_sanitize_html_hook_tag($element, $attributes=null)
/*
	htmLawed hook function used to process tags in as_sanitize_html(...)
*/
	{
		global $as_sanitize_html_newwindow;

		if (!isset($attributes)) // it's a closing tag
			return '</'.$element.'>';
		
		if ( ($element=='param') && (trim(strtolower(@$attributes['name']))=='allowscriptaccess') )
			$attributes['name']='allowscriptaccess_denied';
			
		if ($element=='embed')
			unset($attributes['allowscriptaccess']);
			
		if (($element=='a') && isset($attributes['href']) && $as_sanitize_html_newwindow)
			$attributes['target']='_blank';
		
		$html='<'.$element;
		foreach ($attributes as $key => $value)
			$html.=' '.$key.'="'.$value.'"';
			
		return $html.'>';
	}
	
	
	function as_xml($string)
/*
	Return XML representation of $string, which is similar to HTML but ASCII control characters are also disallowed
*/
	{
		return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$string));
	}
	
	
	function as_js($value, $forcequotes=false)
/*
	Return JavaScript representation of $value, putting in quotes if non-numeric or if $forcequotes is true
*/
	{
		if (is_numeric($value) && !$forcequotes)
			return $value;
		else
			return "'".strtr($value, array(
				"'" => "\\'",
				'/' => '\\/',
				'\\' => '\\\\',
				"\n" => "\\n",
				"\r" => "\\n",
			))."'";
	}


//	Finding out more about the current request
	
	function as_set_request($request, $relativeroot, $usedformat=null)
/*
	Inform Q2A that the current request is $request (slash-separated, independent of the url scheme chosen),
	that the relative path to the Q2A root apperas to be $relativeroot, and the url scheme appears to be $usedformat
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_request, $as_root_url_relative, $as_used_url_format;
		
		$as_request=$request;
		$as_root_url_relative=$relativeroot;
		$as_used_url_format=$usedformat;
	}
	
	
	function as_request()
/*
	Returns the current Q2A request (slash-separated, independent of the url scheme chosen)
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_request;
		return $as_request;
	}
	
	
	function as_request_part($part)
/*
	Returns the indexed $part (as separated by slashes) of the current Q2A request, or null if it doesn't exist
*/
	{
		$parts=explode('/', as_request());
		return @$parts[$part];
	}
	
	
	function as_request_parts($start=0)
/*
	Returns an array of parts (as separated by slashes) of the current Q2A request, starting at part $start
*/
	{
		return array_slice(explode('/', as_request()), $start);
	}
	
	
	function as_gpc_to_string($string)
/*
	Return string for incoming GET/POST/COOKIE value, stripping slashes if appropriate
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return get_magic_quotes_gpc() ? stripslashes($string) : $string;
	}
	

	function as_string_to_gpc($string)
/*
	Return string with slashes added, if appropriate for later removal by as_gpc_to_string()
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return get_magic_quotes_gpc() ? addslashes($string) : $string;
	}


	function as_get($field)
/*
	Return string for incoming GET field, or null if it's not defined
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return isset($_GET[$field]) ? as_gpc_to_string($_GET[$field]) : null;
	}


	function as_post_text($field)
/*
	Return string for incoming POST field, or null if it's not defined.
	While we're at it, trim() surrounding white space and converted to Unix line endings.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return isset($_POST[$field]) ? preg_replace('/\r\n?/', "\n", trim(as_gpc_to_string($_POST[$field]))) : null;
	}

	
	function as_clicked($name)
/*
	Return true if form button $name was clicked (as type=submit/image) to create this page request, or if a
	simulated click was sent for the button (via 'as_click' POST field)
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return isset($_POST[$name]) || isset($_POST[$name.'_x']) || (as_post_text('as_click')==$name);
	}

	
	function as_remote_ip_address()
/*
	Return the remote IP address of the user accessing the site, if it's available, or null otherwise
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return @$_SERVER['REMOTE_ADDR'];
	}
	
	
	function as_is_http_post()
/*
	Return true if we are responding to an HTTP POST request
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return ($_SERVER['REQUEST_METHOD']=='POST') || !empty($_POST);
	}

	
	function as_is_https_probably()
/*
	Return true if we appear to be responding to a secure HTTP request (but hard to be sure)
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return (@$_SERVER['HTTPS'] && ($_SERVER['HTTPS']!='off')) || (@$_SERVER['SERVER_PORT']==443);
	}
	
	
	function as_is_human_probably()
/*
	Return true if it appears the page request is coming from a human using a web browser, rather than a search engine
	or other bot. Based on a whitelist of terms in user agents, this can easily be tricked by a scraper or bad bot.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-util-string.php';
		
		$useragent=@$_SERVER['HTTP_USER_AGENT'];
		
		return (strlen($useragent)==0) || as_string_matches_one($useragent, array(
			'MSIE', 'Firefox', 'Chrome', 'Safari', 'Opera', 'Gecko', 'MIDP', 'PLAYSTATION', 'Teleca',
			'BlackBerry', 'UP.Browser', 'Polaris', 'MAUI_WAP_Browser', 'iPad', 'iPhone', 'iPod'
		));
	}
	
	
	function as_is_mobile_probably()
/*
	Return true if it appears that the page request is coming from a mobile client rather than a desktop/laptop web browser
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-util-string.php';
		
		// inspired by: http://dangerousprototypes.com/docs/PhpBB3_MOD:_Replacement_mobile_browser_detection_for_mobile_themes
		
		$loweragent=strtolower(@$_SERVER['HTTP_USER_AGENT']);
		
		if (strpos($loweragent, 'ipad')!==false) // consider iPad as desktop
			return false;
		
		$mobileheaders=array('HTTP_X_OPERAMINI_PHONE', 'HTTP_X_WAP_PROFILE', 'HTTP_PROFILE');
		
		foreach ($mobileheaders as $header)
			if (isset($_SERVER[$header]))
				return true;
				
		if (as_string_matches_one($loweragent, array(
			'android', 'phone', 'mobile', 'windows ce', 'palm', ' mobi', 'wireless', 'blackberry', 'opera mini', 'symbian',
			'nokia', 'samsung', 'ericsson,', 'vodafone/', 'kindle', 'ipod', 'wap1.', 'wap2.', 'sony', 'sanyo', 'sharp',
			'panasonic', 'philips', 'pocketpc', 'avantgo', 'blazer', 'ipaq', 'up.browser', 'up.link', 'mmp', 'smartphone', 'midp'
		)))
			return true;
		
		return as_string_matches_one(strtolower(@$_SERVER['HTTP_ACCEPT']), array(
			'application/vnd.wap.xhtml+xml', 'text/vnd.wap.wml'
		));
	}
	
	
//	Language phrase support

	function as_lang($identifier)
/*
	Return the translated string for $identifier, unless we're using external translation logic.
	This will retrieve the 'site_language' option so make sure you've already loaded/set that if
	loading an option now will cause a problem (see issue in as_default_option()). The part of
	$identifier before the slash (/) replaces the * in the as-lang-*.php file references, and the
	part after the / is the key of the array element to be taken from that file's returned result.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_lang_file_pattern, $as_phrases_custom, $as_phrases_lang, $as_phrases_default;
		
		list($group, $label)=explode('/', $identifier, 2);
		
	//	First look for a custom phrase
		
		if (!isset($as_phrases_custom[$group])) { // only load each language file once
			$phrases=@include AS_LANG_DIR.'custom/as-lang-'.$group.'.php'; // can tolerate missing file or directory
			$as_phrases_custom[$group]=is_array($phrases) ? $phrases : array();
		}
		
		if (isset($as_phrases_custom[$group][$label]))
			return $as_phrases_custom[$group][$label];
			
	//	Second look for a localized file
	
		$languagecode=as_opt('site_language');
		
		if (strlen($languagecode)) {
			if (!isset($as_phrases_lang[$group])) {
				if (isset($as_lang_file_pattern[$group]))
					$include=str_replace('*', $languagecode, $as_lang_file_pattern[$group]);
				else
					$include=AS_LANG_DIR.$languagecode.'/as-lang-'.$group.'.php';
				
				$phrases=@include $include;
				$as_phrases_lang[$group]=is_array($phrases) ? $phrases : array();
			}
			
			if (isset($as_phrases_lang[$group][$label]))
				return $as_phrases_lang[$group][$label];
		}
		
	//	Finally load the default
	
		if (!isset($as_phrases_default[$group])) { // only load each default language file once
			if (isset($as_lang_file_pattern[$group]))
				$include=str_replace('*', 'default', $as_lang_file_pattern[$group]);
			else
				$include=AS_INCLUDE_DIR.'as-lang-'.$group.'.php';
				
			$as_phrases_default[$group]=@include_once $include;
		}
		
		if (isset($as_phrases_default[$group][$label]))
			return $as_phrases_default[$group][$label];
			
		return '['.$identifier.']'; // as a last resort, return the identifier to help in development
	}


	function as_lang_sub($identifier, $textparam, $symbol='^')
/*
	Return the translated string for $identifier, with $symbol substituted for $textparam
*/
	{
		return str_replace($symbol, $textparam, as_lang($identifier));
	}
	

	function as_lang_html($identifier)
/*
	Return the translated string for $identifier, converted to HTML
*/
	{
		return as_html(as_lang($identifier));
	}

	
	function as_lang_html_sub($identifier, $htmlparam, $symbol='^')
/*
	Return the translated string for $identifier converted to HTML, with $symbol *then* substituted for $htmlparam
*/
	{
		return str_replace($symbol, $htmlparam, as_lang_html($identifier));
	}
	

	function as_lang_html_sub_split($identifier, $htmlparam, $symbol='^')
/*
	Return an array containing the translated string for $identifier converted to HTML, then split into three,
	with $symbol substituted for $htmlparam in the 'data' element, and obvious 'prefix' and 'suffix' elements
*/
	{
		$html=as_lang_html($identifier);

		$symbolpos=strpos($html, $symbol);
		if (!is_numeric($symbolpos))
			as_fatal_error('Missing '.$symbol.' in language string '.$identifier);
			
		return array(
			'prefix' => substr($html, 0, $symbolpos),
			'data' => $htmlparam,
			'suffix' => substr($html, $symbolpos+1),
		);
	}

	
//	Request and path generation 

	function as_path_to_root()
/*
	Return the relative path to the Q2A root (if it's was previously set by as_set_request())
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_root_url_relative;
		return $as_root_url_relative;
	}
	
	
	function as_get_request_map()
/*
	Return an array of mappings of Q2A requests, as defined in the as-config.php file
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_request_map;
		return $as_request_map;
	}
	

	function as_path($request, $params=null, $rooturl=null, $neaturls=null, $anchor=null)
/*
	Return the relative URI path for $request, with optional parameters $params and $anchor.
	Slashes in $request will not be urlencoded, but any other characters will.
	If $neaturls is set, use that, otherwise retrieve the option. If $rooturl is set, take
	that as the root of the Q2A site, otherwise use path to root which was set elsewhere.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		if (!isset($neaturls)) {
			require_once AS_INCLUDE_DIR.'as-app-options.php';
			$neaturls=as_opt('neat_urls');
		}
		
		if (!isset($rooturl))
			$rooturl=as_path_to_root();
		
		$url=$rooturl.( (empty($rooturl) || (substr($rooturl, -1)=='/') ) ? '' : '/');
		$paramsextra='';
		
		$requestparts=explode('/', $request);
		$pathmap=as_get_request_map();
		
		if (isset($pathmap[$requestparts[0]])) {
			$newpart=$pathmap[$requestparts[0]];
			
			if (strlen($newpart))
				$requestparts[0]=$newpart;
			elseif (count($requestparts)==1)
				array_shift($requestparts);
		}
		
		foreach ($requestparts as $index => $requestpart)
			$requestparts[$index]=urlencode($requestpart);
		$requestpath=implode('/', $requestparts);
		
		switch ($neaturls) {
			case AS_URL_FORMAT_INDEX:
				if (!empty($request))
					$url.='index.php/'.$requestpath;
				break;
				
			case AS_URL_FORMAT_NEAT:
				$url.=$requestpath;
				break;
				
			case AS_URL_FORMAT_PARAM:
				if (!empty($request))
					$paramsextra='?as='.$requestpath;
				break;
				
			default:
				$url.='index.php';
			
			case AS_URL_FORMAT_PARAMS:
				if (!empty($request))
					foreach ($requestparts as $partindex => $requestpart)
						$paramsextra.=(strlen($paramsextra) ? '&' : '?').'as'.($partindex ? ('_'.$partindex) : '').'='.$requestpart;
				break;
		}
		
		if (isset($params))
			foreach ($params as $key => $value)
				$paramsextra.=(strlen($paramsextra) ? '&' : '?').urlencode($key).'='.urlencode((string)$value);
		
		return $url.$paramsextra.( empty($anchor) ? '' : '#'.urlencode($anchor) );
	}


	function as_path_html($request, $params=null, $rooturl=null, $neaturls=null, $anchor=null)
/*
	Return HTML representation of relative URI path for $request - see as_path() for other parameters
*/
	{
		return as_html(as_path($request, $params, $rooturl, $neaturls, $anchor));
	}
	
	
	function as_path_absolute($request, $params=null, $anchor=null)
/*
	Return the absolute URI for $request - see as_path() for other parameters
*/
	{
		return as_path($request, $params, as_opt('site_url'), null, $anchor);
	}

	
	function as_q_request($questionid, $title)
/*
	Return the Q2A request for question $questionid, and make it search-engine friendly based on $title, which is
	shortened if necessary by removing shorter words which are generally less meaningful.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-app-options.php';
		require_once AS_INCLUDE_DIR.'as-util-string.php';
	
		$title=as_block_words_replace($title, as_get_block_words_preg());
		
		$words=as_string_to_words($title, true, false, false);

		$wordlength=array();
		foreach ($words as $index => $word)
			$wordlength[$index]=as_strlen($word);

		$remaining=as_opt('q_urls_title_length');
		
		if (array_sum($wordlength)>$remaining) {
			arsort($wordlength, SORT_NUMERIC); // sort with longest words first
			
			foreach ($wordlength as $index => $length) {
				if ($remaining>0)
					$remaining-=$length;
				else
					unset($words[$index]);
			}
		}
		
		$title=implode('-', $words);
		if (as_opt('q_urls_remove_accents'))
			$title=as_string_remove_accents($title);
		
		return (int)$questionid.'/'.$title;
	}
	
	
	function as_anchor($basetype, $postid)
/*
	Return the HTML anchor that should be used for post $postid with $basetype (Q/A/C)
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return strtolower($basetype).$postid; // used to be $postid only but this violated HTML spec
	}
	
	
	function as_q_path($questionid, $title, $absolute=false, $showtype=null, $showid=null)
/*
	Return the URL for question $questionid with $title, possibly using $absolute URLs.
	To link to a specific answer or comment in a question, set $showtype and $showid accordingly.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		if ( (($showtype=='Q') || ($showtype=='A') || ($showtype=='C')) && isset($showid))  {
			$params=array('show' => $showid); // due to pagination
			$anchor=as_anchor($showtype, $showid);
		
		} else {
			$params=null;
			$anchor=null;
		}
		
		return as_path(as_q_request($questionid, $title), $params, $absolute ? as_opt('site_url') : null, null, $anchor);
	}
	
	
	function as_q_path_html($questionid, $title, $absolute=false, $showtype=null, $showid=null)
/*
	Return the HTML representation of the URL for $questionid - other parameters as for as_q_path()
*/
	{
		return as_html(as_q_path($questionid, $title, $absolute, $showtype, $showid));
	}

	
	function as_feed_request($feed)
/*
	Return the request for the specified $feed
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return 'feed/'.$feed.'.rss';
	}
	
	
	function as_self_html()
/*
	Return an HTML-ready relative URL for the current page, preserving GET parameters - this is useful for action="..." in HTML forms
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_used_url_format;
		
		return as_path_html(as_request(), $_GET, null, $as_used_url_format);
	}
	

	function as_path_form_html($request, $params=null, $rooturl=null, $neaturls=null, $anchor=null)
/*
	Return HTML for hidden fields to insert into a <form method="get"...> on the page.
	This is needed because any parameters on the URL will be lost when the form is submitted.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		$path=as_path($request, $params, $rooturl, $neaturls, $anchor);
		$formhtml='';
		
		$questionpos=strpos($path, '?');
		if (is_numeric($questionpos)) {
			$params=explode('&', substr($path, $questionpos+1));
			
			foreach ($params as $param)
				if (preg_match('/^([^\=]*)(\=(.*))?$/', $param, $matches))
					$formhtml.='<input type="hidden" name="'.as_html(urldecode($matches[1])).'" value="'.as_html(urldecode(@$matches[3])).'"/>';
		}
		
		return $formhtml;
	}
	
	
	function as_redirect($request, $params=null, $rooturl=null, $neaturls=null, $anchor=null)
/*
	Redirect the user's web browser to $request and then we're done - see as_path() for other parameters
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		as_redirect_raw(as_path($request, $params, $rooturl, $neaturls, $anchor));
	}
	
	
	function as_redirect_raw($url)
/*
	Redirect the user's web browser to page $path which is already a URL
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		header('Location: '.$url);
		as_exit('redirect');
	}
	

//	General utilities

	function as_retrieve_url($url)
/*
	Return the contents of remote $url, using file_get_contents() if possible, otherwise curl functions
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		$contents=@file_get_contents($url);
		
		if ((!strlen($contents)) && function_exists('curl_exec')) { // try curl as a backup (if allow_url_fopen not set)
			$curl=curl_init($url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			$contents=@curl_exec($curl);
			curl_close($curl);
		}
		
		return $contents;
	}


	function as_opt($name, $value=null)
/*
	Shortcut to get or set an option value without specifying database
*/
	{
		global $as_options_cache;
		
		if ((!isset($value)) && isset($as_options_cache[$name]))
			return $as_options_cache[$name]; // quick shortcut to reduce calls to as_get_options()
		
		require_once AS_INCLUDE_DIR.'as-app-options.php';
		
		if (isset($value))
			as_set_option($name, $value);	
		
		$options=as_get_options(array($name));

		return $options[$name];
	}
	
	
//	Event and process stage reporting

	function as_suspend_event_reports($suspend=true)
/*
	Suspend the reporting of events to event modules via as_report_event(...) if $suspend is
	true, otherwise reinstate it. A counter is kept to allow multiple calls.
*/
	{
		global $as_event_reports_suspended;
		
		$as_event_reports_suspended+=($suspend ? 1 : -1);
	}
	
	
	function as_report_event($event, $userid, $handle, $cookieid, $params=array())
/*
	Send a notification of event $event by $userid, $handle and $cookieid to all event modules, with extra $params
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_event_reports_suspended;
		
		if ($as_event_reports_suspended>0)
			return;
		
		$eventmodules=as_load_modules_with('event', 'process_event');
		foreach ($eventmodules as $eventmodule)
			$eventmodule->process_event($event, $userid, $handle, $cookieid, $params);
	}
	
	
	function as_report_process_stage($method) // can have extra params
	{
		global $as_process_reports_suspended;
		
		if (@$as_process_reports_suspended)
			return;
			
		$as_process_reports_suspended=true; // prevent loop, e.g. because of an error
		
		$args=func_get_args();
		$args=array_slice($args, 1);
		
		$processmodules=as_load_modules_with('process', $method);
		foreach ($processmodules as $processmodule)
			call_user_func_array(array($processmodule, $method), $args);

		$as_process_reports_suspended=null;
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/