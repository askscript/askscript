<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-ajax-click-admin.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Server-side response to Ajax single clicks on posts in admin section


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

	require_once QA_INCLUDE_DIR.'qa-app-admin.php';
	require_once QA_INCLUDE_DIR.'qa-app-users.php';
	require_once QA_INCLUDE_DIR.'qa-app-cookies.php';


	$entityid=as_post_text('entityid');
	$action=as_post_text('action');

	if (!as_check_form_security_code('admin/click', as_post_text('code')))
		echo "QA_AJAX_RESPONSE\n0\n".as_lang('misc/form_security_reload');
	elseif (as_admin_single_click($entityid, $action)) // permission check happens in here
		echo "QA_AJAX_RESPONSE\n1\n";
	else
		echo "QA_AJAX_RESPONSE\n0\n".as_lang('main/general_error');
				
	
/*
	Omit PHP closing tag to help avoid accidental output
*/