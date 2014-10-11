<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-db-admin.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Database access functions which are specific to the admin center


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

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}


	function as_db_mysql_version()
/*
	Return the current version of MySQL
*/
	{
		return as_db_read_one_value(as_db_query_raw('SELECT VERSION()'));
	}
	
	
	function as_db_table_size()
/*
	Return the total size in bytes of all relevant tables in the Q2A database
*/
	{
		if (defined('QA_MYSQL_USERS_PREFIX')) { // check if one of the prefixes is a prefix itself of the other
			if (stripos(QA_MYSQL_USERS_PREFIX, QA_MYSQL_TABLE_PREFIX)===0)
				$prefixes=array(QA_MYSQL_TABLE_PREFIX);
			elseif (stripos(QA_MYSQL_TABLE_PREFIX, QA_MYSQL_USERS_PREFIX)===0)
				$prefixes=array(QA_MYSQL_USERS_PREFIX);
			else
				$prefixes=array(QA_MYSQL_TABLE_PREFIX, QA_MYSQL_USERS_PREFIX);
		
		} else
			$prefixes=array(QA_MYSQL_TABLE_PREFIX);
			
		$size=0;
		foreach ($prefixes as $prefix) {
			$statuses=as_db_read_all_assoc(as_db_query_raw(
				"SHOW TABLE STATUS LIKE '".$prefix."%'"
			));

			foreach ($statuses as $status)
				$size+=$status['Data_length']+$status['Index_length'];
		}
		
		return $size;
	}
	
	
	function as_db_count_posts($type=null, $fromuser=null)
/*
	Return a count of the number of posts of $type in database.
	Set $fromuser to true to only count non-anonymous posts, false to only count anonymous posts
*/
	{
		$wheresql='';
		
		if (isset($type))
			$wheresql.=' WHERE type='.as_db_argument_to_mysql($type, true);
		
		if (isset($fromuser))
			$wheresql.=(strlen($wheresql) ? ' AND' : ' WHERE').' userid '.($fromuser ? 'IS NOT' : 'IS').' NULL';
		
		return as_db_read_one_value(as_db_query_sub(
			'SELECT COUNT(*) FROM ^posts'.$wheresql
		));
	}


	function as_db_count_users()
/*
	Return number of registered users in database.
*/
	{
		return as_db_read_one_value(as_db_query_sub(
			'SELECT COUNT(*) FROM ^users'
		));
	}
	

	function as_db_count_active_users($table)
/*
	Return number of active users in database $table
*/
	{
		switch ($table) {
			case 'posts':
			case 'uservotes':
			case 'userpoints':
				break;
				
			default:
				as_fatal_error('as_db_count_active_users() called for unknown table');
				break;
		}
		
		return as_db_read_one_value(as_db_query_sub(
			'SELECT COUNT(DISTINCT(userid)) FROM ^'.$table
		));
	}
	
	
	function as_db_count_categories()
/*
	Return number of categories in the database
*/
	{
		return as_db_read_one_value(as_db_query_sub(
			'SELECT COUNT(*) FROM ^categories'
		));
	}
	
	
	function as_db_count_categoryid_qs($categoryid)
/*
	Return number of questions in the database in $categoryid exactly, and not one of its subcategories 
*/
	{
		return as_db_read_one_value(as_db_query_sub(
			"SELECT COUNT(*) FROM ^posts WHERE categoryid<=># AND type='Q'",
			$categoryid
		));
	}
	
	
	function as_db_get_user_visible_postids($userid)
/*
	Return list of postids of visible or queued posts by $userid
*/
	{
		return as_db_read_all_values(as_db_query_sub(
			"SELECT postid FROM ^posts WHERE userid=# AND type IN ('Q', 'A', 'C', 'Q_QUEUED', 'A_QUEUED', 'C_QUEUED')",
			$userid
		));
	}
	
	
	function as_db_get_ip_visible_postids($ip)
