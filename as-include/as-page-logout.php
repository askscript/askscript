<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-logout.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for logout page (not much to do)


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


	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User logout is handled by external code');
	
	if (as_is_logged_in())
		as_set_logged_in_user(null);
		
	as_redirect(''); // back to home page
	

/*
	Omit PHP closing tag to help avoid accidental output
*/