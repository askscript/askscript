<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-db-notices.php
	Version: See define()s at top of as-include/as-base.php
	Description: Database-level access to usernotices table


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


	function as_db_usernotice_create($userid, $content, $format='', $tags=null)
/*
	Create a notice for $userid with $content in $format and optional $tags (not displayed) and return its noticeid
*/
	{
		as_db_query_sub(
			'INSERT INTO ^usernotices (userid, content, format, tags, created) VALUES ($, $, $, $, NOW())',
			$userid, $content, $format, $tags
		);
		
		return as_db_last_insert_id();
	}
	

	function as_db_usernotice_delete($userid, $noticeid)
/*
	Delete the notice $notice which belongs to $userid
*/
	{
		as_db_query_sub(
			'DELETE FROM ^usernotices WHERE userid=$ AND noticeid=#',
			$userid, $noticeid
		);
	}


	function as_db_usernotices_list($userid)
/*
	Return an array summarizing the notices to be displayed for $userid, including the tags (not displayed)
*/
	{
		return as_db_read_all_assoc(as_db_query_sub(
			'SELECT noticeid, tags, UNIX_TIMESTAMP(created) AS created FROM ^usernotices WHERE userid=$ ORDER BY created',
			$userid
		));
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/