/*
	Return list of postids of visible or queued posts from $ip address
*/
	{
		return as_db_read_all_values(as_db_query_sub(
			"SELECT postid FROM ^posts WHERE createip=INET_ATON($) AND type IN ('Q', 'A', 'C', 'Q_QUEUED', 'A_QUEUED', 'C_QUEUED')",
			$ip
		));
	}
	
	
	function as_db_postids_count_dependents($postids)
/*
	Return an array whose keys contain the $postids which exist, and whose elements contain the number of other posts depending on each one
*/
	{
		if (count($postids))
			return as_db_read_all_assoc(as_db_query_sub(
				"SELECT postid, COALESCE(childcount, 0) AS count FROM ^posts LEFT JOIN (SELECT parentid, COUNT(*) AS childcount FROM ^posts WHERE parentid IN (#) AND LEFT(type, 1) IN ('A', 'C') GROUP BY parentid) x ON postid=x.parentid WHERE postid IN (#)",
				$postids, $postids
			), 'postid', 'count');
		else
			return array();
	}
	
	
	function as_db_get_unapproved_users($count)
/*
	Return an array of the (up to) $count most recently created users who are awaiting approval and have not been blocked.
	The array element for each user includes a 'profile' key whose value is an array of non-empty profile fields of the user.
*/
	{
		$results=as_db_read_all_assoc(as_db_query_sub(
			"SELECT ^users.userid, UNIX_TIMESTAMP(created) AS created, INET_NTOA(createip) AS createip, email, handle, flags, title, content FROM ^users LEFT JOIN ^userprofile ON ^users.userid=^userprofile.userid AND LENGTH(content)>0 WHERE level<# AND NOT (flags&#) ORDER BY created DESC LIMIT #",
			QA_USER_LEVEL_APPROVED, QA_USER_FLAGS_USER_BLOCKED, $count
		));
		
		$users=array();
		
		foreach ($results as $result) {
			$userid=$result['userid'];
			
			if (!isset($users[$userid])) {
				$users[$result['userid']]=$result;
				$users[$result['userid']]['profile']=array();
				unset($users[$userid]['title']);
				unset($users[$userid]['content']);
			}
			
			if (isset($result['title']) && isset($result['content']))
				$users[$userid]['profile'][$result['title']]=$result['content'];
		}
		
		return $users;
	}
	
	
	function as_db_has_blobs_on_disk()
/*
	Return whether there are any blobs whose content has been stored as a file on disk
*/
	{
		return count(as_db_read_all_values(as_db_query_sub('SELECT blobid FROM ^blobs WHERE content IS NULL LIMIT 1'))) ? true : false;
	}
	
	
	function as_db_has_blobs_in_db()
/*
	Return whether there are any blobs whose content has been stored in the database
*/
	{
		return count(as_db_read_all_values(as_db_query_sub('SELECT blobid FROM ^blobs WHERE content IS NOT NULL LIMIT 1'))) ? true : false;
	}

	
	function as_db_category_last_pos($parentid)
/*
	Return the maximum position of the categories with $parentid
*/
	{
		return as_db_read_one_value(as_db_query_sub(
			'SELECT COALESCE(MAX(position), 0) FROM ^categories WHERE parentid<=>#',
			$parentid
		));
	}
	
	
	function as_db_category_child_depth($categoryid)
/*
	Return how many levels of subcategory there are below $categoryid
*/
	{
		// This is potentially a very slow query since it counts all the multi-generational offspring of a particular category
		// But it's only used for admin purposes when moving a category around so I don't think it's worth making more efficient
		// (Incidentally, this could be done by keeping a count for every category of how many generations of offspring it has.)
		
		$result=as_db_read_one_assoc(as_db_query_sub(
			'SELECT COUNT(child1.categoryid) AS count1, COUNT(child2.categoryid) AS count2, COUNT(child3.categoryid) AS count3 FROM ^categories AS child1 LEFT JOIN ^categories AS child2 ON child2.parentid=child1.categoryid LEFT JOIN ^categories AS child3 ON child3.parentid=child2.categoryid WHERE child1.parentid=#;', // requires QA_CATEGORY_DEPTH=4
			$categoryid
		));
		
		for ($depth=QA_CATEGORY_DEPTH-1; $depth>=1; $depth--)
			if ($result['count'.$depth])
				return $depth;
		
		return 0;
	}
	
	
	function as_db_category_create($parentid, $title, $tags)
