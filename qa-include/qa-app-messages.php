<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-app-messages.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Handling private or public messages (wall posts)


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


	function as_wall_error_html($fromuserid, $touserid, $touserflags)
/*
	Returns an HTML string describing the reason why user $fromuserid cannot post on the wall of $touserid who has
	user flags $touserflags. If there is no such reason the function returns false.
*/
	{
		require_once QA_INCLUDE_DIR.'qa-app-limits.php';

		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		if ((!QA_FINAL_EXTERNAL_USERS) && as_opt('allow_user_walls')) {
			if ( ($touserflags & QA_USER_FLAGS_NO_WALL_POSTS) && !(isset($fromuserid) && ($fromuserid==$touserid)) )
				return as_lang_html('profile/post_wall_blocked');
			
			else
				switch (as_user_permit_error('permit_post_wall', QA_LIMIT_WALL_POSTS)) {
					case 'limit':
						return as_lang_html('profile/post_wall_limit');
						break;
						
					case 'login':
						return as_insert_login_links(as_lang_html('profile/post_wall_must_login'), as_request());
						break;
						
					case 'confirm':
						return as_insert_login_links(as_lang_html('profile/post_wall_must_confirm'), as_request());
						break;
						
					case 'approve':
						return as_lang_html('profile/post_wall_must_be_approved');
						break;
						
					case false:
						return false;
						break;
				}
		}
		
		return as_lang_html('users/no_permission');
	}
	
	
	function as_wall_add_post($userid, $handle, $cookieid, $touserid, $tohandle, $content, $format)
/*
	Adds a post to the wall of user $touserid with handle $tohandle, containing $content in $format (e.g. '' for text or 'html')
	The post is by user $userid with handle $handle, and $cookieid is the user's current cookie (used for reporting the event).
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once QA_INCLUDE_DIR.'qa-app-format.php';
		require_once QA_INCLUDE_DIR.'qa-db-messages.php';
				
		$messageid=as_db_message_create($userid, $touserid, $content, $format, true);
		as_db_user_recount_posts($touserid);
		
		as_report_event('u_wall_post', $userid, $handle, $cookieid, array(
			'userid' => $touserid,
			'handle' => $tohandle,
			'messageid' => $messageid,
			'content' => $content,
			'format' => $format,
			'text' => as_viewer_text($content, $format),
		));

		return $messageid;
	}
	
	
	function as_wall_delete_post($userid, $handle, $cookieid, $message)
/*
	Deletes the wall post described in $message (as obtained via as_db_recent_messages_selectspec()). The deletion was performed
	by user $userid with handle $handle, and $cookieid is the user's current cookie (all used for reporting the event).
*/
	{
		require_once QA_INCLUDE_DIR.'qa-db-messages.php';
		
		as_db_message_delete($message['messageid']);
		as_db_user_recount_posts($message['touserid']);
		
		as_report_event('u_wall_delete', $userid, $handle, $cookieid, array(
			'messageid' => $message['messageid'],
			'oldmessage' => $message,
		));
	}
	
	
	function as_wall_posts_add_rules($usermessages, $start)
/*
	Return the list of messages in $usermessages (as obtained via as_db_recent_messages_selectspec()) with additional
	fields indicating what actions can be performed on them by the current user. The messages were retrieved beginning
	at offset $start in the database. Currently only 'deleteable' is relevant.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		$userid=as_get_logged_in_userid();
		$userdeleteall=!(as_user_permit_error('permit_hide_show') || as_user_permit_error('permit_delete_hidden'));
			// reuse "Hiding or showing any post" and "Deleting hidden posts" permissions
		$userrecent=($start==0) && isset($userid); // User can delete all of the recent messages they wrote on someone's wall...
		
		foreach ($usermessages as $key => $message) {
			if ($message['fromuserid']!=$userid)
				$userrecent=false; // ... until we come across one that they didn't write (which could be a reply)
		
			$usermessages[$key]['deleteable'] =
				($message['touserid']==$userid) || // if it's this user's wall
				($userrecent && ($message['fromuserid']==$userid)) || // if it's one the user wrote that no one replied to yet
				$userdeleteall; // if the user has enough permissions  to delete from any wall
		}
		
		return $usermessages;
	}
	
		
	function as_wall_post_view($message)
/*
	Returns an element to add to $as_content['message_list']['messages'] for $message (as obtained via
	as_db_recent_messages_selectspec() and then as_wall_posts_add_rules()).
*/
	{
		require_once QA_INCLUDE_DIR.'qa-app-format.php';
		
		$options=as_message_html_defaults();
		
		$htmlfields=as_message_html_fields($message, $options);
		
		if ($message['deleteable'])
			$htmlfields['form']=array(
				'style' => 'light',

				'buttons' => array(
					'delete' => array(
						'tags' => 'name="m'.as_html($message['messageid']).'_dodelete" onclick="return as_wall_post_click('.as_js($message['messageid']).', this);"',
						'label' => as_lang_html('question/delete_button'),
						'popup' => as_lang_html('profile/delete_wall_post_popup'),
					),
				),
			);
			
		return $htmlfields;
	}
	
	
	function as_wall_view_more_link($handle, $start)
/*
	Returns an element to add to $as_content['message_list']['messages'] with a link to view all wall posts
*/
	{
		return array(
			'content' => '<a href="'.as_path_html('user/'.$handle.'/wall', array('start' => $start)).'">'.as_lang_html('profile/wall_view_more').'</a>',
		);
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/