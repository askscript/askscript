<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-db-cookies.php
	Version: See define()s at top of as-include/as-base.php
	Description: Database access functions for user cookies


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


	function as_db_cookie_create($ipaddress)
/*
	Create a new random cookie for $ipaddress and insert into database, returning it
*/
	{
		for ($attempt=0; $attempt<10; $attempt++) {
			$cookieid=as_db_random_bigint();
			
			if (as_db_cookie_exists($cookieid))
				continue;

			as_db_query_sub(
				'INSERT INTO ^cookies (cookieid, created, createip) '.
					'VALUES (#, NOW(), COALESCE(INET_ATON($), 0))',
				$cookieid, $ipaddress
			);
		
			return $cookieid;
		}
		
		return null;
	}

	
	function as_db_cookie_written($cookieid, $ipaddress)
/*
	Note in database that a write operation has been done by user identified by $cookieid and from $ipaddress
*/
	{
		as_db_query_sub(
			'UPDATE ^cookies SET written=NOW(), writeip=COALESCE(INET_ATON($), 0) WHERE cookieid=#',
			$ipaddress, $cookieid
		);
	}

	
	function as_db_cookie_exists($cookieid)
/*
	Return whether $cookieid exists in database
*/
	{
		return as_db_read_one_value(as_db_query_sub(
			'SELECT COUNT(*) FROM ^cookies WHERE cookieid=#',
			$cookieid
		)) > 0;
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/