/*
	Create a new category with $parentid, $title (=name) and $tags (=slug) in the database
*/
	{
		$lastpos=as_db_category_last_pos($parentid);

		as_db_query_sub(
			'INSERT INTO ^categories (parentid, title, tags, position) VALUES (#, $, $, #)',
			$parentid, $title, $tags, 1+$lastpos
		);
		
		$categoryid=as_db_last_insert_id();
		
		as_db_categories_recalc_backpaths($categoryid);
		
		return $categoryid;
	}
	
	
	function as_db_categories_recalc_backpaths($firstcategoryid, $lastcategoryid=null)
/*
	Recalculate the backpath columns for all categories from $firstcategoryid to $lastcategoryid (if specified)
*/
	{
		if (!isset($lastcategoryid))
			$lastcategoryid=$firstcategoryid;

		as_db_query_sub(
			"UPDATE ^categories AS x, (SELECT cat1.categoryid, CONCAT_WS('/', cat1.tags, cat2.tags, cat3.tags, cat4.tags) AS backpath FROM ^categories AS cat1 LEFT JOIN ^categories AS cat2 ON cat1.parentid=cat2.categoryid LEFT JOIN ^categories AS cat3 ON cat2.parentid=cat3.categoryid LEFT JOIN ^categories AS cat4 ON cat3.parentid=cat4.categoryid WHERE cat1.categoryid BETWEEN # AND #) AS a SET x.backpath=a.backpath WHERE x.categoryid=a.categoryid",
			$firstcategoryid, $lastcategoryid // requires QA_CATEGORY_DEPTH=4
		);
	}


	function as_db_category_rename($categoryid, $title, $tags)
/*
	Set the name of $categoryid to $title and its slug to $tags in the database
*/
	{
		as_db_query_sub(
			'UPDATE ^categories SET title=$, tags=$ WHERE categoryid=#',
			$title, $tags, $categoryid
		);
		
		as_db_categories_recalc_backpaths($categoryid); // may also require recalculation of its offspring's backpaths
	}
	
	
	function as_db_category_set_content($categoryid, $content)
/*
	Set the content (=description) of $categoryid to $content
*/
	{
		as_db_query_sub(
			'UPDATE ^categories SET content=$ WHERE categoryid=#',
			$content, $categoryid
		);
	}
	
	
	function as_db_category_get_parent($categoryid)
/*
	Return the parentid of $categoryid
*/
	{
		return as_db_read_one_value(as_db_query_sub(
			'SELECT parentid FROM ^categories WHERE categoryid=#',
			$categoryid
		));
	}
	
	
	function as_db_category_set_position($categoryid, $newposition)
/*
	Move the category $categoryid into position $newposition under its parent
*/
	{
		as_db_ordered_move('categories', 'categoryid', $categoryid, $newposition,
			as_db_apply_sub('parentid<=>#', array(as_db_category_get_parent($categoryid))));
	}
	
	
	function as_db_category_set_parent($categoryid, $newparentid)
/*
	Set the parent of $categoryid to $newparentid, placing it in last position (doesn't do necessary recalculations)
*/
	{
		$oldparentid=as_db_category_get_parent($categoryid);
		
		if (strcmp($oldparentid, $newparentid)) { // if we're changing parent, move to end of old parent, then end of new parent
			$lastpos=as_db_category_last_pos($oldparentid);
			
			as_db_ordered_move('categories', 'categoryid', $categoryid, $lastpos, as_db_apply_sub('parentid<=>#', array($oldparentid)));
			
			$lastpos=as_db_category_last_pos($newparentid);
			
			as_db_query_sub(
				'UPDATE ^categories SET parentid=#, position=# WHERE categoryid=#',
				$newparentid, 1+$lastpos, $categoryid
			);
		}
	}
	
	
	function as_db_category_reassign($categoryid, $reassignid)
