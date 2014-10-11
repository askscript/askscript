<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-search-basic.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Basic module for indexing and searching Q2A posts


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


	class as_search_basic {
	
		function index_post($postid, $type, $questionid, $parentid, $title, $content, $format, $text, $tagstring, $categoryid)
		{
			require_once QA_INCLUDE_DIR.'qa-db-post-create.php';
			
		//	Get words from each textual element
		
			$titlewords=array_unique(as_string_to_words($title));
			$contentcount=array_count_values(as_string_to_words($text));
			$tagwords=array_unique(as_string_to_words($tagstring));
			$wholetags=array_unique(as_tagstring_to_tags($tagstring));
			
		//	Map all words to their word IDs
			
			$words=array_unique(array_merge($titlewords, array_keys($contentcount), $tagwords, $wholetags));
			$wordtoid=as_db_word_mapto_ids_add($words);
			
		//	Add to title words index
			
			$titlewordids=as_array_filter_by_keys($wordtoid, $titlewords);
			as_db_titlewords_add_post_wordids($postid, $titlewordids);
		
		//	Add to content words index (including word counts)
		
			$contentwordidcounts=array();
			foreach ($contentcount as $word => $count)
				if (isset($wordtoid[$word]))
					$contentwordidcounts[$wordtoid[$word]]=$count;
	
			as_db_contentwords_add_post_wordidcounts($postid, $type, $questionid, $contentwordidcounts);
			
		//	Add to tag words index
		
			$tagwordids=as_array_filter_by_keys($wordtoid, $tagwords);
			as_db_tagwords_add_post_wordids($postid, $tagwordids);
		
		//	Add to whole tags index
	
			$wholetagids=as_array_filter_by_keys($wordtoid, $wholetags);
			as_db_posttags_add_post_wordids($postid, $wholetagids);
			
		//	Update counts cached in database (will be skipped if as_suspend_update_counts() was called
			
			as_db_word_titlecount_update($titlewordids);
			as_db_word_contentcount_update(array_keys($contentwordidcounts));
			as_db_word_tagwordcount_update($tagwordids);
			as_db_word_tagcount_update($wholetagids);
			as_db_tagcount_update();
		}
		

		function unindex_post($postid)
		{
			require_once QA_INCLUDE_DIR.'qa-db-post-update.php';

			$titlewordids=as_db_titlewords_get_post_wordids($postid);
			as_db_titlewords_delete_post($postid);
			as_db_word_titlecount_update($titlewordids);
	
			$contentwordids=as_db_contentwords_get_post_wordids($postid);
			as_db_contentwords_delete_post($postid);
			as_db_word_contentcount_update($contentwordids);
			
			$tagwordids=as_db_tagwords_get_post_wordids($postid);
			as_db_tagwords_delete_post($postid);
			as_db_word_tagwordcount_update($tagwordids);
	
			$wholetagids=as_db_posttags_get_post_wordids($postid);
			as_db_posttags_delete_post($postid);
			as_db_word_tagcount_update($wholetagids);
		}

		
		function move_post($postid, $categoryid)
		{
			// for now, the built-in search engine ignores categories
		}
		
		
		function index_page($pageid, $request, $title, $content, $format, $text)
		{
			// for now, the built-in search engine ignores custom pages
		}
		
		
		function unindex_page($pageid)
		{
			// for now, the built-in search engine ignores custom pages
		}
		
		
		function process_search($query, $start, $count, $userid, $absoluteurls, $fullcontent)
		{
			require_once QA_INCLUDE_DIR.'qa-db-selects.php';
			require_once QA_INCLUDE_DIR.'qa-util-string.php';

			$words=as_string_to_words($query);
			
			$questions=as_db_select_with_pending(
				as_db_search_posts_selectspec($userid, $words, $words, $words, $words, trim($query), $start, $fullcontent, $count)
			);
			
			$results=array();
			
			foreach ($questions as $question) {
				as_search_set_max_match($question, $type, $postid); // to link straight to best part

				$results[]=array(
					'question' => $question,
					'match_type' => $type,
					'match_postid' => $postid,
				);
			}
			
			return $results;
		}
	
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/