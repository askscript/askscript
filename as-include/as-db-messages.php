<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-db-messages.php
	Version: See define()s at top of as-include/as-base.php
	Description: Database-level access to messages table for private message history


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


	function as_db_message_create($fromuserid, $touserid, $content, $format, $public=false)
/*
	Record a message sent from $fromuserid to $touserid with $content in $format in the database. $public sets whether
	public (on wall) or private. Return the messageid of the row created.
*/
	{
		as_db_query_sub(
			'INSERT INTO ^messages (type, fromuserid, touserid, content, format, created) VALUES ($, #, #, $, $, NOW())',
			$public ? 'PUBLIC' : 'PRIVATE', $fromuserid, $touserid, $content, $format
		);
		
		return as_db_last_insert_id();
	}
	

	function as_db_message_delete($messageid)
/*
	Delete the message with $messageid from the database
*/
	{
		as_db_query_sub(
			'DELETE FROM ^messages WHERE messageid=#',
			$messageid
		);
	}


	function as_db_user_recount_posts($userid)
/*
	Recalculate the cached count of wall posts for user $userid in the database
*/
	{
		if (as_should_update_counts())
			as_db_query_sub(
				"UPDATE ^users AS x, (SELECT COUNT(*) AS wallposts FROM ^messages WHERE touserid=# AND type='PUBLIC') AS a SET x.wallposts=a.wallposts WHERE x.userid=#",
				$userid, $userid
			);
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/