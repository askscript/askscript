<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-db-post-create.php
	Version: See define()s at top of as-include/as-base.php
	Description: Database functions for creating a question, answer or comment


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


	function as_db_post_create($type, $parentid, $userid, $cookieid, $ip, $title, $content, $format, $tagstring, $notify, $categoryid=null, $name=null)
/*
	Create a new post in the database and return its ID (based on auto-incrementing)
*/
	{
		as_db_query_sub(
			'INSERT INTO ^posts (categoryid, type, parentid, userid, cookieid, createip, title, content, format, tags, notify, name, created) '.
			'VALUES (#, $, #, $, #, INET_ATON($), $, $, $, $, $, $, NOW())',
			$categoryid, $type, $parentid, $userid, $cookieid, $ip, $title, $content, $format, $tagstring, $notify, $name
		);
		
		return as_db_last_insert_id();
	}

	
	function as_db_posts_calc_category_path($firstpostid, $lastpostid=null)
/*
	Recalculate the full category path (i.e. columns catidpath1/2/3) for posts from $firstpostid to $lastpostid (if specified)
*/
	{
		if (!isset($lastpostid))
			$lastpostid=$firstpostid;
		
		as_db_query_sub(
			"UPDATE ^posts AS x, (SELECT ^posts.postid, ".
				"COALESCE(parent2.parentid, parent1.parentid, parent0.parentid, parent0.categoryid) AS catidpath1, ".
				"IF (parent2.parentid IS NOT NULL, parent1.parentid, IF (parent1.parentid IS NOT NULL, parent0.parentid, IF (parent0.parentid IS NOT NULL, parent0.categoryid, NULL))) AS catidpath2, ".
				"IF (parent2.parentid IS NOT NULL, parent0.parentid, IF (parent1.parentid IS NOT NULL, parent0.categoryid, NULL)) AS catidpath3 ".
				"FROM ^posts LEFT JOIN ^categories AS parent0 ON ^posts.categoryid=parent0.categoryid LEFT JOIN ^categories AS parent1 ON parent0.parentid=parent1.categoryid LEFT JOIN ^categories AS parent2 ON parent1.parentid=parent2.categoryid WHERE ^posts.postid BETWEEN # AND #) AS a SET x.catidpath1=a.catidpath1, x.catidpath2=a.catidpath2, x.catidpath3=a.catidpath3 WHERE x.postid=a.postid",
			$firstpostid, $lastpostid
		); // requires AS_CATEGORY_DEPTH=4
	}
	
	
	function as_db_post_get_category_path($postid)
/*
	Get the full category path (including categoryid) for $postid
*/
	{
		return as_db_read_one_assoc(as_db_query_sub(
			'SELECT categoryid, catidpath1, catidpath2, catidpath3 FROM ^posts WHERE postid=#',
			$postid
		)); // requires AS_CATEGORY_DEPTH=4
	}
	
	
	function as_db_post_acount_update($questionid)
/*
	Update the cached number of answers for $questionid in the database, along with the highest netvotes of any of its answers
*/
	{
		if (as_should_update_counts())
			as_db_query_sub(
				"UPDATE ^posts AS x, (SELECT COUNT(*) AS acount, COALESCE(GREATEST(MAX(netvotes), 0), 0) AS amaxvote FROM ^posts WHERE parentid=# AND type='A') AS a SET x.acount=a.acount, x.amaxvote=a.amaxvote WHERE x.postid=#",
				$questionid, $questionid
			);
	}
	
	
	function as_db_category_path_qcount_update($path)
/*
	Recalculate the number of questions for each category in $path retrieved via as_db_post_get_category_path()
*/
	{
		as_db_ifcategory_qcount_update($path['categoryid']); // requires AS_CATEGORY_DEPTH=4
		as_db_ifcategory_qcount_update($path['catidpath1']);
		as_db_ifcategory_qcount_update($path['catidpath2']);
		as_db_ifcategory_qcount_update($path['catidpath3']);
	}
	
	
	function as_db_ifcategory_qcount_update($categoryid)
/*
	Update the cached number of questions for category $categoryid in the database, including its subcategories
*/
	{
		if (as_should_update_counts() && isset($categoryid)) {
			// This seemed like the most sensible approach which avoids explicitly calculating the category's depth in the hierarchy

			as_db_query_sub(
				"UPDATE ^categories SET qcount=GREATEST( (SELECT COUNT(*) FROM ^posts WHERE categoryid=# AND type='Q'), (SELECT COUNT(*) FROM ^posts WHERE catidpath1=# AND type='Q'), (SELECT COUNT(*) FROM ^posts WHERE catidpath2=# AND type='Q'), (SELECT COUNT(*) FROM ^posts WHERE catidpath3=# AND type='Q') ) WHERE categoryid=#",
				$categoryid, $categoryid, $categoryid, $categoryid, $categoryid
			); // requires AS_CATEGORY_DEPTH=4
		}
	}

	
	function as_db_titlewords_add_post_wordids($postid, $wordids)
