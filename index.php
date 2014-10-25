<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: index.php
	Version: See define()s at top of as-include/as-base.php
	Description: A stub that only sets up the Q2A root and includes as-index.php


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

//	Set base path here so this works with symbolic links for multiple installations

	define('BASE_DIR', dirname(empty($_SERVER['SCRIPT_FILENAME']) ? __FILE__ : $_SERVER['SCRIPT_FILENAME']).'/');
	
	define('AS_VERSION', '1.6.3'); // also used as suffix for .js and .css requests
	define('AS_BUILD_DATE', '2014-01-19');
	
	require 'as-include/as-load.php';


/*
	Omit PHP closing tag to help avoid accidental output
*/