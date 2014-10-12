<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-app-recalc.php
	Version: See define()s at top of as-include/as-base.php
	Description: Managing database recalculations (clean-up operations) and status messages


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
	A full list of redundant (non-normal) information in the database that can be recalculated:
	
	Recalculated in doreindexcontent:
	================================
	^titlewords (all): index of words in titles of posts
	^contentwords (all): index of words in content of posts
	^tagwords (all): index of words in tags of posts (a tag can contain multiple words)
	^posttags (all): index tags of posts
	^words (all): list of words used for indexes
	^options (title=cache_*): cached values for various things (e.g. counting questions)
	
	Recalculated in dorecountposts:
	==============================
	^posts (upvotes, downvotes, netvotes, hotness, acount, amaxvotes, flagcount): number of votes, hotness, answers, answer votes, flags
	
	Recalculated in dorecalcpoints:
	===============================
	^userpoints (all except bonus): points calculation for all users
	^options (title=cache_userpointscount):
	
	Recalculated in dorecalccategories:
	===================================
	^posts (categoryid): assign to answers and comments based on their antecedent question
	^posts (catidpath1, catidpath2, catidpath3): hierarchical path to category ids (requires AS_CATEGORY_DEPTH=4)
	^categories (qcount): number of (visible) questions in each category
	^categories (backpath): full (backwards) path of slugs to that category
	
	Recalculated in dorebuildupdates:
	=================================
	^sharedevents (all): per-entity event streams (see big comment in as-db-favorites.php)
	^userevents (all): per-subscriber event streams
	
	[but these are not entirely redundant since they can contain historical information no longer in ^posts]
*/

	if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once AS_INCLUDE_DIR.'as-db-recalc.php';
	require_once AS_INCLUDE_DIR.'as-db-post-create.php';
	require_once AS_INCLUDE_DIR.'as-db-points.php';
	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-db-admin.php';
	require_once AS_INCLUDE_DIR.'as-db-users.php';
	require_once AS_INCLUDE_DIR.'as-app-options.php';
	require_once AS_INCLUDE_DIR.'as-app-post-create.php';
	require_once AS_INCLUDE_DIR.'as-app-post-update.php';


	function as_recalc_perform_step(&$state)
