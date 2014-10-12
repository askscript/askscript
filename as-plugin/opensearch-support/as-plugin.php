<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-plugin/opensearch-support/as-plugin.php
	Version: See define()s at top of as-include/as-base.php
	Description: Initiates OpenSearch plugin


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

/*
	Plugin Name: OpenSearch Support
	Plugin URI: 
	Plugin Description: Allows OpenSearch clients to search Q2A site directly
	Plugin Version: 1.0
	Plugin Date: 2012-08-21
	Plugin Author: Question2Answer
	Plugin Author URI: http://www.question2answer.org/
	Plugin License: GPLv2
	Plugin Minimum Question2Answer Version: 1.5
	Plugin Update Check URI: 
*/


	if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../../');
		exit;
	}

	
	as_register_plugin_layer('as-opensearch-layer.php', 'OpenSearch Layer');
	as_register_plugin_module('page', 'as-opensearch-page.php', 'as_opensearch_xml', 'OpenSearch XML');
	

/*
	Omit PHP closing tag to help avoid accidental output
*/