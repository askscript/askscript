<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-event-updates.php
	Version: See define()s at top of as-include/as-base.php
	Description: Event module for maintaining events tables


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


	class as_event_updates {

		function process_event($event, $userid, $handle, $cookieid, $params)
		{
			if (@$params['silent']) // don't create updates about silent edits, and possibly other silent events in future
				return;
				
			require_once AS_INCLUDE_DIR.'as-db-events.php';
			require_once AS_INCLUDE_DIR.'as-app-events.php';

			switch ($event) {
				case 'q_post':
					if (isset($params['parent'])) // question is following an answer
						as_create_event_for_q_user($params['parent']['parentid'], $params['postid'], AS_UPDATE_FOLLOWS, $userid, $params['parent']['userid']);
					
					as_create_event_for_q_user($params['postid'], $params['postid'], null, $userid);
					as_create_event_for_tags($params['tags'], $params['postid'], null, $userid);
					as_create_event_for_category($params['categoryid'], $params['postid'], null, $userid);
					break;
					

				case 'a_post':
					as_create_event_for_q_user($params['parentid'], $params['postid'], null, $userid, $params['parent']['userid']);
					break;
					

				case 'c_post':
					$keyuserids=array();
					
					foreach ($params['thread'] as $comment) // previous comments in thread (but not author of parent again)
						if (isset($comment['userid']))
							$keyuserids[$comment['userid']]=true;
							
					foreach ($keyuserids as $keyuserid => $dummy)
						if ($keyuserid != $userid)
							as_db_event_create_not_entity($keyuserid, $params['questionid'], $params['postid'], AS_UPDATE_FOLLOWS, $userid);

					switch ($params['parent']['basetype'])
					{
						case 'Q':
							$updatetype=AS_UPDATE_C_FOR_Q;
							break;
							
						case 'A':
							$updatetype=AS_UPDATE_C_FOR_A;
							break;
							
						default:
							$updatetype=null;
							break;
					}
					
					as_create_event_for_q_user($params['questionid'], $params['postid'], $updatetype, $userid,
						@$keyuserids[$params['parent']['userid']] ? null : $params['parent']['userid']);
							// give precedence to 'your comment followed' rather than 'your Q/A commented' if both are true	
					break;

				
				case 'q_edit':
					if ($params['titlechanged'] || $params['contentchanged'])
						$updatetype=AS_UPDATE_CONTENT;
					elseif ($params['tagschanged'])
						$updatetype=AS_UPDATE_TAGS;
					else
						$updatetype=null;
						
					if (isset($updatetype)) {
						as_create_event_for_q_user($params['postid'], $params['postid'], $updatetype, $userid, $params['oldquestion']['userid']);

						if ($params['tagschanged'])
							as_create_event_for_tags($params['tags'], $params['postid'], AS_UPDATE_TAGS, $userid);
					}
					break;

					
				case 'a_select':
					as_create_event_for_q_user($params['parentid'], $params['postid'], AS_UPDATE_SELECTED, $userid, $params['answer']['userid']);
					break;

					
				case 'q_reopen':
				case 'q_close':
					as_create_event_for_q_user($params['postid'], $params['postid'], AS_UPDATE_CLOSED, $userid, $params['oldquestion']['userid']);
					break;
					

				case 'q_hide':
					if (isset($params['oldquestion']['userid']))
						as_db_event_create_not_entity($params['oldquestion']['userid'], $params['postid'], $params['postid'], AS_UPDATE_VISIBLE, $userid);
					break;
					

				case 'q_reshow':
					as_create_event_for_q_user($params['postid'], $params['postid'], AS_UPDATE_VISIBLE, $userid, $params['oldquestion']['userid']);
					break;
					
				
				case 'q_move':
					as_create_event_for_q_user($params['postid'], $params['postid'], AS_UPDATE_CATEGORY, $userid, $params['oldquestion']['userid']);
					as_create_event_for_category($params['categoryid'], $params['postid'], AS_UPDATE_CATEGORY, $userid);
					break;
					

				case 'a_edit':
					if ($params['contentchanged'])
						as_create_event_for_q_user($params['parentid'], $params['postid'], AS_UPDATE_CONTENT, $userid, $params['oldanswer']['userid']);
					break;
					

				case 'a_hide':
					if (isset($params['oldanswer']['userid']))
						as_db_event_create_not_entity($params['oldanswer']['userid'], $params['parentid'], $params['postid'], AS_UPDATE_VISIBLE, $userid);
					break;
					

				case 'a_reshow':
					as_create_event_for_q_user($params['parentid'], $params['postid'], AS_UPDATE_VISIBLE, $userid, $params['oldanswer']['userid']);
					break;
					

				case 'c_edit':
					if ($params['contentchanged'])
						as_create_event_for_q_user($params['questionid'], $params['postid'], AS_UPDATE_CONTENT, $userid, $params['oldcomment']['userid']);
					break;
					

				case 'a_to_c':
					if ($params['contentchanged'])
						as_create_event_for_q_user($params['questionid'], $params['postid'], AS_UPDATE_CONTENT, $userid, $params['oldanswer']['userid']);
					else
						as_create_event_for_q_user($params['questionid'], $params['postid'], AS_UPDATE_TYPE, $userid, $params['oldanswer']['userid']);
					break;
				

				case 'c_hide':
					if (isset($params['oldcomment']['userid']))
						as_db_event_create_not_entity($params['oldcomment']['userid'], $params['questionid'], $params['postid'], AS_UPDATE_VISIBLE, $userid);
					break;
					

				case 'c_reshow':
					as_create_event_for_q_user($params['questionid'], $params['postid'], AS_UPDATE_VISIBLE, $userid, $params['oldcomment']['userid']);
					break;
			}
		}
	
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/