/*
	Advance the recalculation operation represented by $state by a single step.
	$state can also be the name of a recalculation operation on its own.
*/
	{
		$continue=false;
		
		@list($operation, $length, $next, $done)=explode("\t", $state);
		
		switch ($operation) {
			case 'doreindexcontent':
				as_recalc_transition($state, 'doreindexcontent_pagereindex');
				break;
				
			case 'doreindexcontent_pagereindex':
				$pages=as_db_pages_get_for_reindexing($next, 10);
				
				if (count($pages)) {
					require_once AS_INCLUDE_DIR.'as-app-format.php';
					
					$lastpageid=max(array_keys($pages));
					
					foreach ($pages as $pageid => $page)
						if (!($page['flags'] & AS_PAGE_FLAGS_EXTERNAL)) {
							$searchmodules=as_load_modules_with('search', 'unindex_page');
							foreach ($searchmodules as $searchmodule)
								$searchmodule->unindex_page($pageid);
								
							$searchmodules=as_load_modules_with('search', 'index_page');
							if (count($searchmodules)) {
								$indextext=as_viewer_text($page['content'], 'html');

								foreach ($searchmodules as $searchmodule)
									$searchmodule->index_page($pageid, $page['tags'], $page['heading'], $page['content'], 'html', $indextext);
							}
						}
						
					$next=1+$lastpageid;
					$done+=count($pages);
					$continue=true;
				
				} else
					as_recalc_transition($state, 'doreindexcontent_postcount');
				break;
			
			case 'doreindexcontent_postcount':
				as_db_qcount_update();
				as_db_acount_update();
				as_db_ccount_update();

				as_recalc_transition($state, 'doreindexcontent_postreindex');
				break;
				
			case 'doreindexcontent_postreindex':
				$posts=as_db_posts_get_for_reindexing($next, 10);
				
				if (count($posts)) {
					require_once AS_INCLUDE_DIR.'as-app-format.php';

					$lastpostid=max(array_keys($posts));
					
					as_db_prepare_for_reindexing($next, $lastpostid);
					as_suspend_update_counts();
		
					foreach ($posts as $postid => $post) {
						as_post_unindex($postid);
						as_post_index($postid, $post['type'], $post['questionid'], $post['parentid'], $post['title'], $post['content'],
							$post['format'], as_viewer_text($post['content'], $post['format']), $post['tags'], $post['categoryid']);
					}
					
					$next=1+$lastpostid;
					$done+=count($posts);
					$continue=true;

				} else {
					as_db_truncate_indexes($next);
					as_recalc_transition($state, 'doreindexposts_wordcount');
				}
				break;
				
			case 'doreindexposts_wordcount':
				$wordids=as_db_words_prepare_for_recounting($next, 1000);
				
				if (count($wordids)) {
					$lastwordid=max($wordids);
					
					as_db_words_recount($next, $lastwordid);
					
					$next=1+$lastwordid;
					$done+=count($wordids);
					$continue=true;
			
				} else {
					as_db_tagcount_update(); // this is quick so just do it here
					as_recalc_transition($state, 'doreindexposts_complete');
				}
				break;
				
			case 'dorecountposts':
				as_recalc_transition($state, 'dorecountposts_postcount');
				break;
				
			case 'dorecountposts_postcount':
				as_db_qcount_update();
				as_db_acount_update();
				as_db_ccount_update();
				as_db_unaqcount_update();
				as_db_unselqcount_update();

				as_recalc_transition($state, 'dorecountposts_votecount');
				break;
				
			case 'dorecountposts_votecount':
				$postids=as_db_posts_get_for_recounting($next, 1000);
				
				if (count($postids)) {
					$lastpostid=max($postids);
					
					as_db_posts_votes_recount($next, $lastpostid);
					
					$next=1+$lastpostid;
					$done+=count($postids);
					$continue=true;

				} else
					as_recalc_transition($state, 'dorecountposts_acount');
				break;
				
			case 'dorecountposts_acount':
				$postids=as_db_posts_get_for_recounting($next, 1000);
				
				if (count($postids)) {
					$lastpostid=max($postids);
					
					as_db_posts_answers_recount($next, $lastpostid);
					
					$next=1+$lastpostid;
					$done+=count($postids);
					$continue=true;

				} else {
					as_db_unupaqcount_update();
					as_recalc_transition($state, 'dorecountposts_complete');
				}
				break;
			
			case 'dorecalcpoints':
				as_recalc_transition($state, 'dorecalcpoints_usercount');
				break;
				
			case 'dorecalcpoints_usercount':
				as_db_userpointscount_update(); // for progress update - not necessarily accurate
				as_db_uapprovecount_update(); // needs to be somewhere and this is the most appropriate place
				as_recalc_transition($state, 'dorecalcpoints_recalc');
				break;
				
			case 'dorecalcpoints_recalc':
				$recalccount=10;
				$userids=as_db_users_get_for_recalc_points($next, $recalccount+1); // get one extra so we know where to start from next
				$gotcount=count($userids);
				$recalccount=min($recalccount, $gotcount); // can't recalc more than we got
				
				if ($recalccount>0) {
					$lastuserid=$userids[$recalccount-1];
					as_db_users_recalc_points($next, $lastuserid);					
					$done+=$recalccount;
					
				} else
					$lastuserid=$next; // for truncation

				if ($gotcount>$recalccount) { // more left to do
					$next=$userids[$recalccount]; // start next round at first one not recalculated
					$continue=true;

				} else {
					as_db_truncate_userpoints($lastuserid);
					as_db_userpointscount_update(); // quick so just do it here
					as_recalc_transition($state, 'dorecalcpoints_complete');
				}
				break;
				
			case 'dorefillevents':
				as_recalc_transition($state, 'dorefillevents_qcount');
				break;
				
			case 'dorefillevents_qcount':
				as_db_qcount_update();
				as_recalc_transition($state, 'dorefillevents_refill');
				break;
				
			case 'dorefillevents_refill':
				$questionids=as_db_qs_get_for_event_refilling($next, 1);
				
				if (count($questionids)) {
					require_once AS_INCLUDE_DIR.'as-app-events.php';
					require_once AS_INCLUDE_DIR.'as-app-updates.php';
					require_once AS_INCLUDE_DIR.'as-util-sort.php';
					
					$lastquestionid=max($questionids);
					
					foreach ($questionids as $questionid) {

					//	Retrieve all posts relating to this question

						list($question, $childposts, $achildposts)=as_db_select_with_pending(
							as_db_full_post_selectspec(null, $questionid),
							as_db_full_child_posts_selectspec(null, $questionid),
							as_db_full_a_child_posts_selectspec(null, $questionid)
						);
						
					//	Merge all posts while preserving keys as postids
						
						$posts=array($questionid => $question);

						foreach ($childposts as $postid => $post)
							$posts[$postid]=$post;

						foreach ($achildposts as $postid => $post)
							$posts[$postid]=$post;
							
					//	Creation and editing of each post
							
						foreach ($posts as $postid => $post) {
							$followonq=($post['basetype']=='Q') && ($postid!=$questionid);
							
							if ($followonq)
								$updatetype=AS_UPDATE_FOLLOWS;
							elseif ( ($post['basetype']=='C') && (@$posts[$post['parentid']]['basetype']=='Q') )
								$updatetype=AS_UPDATE_C_FOR_Q;
							elseif ( ($post['basetype']=='C') && (@$posts[$post['parentid']]['basetype']=='A') )
								$updatetype=AS_UPDATE_C_FOR_A;
							else
								$updatetype=null;
							
							as_create_event_for_q_user($questionid, $postid, $updatetype, $post['userid'], @$posts[$post['parentid']]['userid'], $post['created']);
							
							if (isset($post['updated']) && !$followonq)
								as_create_event_for_q_user($questionid, $postid, $post['updatetype'], $post['lastuserid'], $post['userid'], $post['updated']);
						}
						
					//	Tags and categories of question

						as_create_event_for_tags($question['tags'], $questionid, null, $question['userid'], $question['created']);
						as_create_event_for_category($question['categoryid'], $questionid, null, $question['userid'], $question['created']);
						
					//	Collect comment threads
						
						$parentidcomments=array();
						
						foreach ($posts as $postid => $post)
							if ($post['basetype']=='C')
								$parentidcomments[$post['parentid']][$postid]=$post;
					
					//	For each comment thread, notify all previous comment authors of each comment in the thread (could get slow)

						foreach ($parentidcomments as $parentid => $comments) {
							$keyuserids=array();
							
							as_sort_by($comments, 'created');
							
							foreach ($comments as $comment) {
								foreach ($keyuserids as $keyuserid => $dummy)
									if ( ($keyuserid != $comment['userid']) && ($keyuserid != @$posts[$parentid]['userid']) )
										as_db_event_create_not_entity($keyuserid, $questionid, $comment['postid'], AS_UPDATE_FOLLOWS, $comment['userid'], $comment['created']);

								if (isset($comment['userid']))
									$keyuserids[$comment['userid']]=true;
							}
						}
					}
					
					$next=1+$lastquestionid;
					$done+=count($questionids);
					$continue=true;

				} else
					as_recalc_transition($state, 'dorefillevents_complete');
				break;
			
			case 'dorecalccategories':
				as_recalc_transition($state, 'dorecalccategories_postcount');
				break;
			
			case 'dorecalccategories_postcount':
				as_db_acount_update();
				as_db_ccount_update();
				
				as_recalc_transition($state, 'dorecalccategories_postupdate');
				break;
				
			case 'dorecalccategories_postupdate':
				$postids=as_db_posts_get_for_recategorizing($next, 100);
				
				if (count($postids)) {
					$lastpostid=max($postids);
					
					as_db_posts_recalc_categoryid($next, $lastpostid);
					as_db_posts_calc_category_path($next, $lastpostid);
					
					$next=1+$lastpostid;
					$done+=count($postids);
					$continue=true;
				
				} else {
					as_recalc_transition($state, 'dorecalccategories_recount');
				}
				break;
			
			case 'dorecalccategories_recount':
				$categoryids=as_db_categories_get_for_recalcs($next, 10);
				
				if (count($categoryids)) {
					$lastcategoryid=max($categoryids);
					
					foreach ($categoryids as $categoryid)
						as_db_ifcategory_qcount_update($categoryid);
					
					$next=1+$lastcategoryid;
					$done+=count($categoryids);
					$continue=true;
				
				} else {
					as_recalc_transition($state, 'dorecalccategories_backpaths');
				}
				break;
				
			case 'dorecalccategories_backpaths':
				$categoryids=as_db_categories_get_for_recalcs($next, 10);

				if (count($categoryids)) {
					$lastcategoryid=max($categoryids);
					
					as_db_categories_recalc_backpaths($next, $lastcategoryid);
					
					$next=1+$lastcategoryid;
					$done+=count($categoryids);
					$continue=true;
				
				} else {
					as_recalc_transition($state, 'dorecalccategories_complete');
				}
				break;
				
			case 'dodeletehidden':
				as_recalc_transition($state, 'dodeletehidden_comments');
				break;
				
			case 'dodeletehidden_comments':
				$posts=as_db_posts_get_for_deleting('C', $next, 1);
				
				if (count($posts)) {
					require_once AS_INCLUDE_DIR.'as-app-posts.php';
					
					$postid=$posts[0];

					as_post_delete($postid);
					
					$next=1+$postid;
					$done++;
					$continue=true;
				
				} else
					as_recalc_transition($state, 'dodeletehidden_answers');
				break;
			
			case 'dodeletehidden_answers':
				$posts=as_db_posts_get_for_deleting('A', $next, 1);
				
				if (count($posts)) {
					require_once AS_INCLUDE_DIR.'as-app-posts.php';
					
					$postid=$posts[0];
					
					as_post_delete($postid);
					
					$next=1+$postid;
					$done++;
					$continue=true;
				
				} else
					as_recalc_transition($state, 'dodeletehidden_questions');
				break;

			case 'dodeletehidden_questions':
				$posts=as_db_posts_get_for_deleting('Q', $next, 1);
				
				if (count($posts)) {
					require_once AS_INCLUDE_DIR.'as-app-posts.php';
					
					$postid=$posts[0];
					
					as_post_delete($postid);
					
					$next=1+$postid;
					$done++;
					$continue=true;
				
				} else
					as_recalc_transition($state, 'dodeletehidden_complete');
				break;
				
			case 'doblobstodisk':
				as_recalc_transition($state, 'doblobstodisk_move');
				break;
				
			case 'doblobstodisk_move':
				$blob=as_db_get_next_blob_in_db($next);
				
				if (isset($blob)) {
					require_once AS_INCLUDE_DIR.'as-app-blobs.php';
					require_once AS_INCLUDE_DIR.'as-db-blobs.php';
					
					if (as_write_blob_file($blob['blobid'], $blob['content'], $blob['format']))
						as_db_blob_set_content($blob['blobid'], null);
						
					$next=1+$blob['blobid'];
					$done++;
					$continue=true;
				
				} else
					as_recalc_transition($state, 'doblobstodisk_complete');
				break;
				
			case 'doblobstodb':
				as_recalc_transition($state, 'doblobstodb_move');
				break;
			
			case 'doblobstodb_move':
				$blob=as_db_get_next_blob_on_disk($next);
				
				if (isset($blob)) {
					require_once AS_INCLUDE_DIR.'as-app-blobs.php';
					require_once AS_INCLUDE_DIR.'as-db-blobs.php';
					
					$content=as_read_blob_file($blob['blobid'], $blob['format']);
					as_db_blob_set_content($blob['blobid'], $content);
					as_delete_blob_file($blob['blobid'], $blob['format']);
						
					$next=1+$blob['blobid'];
					$done++;
					$continue=true;
				
				} else
					as_recalc_transition($state, 'doblobstodb_complete');
				break;

			default:
				$state='';
				break;
		}
		
		if ($continue)
			$state=$operation."\t".$length."\t".$next."\t".$done;
		
		return $continue && ($done<$length);
	}
	

	function as_recalc_transition(&$state, $operation)
