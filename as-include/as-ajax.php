<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-ajax.php
	Version: See define()s at top of as-include/as-base.php
	Description: Front line of response to Ajax requests, routing as appropriate


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

//	Output this header as early as possible

	header('Content-Type: text/plain; charset=utf-8');


//	Ensure no PHP errors are shown in the Ajax response

	@ini_set('display_errors', 0);


//	Load the Q2A base file which sets up a bunch of crucial functions

	require 'as-base.php';

	as_report_process_stage('init_ajax');
		

//	Get general Ajax parameters from the POST payload, and clear $_GET

	as_set_request(as_post_text('as_request'), as_post_text('as_root'));

	$_GET=array(); // for as_self_html()
	

//	Database failure handler

	function as_ajax_db_fail_handler()
	{
		echo "AS_AJAX_RESPONSE\n0\nA database error occurred.";
		as_exit('error');
	}


//	Perform the appropriate Ajax operation

	$routing=array(
		'notice' => 'as-ajax-notice.php',
		'favorite' => 'as-ajax-favorite.php',
		'vote' => 'as-ajax-vote.php',
		'recalc' => 'as-ajax-recalc.php',
		'mailing' => 'as-ajax-mailing.php',
		'version' => 'as-ajax-version.php',
		'category' => 'as-ajax-category.php',
		'asktitle' => 'as-ajax-asktitle.php',
		'answer' => 'as-ajax-answer.php',
		'comment' => 'as-ajax-comment.php',
		'click_a' => 'as-ajax-click-answer.php',
		'click_c' => 'as-ajax-click-comment.php',
		'click_admin' => 'as-ajax-click-admin.php',
		'show_cs' => 'as-ajax-show-comments.php',
		'wallpost' => 'as-ajax-wallpost.php',
		'click_wall' => 'as-ajax-click-wall.php',
	);
	
	$operation=as_post_text('as_operation');
	
	if (isset($routing[$operation])) {
		as_db_connect('as_ajax_db_fail_handler');

		require AS_INCLUDE_DIR.$routing[$operation];
		
		as_db_disconnect();
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/