/*
	Change the categoryid of any posts with (exact) $categoryid to $reassignid
*/
	{
		as_db_query_sub('UPDATE ^posts SET categoryid=# WHERE categoryid<=>#', $reassignid, $categoryid);
	}
	
	
	function as_db_category_delete($categoryid)
/*
	Delete the category $categoryid in the database
*/
	{
		as_db_ordered_delete('categories', 'categoryid', $categoryid,
			as_db_apply_sub('parentid<=>#', array(as_db_category_get_parent($categoryid))));
	}
	
	
	function as_db_category_slug_to_id($parentid, $slug)
/*
	Return the categoryid for the category with parent $parentid and $slug
*/
	{
		return as_db_read_one_value(as_db_query_sub(
			'SELECT categoryid FROM ^categories WHERE parentid<=># AND tags=$',
			$parentid, $slug
		), true);
	}
	
	
	function as_db_page_create($title, $flags, $tags, $heading, $content, $permit=null)
/*
	Create a new custom page (or link) in the database
*/
	{
		$position=as_db_read_one_value(as_db_query_sub('SELECT 1+COALESCE(MAX(position), 0) FROM ^pages'));
		
		as_db_query_sub(
			'INSERT INTO ^pages (title, nav, flags, permit, tags, heading, content, position) VALUES ($, \'\', #, #, $, $, $, #)',
			$title, $flags, $permit, $tags, $heading, $content, $position
		);
		
		return as_db_last_insert_id();
	}
	
	
	function as_db_page_set_fields($pageid, $title, $flags, $tags, $heading, $content, $permit=null)
/*
	Set the fields of $pageid to the values provided in the database
*/
	{
		as_db_query_sub(
			'UPDATE ^pages SET title=$, flags=#, permit=#, tags=$, heading=$, content=$ WHERE pageid=#',
			$title, $flags, $permit, $tags, $heading, $content, $pageid
		);
	}
	
	
	function as_db_page_move($pageid, $nav, $newposition)
/*
	Move the page $pageid into navigation menu $nav and position $newposition in the database
*/
	{
		as_db_query_sub(
			'UPDATE ^pages SET nav=$ WHERE pageid=#',
			$nav, $pageid
		);

		as_db_ordered_move('pages', 'pageid', $pageid, $newposition);
	}
	
	
	function as_db_page_delete($pageid)
/*
	Delete the page $pageid in the database
*/
	{
		as_db_ordered_delete('pages', 'pageid', $pageid);
	}
	
	
	function as_db_ordered_move($table, $idcolumn, $id, $newposition, $conditionsql=null)
/*
	Move the entity identified by $idcolumn=$id into position $newposition (within optional $conditionsql) in $table in the database
*/
	{
		$andsql=isset($conditionsql) ? (' AND '.$conditionsql) : '';
		
		as_db_query_sub('LOCK TABLES ^'.$table.' WRITE');
		
		$oldposition=as_db_read_one_value(as_db_query_sub('SELECT position FROM ^'.$table.' WHERE '.$idcolumn.'=#'.$andsql, $id));
		
		if ($newposition!=$oldposition) {
			$lastposition=as_db_read_one_value(as_db_query_sub('SELECT MAX(position) FROM ^'.$table.' WHERE TRUE'.$andsql));
			
			$newposition=max(1, min($newposition, $lastposition)); // constrain it to within range
			
			as_db_query_sub('UPDATE ^'.$table.' SET position=# WHERE '.$idcolumn.'=#'.$andsql, 1+$lastposition, $id);
				// move it temporarily off the top because we have a unique key on the position column
			
			if ($newposition<$oldposition)
				as_db_query_sub('UPDATE ^'.$table.' SET position=position+1 WHERE position BETWEEN # AND #'.$andsql.' ORDER BY position DESC', $newposition, $oldposition);
			else
				as_db_query_sub('UPDATE ^'.$table.' SET position=position-1 WHERE position BETWEEN # AND #'.$andsql.' ORDER BY position', $oldposition, $newposition);
	
			as_db_query_sub('UPDATE ^'.$table.' SET position=# WHERE '.$idcolumn.'=#'.$andsql, $newposition, $id);
		}
		
		as_db_query_sub('UNLOCK TABLES');
	}
	
	
	function as_db_ordered_delete($table, $idcolumn, $id, $conditionsql=null)