/*
	Change the $state to represent the beginning of a new $operation
*/
	{
		$length=as_recalc_stage_length($operation);
		$next=(AS_FINAL_EXTERNAL_USERS && ($operation=='dorecalcpoints_recalc')) ? '' : 0;
		$done=0;
		
		$state=$operation."\t".$length."\t".$next."\t".$done;
	}

		
	function as_recalc_stage_length($operation)
/*
	Return how many steps there will be in recalculation $operation
*/
	{
		switch ($operation) {
			case 'doreindexcontent_pagereindex':
				$length=as_db_count_pages();
				break;
			
			case 'doreindexcontent_postreindex':
				$length=as_opt('cache_qcount')+as_opt('cache_acount')+as_opt('cache_ccount');
				break;
			
			case 'doreindexposts_wordcount':
				$length=as_db_count_words();
				break;
				
			case 'dorecalcpoints_recalc':
				$length=as_opt('cache_userpointscount');
				break;
				
			case 'dorecountposts_votecount':
			case 'dorecountposts_acount':
			case 'dorecalccategories_postupdate':
				$length=as_db_count_posts();
				break;
				
			case 'dorefillevents_refill':
				$length=as_opt('cache_qcount')+as_db_count_posts('Q_HIDDEN');
				break;
			
			case 'dorecalccategories_recount':
			case 'dorecalccategories_backpaths':
				$length=as_db_count_categories();
				break;
			
			case 'dodeletehidden_comments':
				$length=count(as_db_posts_get_for_deleting('C'));
				break;
				
			case 'dodeletehidden_answers':
				$length=count(as_db_posts_get_for_deleting('A'));
				break;
				
			case 'dodeletehidden_questions':
				$length=count(as_db_posts_get_for_deleting('Q'));
				break;
				
			case 'doblobstodisk_move':
				$length=as_db_count_blobs_in_db();
				break;
			
			case 'doblobstodb_move':
				$length=as_db_count_blobs_on_disk();
				break;
			
			default:
				$length=0;
				break;
		}
		
		return $length;
	}

	
	function as_recalc_get_message($state)