/*
	Add rows into the database title index, where $postid contains the words $wordids - this does the same sort
	of thing as as_db_posttags_add_post_wordids() in a different way, for no particularly good reason.
*/
	{
		if (count($wordids)) {
			$rowstoadd=array();
			foreach ($wordids as $wordid)
				$rowstoadd[]=array($postid, $wordid);
			
			as_db_query_sub(
				'INSERT INTO ^titlewords (postid, wordid) VALUES #',
				$rowstoadd
			);
		}
	}

	
	function as_db_contentwords_add_post_wordidcounts($postid, $type, $questionid, $wordidcounts)
/*
	Add rows into the database content index, where $postid (of $type, with the antecedent $questionid)
	has words as per the keys of $wordidcounts, and the corresponding number of those words in the values.
*/
	{
		if (count($wordidcounts)) {
			$rowstoadd=array();
			foreach ($wordidcounts as $wordid => $count)
				$rowstoadd[]=array($postid, $wordid, $count, $type, $questionid);

			as_db_query_sub(
				'INSERT INTO ^contentwords (postid, wordid, count, type, questionid) VALUES #',
				$rowstoadd
			);
		}
	}
	
	
	function as_db_tagwords_add_post_wordids($postid, $wordids)
/*
	Add rows into the database index of individual tag words, where $postid contains the words $wordids
*/
	{
		if (count($wordids)) {
			$rowstoadd=array();
			foreach ($wordids as $wordid)
				$rowstoadd[]=array($postid, $wordid);
			
			as_db_query_sub(
				'INSERT INTO ^tagwords (postid, wordid) VALUES #',
				$rowstoadd
			);
		}
	}

	
	function as_db_posttags_add_post_wordids($postid, $wordids)
/*
	Add rows into the database index of whole tags, where $postid contains the tags $wordids
*/
	{
		if (count($wordids))
			as_db_query_sub(
				'INSERT INTO ^posttags (postid, wordid, postcreated) SELECT postid, wordid, created FROM ^words, ^posts WHERE postid=# AND wordid IN ($)',
				$postid, $wordids
			);
	}

	
	function as_db_word_mapto_ids($words)
/*
	Return an array mapping each word in $words to its corresponding wordid in the database
*/
	{
		if (count($words))
			return as_db_read_all_assoc(as_db_query_sub(
				'SELECT wordid, word FROM ^words WHERE word IN ($)', $words
			), 'word', 'wordid');
		else
			return array();
	}

	
	function as_db_word_mapto_ids_add($words)
/*
	Return an array mapping each word in $words to its corresponding wordid in the database, adding any that are missing
*/
	{
		$wordtoid=as_db_word_mapto_ids($words);
		
		$wordstoadd=array();
		foreach ($words as $word)
			if (!isset($wordtoid[$word]))
				$wordstoadd[]=$word;
		
		if (count($wordstoadd)) {
			as_db_query_sub('LOCK TABLES ^words WRITE'); // to prevent two requests adding the same word
			
			$wordtoid=as_db_word_mapto_ids($words); // map it again in case table content changed before it was locked
			
			$rowstoadd=array();
			foreach ($words as $word)
				if (!isset($wordtoid[$word]))
					$rowstoadd[]=array($word);
				
			as_db_query_sub('INSERT IGNORE INTO ^words (word) VALUES $', $rowstoadd);
			
			as_db_query_sub('UNLOCK TABLES');
			
			$wordtoid=as_db_word_mapto_ids($words); // do it one last time
		}
		
		return $wordtoid;
	}
	

	function as_db_word_titlecount_update($wordids)
/*
	Update the titlecount column in the database for the words in $wordids, based on how many posts they appear in the title of
*/
	{
		if (as_should_update_counts() && count($wordids))
			as_db_query_sub(
				'UPDATE ^words AS x, (SELECT ^words.wordid, COUNT(^titlewords.wordid) AS titlecount FROM ^words LEFT JOIN ^titlewords ON ^titlewords.wordid=^words.wordid WHERE ^words.wordid IN (#) GROUP BY wordid) AS a SET x.titlecount=a.titlecount WHERE x.wordid=a.wordid',
				$wordids
			);
	}

	
	function as_db_word_contentcount_update($wordids)