/*
	Delete the entity identified by $idcolumn=$id (and optional $conditionsql) in $table in the database
*/
	{
		$andsql=isset($conditionsql) ? (' AND '.$conditionsql) : '';

		as_db_query_sub('LOCK TABLES ^'.$table.' WRITE');
		
		$oldposition=as_db_read_one_value(as_db_query_sub('SELECT position FROM ^'.$table.' WHERE '.$idcolumn.'=#'.$andsql, $id));
		
		as_db_query_sub('DELETE FROM ^'.$table.' WHERE '.$idcolumn.'=#'.$andsql, $id);
		
		as_db_query_sub('UPDATE ^'.$table.' SET position=position-1 WHERE position>#'.$andsql.' ORDER BY position', $oldposition);
		
		as_db_query_sub('UNLOCK TABLES');
	}
	
	
	function as_db_userfield_create($title, $content, $flags, $permit=null)
/*
	Create a new user field with (internal) tag $title, label $content, $flags and $permit in the database.
*/
	{
		$position=as_db_read_one_value(as_db_query_sub('SELECT 1+COALESCE(MAX(position), 0) FROM ^userfields'));
		
		as_db_query_sub(
			'INSERT INTO ^userfields (title, content, position, flags, permit) VALUES ($, $, #, #, #)',
			$title, $content, $position, $flags, $permit
		);

		return as_db_last_insert_id();
	}
	
	
	function as_db_userfield_set_fields($fieldid, $content, $flags, $permit=null)
/*
	Change the user field $fieldid to have label $content, $flags and $permit in the database (the title column cannot be changed once set)
*/
	{
		as_db_query_sub(
			'UPDATE ^userfields SET content=$, flags=#, permit=# WHERE fieldid=#',
			$content, $flags, $permit, $fieldid
		);
	}
	
	
	function as_db_userfield_move($fieldid, $newposition)
/*
	Move the user field $fieldid into position $newposition in the database
*/
	{
		as_db_ordered_move('userfields', 'fieldid', $fieldid, $newposition);
	}

	
	function as_db_userfield_delete($fieldid)
/*
	Delete the user field $fieldid in the database
*/
	{
		as_db_ordered_delete('userfields', 'fieldid', $fieldid);
	}
	
	
	function as_db_widget_create($title, $tags)
/*
	Return the ID of a new widget, to be displayed by the widget module named $title on templates within $tags (comma-separated list)
*/
	{
		$position=as_db_read_one_value(as_db_query_sub('SELECT 1+COALESCE(MAX(position), 0) FROM ^widgets'));
		
		as_db_query_sub(
			'INSERT INTO ^widgets (place, position, tags, title) VALUES (\'\', #, $, $)',
			$position, $tags, $title
		);
		
		return as_db_last_insert_id();
	}
	
	
	function as_db_widget_set_fields($widgetid, $tags)
/*
	Set the comma-separated list of templates for $widgetid to $tags
*/
	{
		as_db_query_sub(
			'UPDATE ^widgets SET tags=$ WHERE widgetid=#',
			$tags, $widgetid
		);
	}
	
	
	function as_db_widget_move($widgetid, $place, $newposition)
/*
	Move the widget $widgetit into position $position in the database's order, and show it in $place on the page
*/
	{
		as_db_query_sub(
			'UPDATE ^widgets SET place=$ WHERE widgetid=#',
			$place, $widgetid
		);

		as_db_ordered_move('widgets', 'widgetid', $widgetid, $newposition);
	}
	
	
	function as_db_widget_delete($widgetid)
/*
	Delete the widget $widgetid in the database
*/
	{
		as_db_ordered_delete('widgets', 'widgetid', $widgetid);
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/