/*
	Return a string which gives a user-viewable version of $state
*/
	{
		@list($operation, $length, $next, $done)=explode("\t", $state);
		
		$done=(int)$done;
		$length=(int)$length;
		
		switch ($operation) {
			case 'doreindexcontent_postcount':
			case 'dorecountposts_postcount':
			case 'dorecalccategories_postcount':
			case 'dorefillevents_qcount':
				$message=as_lang('admin/recalc_posts_count');
				break;
				
			case 'doreindexcontent_pagereindex':
				$message=strtr(as_lang('admin/reindex_pages_reindexed'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;

			case 'doreindexcontent_postreindex':
				$message=strtr(as_lang('admin/reindex_posts_reindexed'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'doreindexposts_wordcount':
				$message=strtr(as_lang('admin/reindex_posts_wordcounted'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dorecountposts_votecount':
				$message=strtr(as_lang('admin/recount_posts_votes_recounted'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dorecountposts_acount':
				$message=strtr(as_lang('admin/recount_posts_as_recounted'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'doreindexposts_complete':
				$message=as_lang('admin/reindex_posts_complete');
				break;
				
			case 'dorecountposts_complete':
				$message=as_lang('admin/recount_posts_complete');
				break;
				
			case 'dorecalcpoints_usercount':
				$message=as_lang('admin/recalc_points_usercount');
				break;
				
			case 'dorecalcpoints_recalc':
				$message=strtr(as_lang('admin/recalc_points_recalced'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dorecalcpoints_complete':
				$message=as_lang('admin/recalc_points_complete');
				break;
				
			case 'dorefillevents_refill':
				$message=strtr(as_lang('admin/refill_events_refilled'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dorefillevents_complete':
				$message=as_lang('admin/refill_events_complete');
				break;
				
			case 'dorecalccategories_postupdate':
				$message=strtr(as_lang('admin/recalc_categories_updated'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dorecalccategories_recount':
				$message=strtr(as_lang('admin/recalc_categories_recounting'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dorecalccategories_backpaths':
				$message=strtr(as_lang('admin/recalc_categories_backpaths'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dorecalccategories_complete':
				$message=as_lang('admin/recalc_categories_complete');
				break;
				
			case 'dodeletehidden_comments':
				$message=strtr(as_lang('admin/hidden_comments_deleted'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dodeletehidden_answers':
				$message=strtr(as_lang('admin/hidden_answers_deleted'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'dodeletehidden_questions':
				$message=strtr(as_lang('admin/hidden_questions_deleted'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;

			case 'dodeletehidden_complete':
				$message=as_lang('admin/delete_hidden_complete');
				break;
				
			case 'doblobstodisk_move':
			case 'doblobstodb_move':
				$message=strtr(as_lang('admin/blobs_move_moved'), array(
					'^1' => number_format($done),
					'^2' => number_format($length)
				));
				break;
				
			case 'doblobstodisk_complete':
			case 'doblobstodb_complete':
				$message=as_lang('admin/blobs_move_complete');
				break;
			
			default:
				$message='';
				break;
		}
		
		return $message;
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/