/*
	Update the contentcount column in the database for the words in $wordids, based on how many posts they appear in the content of
*/
	{
		if (as_should_update_counts() && count($wordids))
			as_db_query_sub(
				'UPDATE ^words AS x, (SELECT ^words.wordid, COUNT(^contentwords.wordid) AS contentcount FROM ^words LEFT JOIN ^contentwords ON ^contentwords.wordid=^words.wordid WHERE ^words.wordid IN (#) GROUP BY wordid) AS a SET x.contentcount=a.contentcount WHERE x.wordid=a.wordid',
				$wordids
			);
	}

	
	function as_db_word_tagwordcount_update($wordids)
/*
	Update the tagwordcount column in the database for the individual tag words in $wordids, based on how many posts they appear in the tags of
*/
	{
		if (as_should_update_counts() && count($wordids))
			as_db_query_sub(
				'UPDATE ^words AS x, (SELECT ^words.wordid, COUNT(^tagwords.wordid) AS tagwordcount FROM ^words LEFT JOIN ^tagwords ON ^tagwords.wordid=^words.wordid WHERE ^words.wordid IN (#) GROUP BY wordid) AS a SET x.tagwordcount=a.tagwordcount WHERE x.wordid=a.wordid',
				$wordids
			);
	}

	
	function as_db_word_tagcount_update($wordids)
/*
	Update the tagcount column in the database for the whole tags in $wordids, based on how many posts they appear as tags of
*/
	{
		if (as_should_update_counts() && count($wordids))
			as_db_query_sub(
				'UPDATE ^words AS x, (SELECT ^words.wordid, COUNT(^posttags.wordid) AS tagcount FROM ^words LEFT JOIN ^posttags ON ^posttags.wordid=^words.wordid WHERE ^words.wordid IN (#) GROUP BY wordid) AS a SET x.tagcount=a.tagcount WHERE x.wordid=a.wordid',
				$wordids
			);
	}

	
	function as_db_qcount_update()
/*
	Update the cached count in the database of the number of questions (excluding hidden/queued)
*/
	{
		if (as_should_update_counts())
			as_db_query_sub("REPLACE ^options (title, content) SELECT 'cache_qcount', COUNT(*) FROM ^posts WHERE type='Q'");
	}


	function as_db_acount_update()
/*
	Update the cached count in the database of the number of answers (excluding hidden/queued)
*/
	{
		if (as_should_update_counts())
			as_db_query_sub("REPLACE ^options (title, content) SELECT 'cache_acount', COUNT(*) FROM ^posts WHERE type='A'");
	}


	function as_db_ccount_update()
/*
	Update the cached count in the database of the number of comments (excluding hidden/queued)
*/
	{
		if (as_should_update_counts())
			as_db_query_sub("REPLACE ^options (title, content) SELECT 'cache_ccount', COUNT(*) FROM ^posts WHERE type='C'");
	}


	function as_db_tagcount_update()
/*
	Update the cached count in the database of the number of different tags used
*/
	{
		if (as_should_update_counts())
			as_db_query_sub("REPLACE ^options (title, content) SELECT 'cache_tagcount', COUNT(*) FROM ^words WHERE tagcount>0");
	}

	
	function as_db_unaqcount_update()
/*
	Update the cached count in the database of the number of unanswered questions (excluding hidden/queued)
*/
	{
		if (as_should_update_counts())
			as_db_query_sub("REPLACE ^options (title, content) SELECT 'cache_unaqcount', COUNT(*) FROM ^posts WHERE type='Q' AND acount=0 AND closedbyid IS NULL");
	}
	
	
	function as_db_unselqcount_update()
/*
	Update the cached count in the database of the number of questions with no answer selected (excluding hidden/queued)
*/
	{
		if (as_should_update_counts())
			as_db_query_sub("REPLACE ^options (title, content) SELECT 'cache_unselqcount', COUNT(*) FROM ^posts WHERE type='Q' AND selchildid IS NULL AND closedbyid IS NULL");
	}
	
	
	function as_db_unupaqcount_update()
/*
	Update the cached count in the database of the number of questions with no upvoted answers (excluding hidden/queued)
*/
	{
		if (as_should_update_counts())
			as_db_query_sub("REPLACE ^options (title, content) SELECT 'cache_unupaqcount', COUNT(*) FROM ^posts WHERE type='Q' AND amaxvote=0 AND closedbyid IS NULL");
	}
	
	
	function as_db_queuedcount_update()
/*
	Update the cached count in the database of the number of posts which are queued for moderation
*/
	{
		if (as_should_update_counts())
			as_db_query_sub("REPLACE ^options (title, content) SELECT 'cache_queuedcount', COUNT(*) FROM ^posts WHERE type IN ('Q_QUEUED', 'A_QUEUED', 'C_QUEUED')");
	}

/*
	Omit PHP closing tag to help avoid accidental output
*/