<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-event-notify.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Event module for sending notification emails


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


	class as_event_notify {

		function process_event($event, $userid, $handle, $cookieid, $params)
		{
			require_once QA_INCLUDE_DIR.'qa-app-emails.php';
			require_once QA_INCLUDE_DIR.'qa-app-format.php';
			require_once QA_INCLUDE_DIR.'qa-util-string.php';

			
			switch ($event) {
				case 'q_post':
					$followanswer=@$params['followanswer'];
					$sendhandle=isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] : as_lang('main/anonymous'));
					
					if (isset($followanswer['notify']) && !as_post_is_by_user($followanswer, $userid, $cookieid)) {
						$blockwordspreg=as_get_block_words_preg();
						$sendtext=as_viewer_text($followanswer['content'], $followanswer['format'], array('blockwordspreg' => $blockwordspreg));
						
						as_send_notification($followanswer['userid'], $followanswer['notify'], @$followanswer['handle'], as_lang('emails/a_followed_subject'), as_lang('emails/a_followed_body'), array(
							'^q_handle' => $sendhandle,
							'^q_title' => as_block_words_replace($params['title'], $blockwordspreg),
							'^a_content' => $sendtext,
							'^url' => as_q_path($params['postid'], $params['title'], true),
						));
					}
					
					if (as_opt('notify_admin_q_post'))
						as_send_notification(null, as_opt('feedback_email'), null, as_lang('emails/q_posted_subject'), as_lang('emails/q_posted_body'), array(
							'^q_handle' => $sendhandle,
							'^q_title' => $params['title'], // don't censor title or content here since we want the admin to see bad words
							'^q_content' => $params['text'],
							'^url' => as_q_path($params['postid'], $params['title'], true),
						));

					break;

					
				case 'a_post':
					$question=$params['parent'];
					
					if (isset($question['notify']) && !as_post_is_by_user($question, $userid, $cookieid))
						as_send_notification($question['userid'], $question['notify'], @$question['handle'], as_lang('emails/q_answered_subject'), as_lang('emails/q_answered_body'), array(
							'^a_handle' => isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] : as_lang('main/anonymous')),
							'^q_title' => $question['title'],
							'^a_content' => as_block_words_replace($params['text'], as_get_block_words_preg()),
							'^url' => as_q_path($question['postid'], $question['title'], true, 'A', $params['postid']),
						));
					break;

					
				case 'c_post':
					$parent=$params['parent'];
					$question=$params['question'];
					
					$senttoemail=array(); // to ensure each user or email gets only one notification about an added comment
					$senttouserid=array();
					
					switch ($parent['basetype']) {
						case 'Q':
							$subject=as_lang('emails/q_commented_subject');
							$body=as_lang('emails/q_commented_body');
							$context=$parent['title'];
							break;
							
						case 'A':
							$subject=as_lang('emails/a_commented_subject');
							$body=as_lang('emails/a_commented_body');
							$context=as_viewer_text($parent['content'], $parent['format']);
							break;
					}
					
					$blockwordspreg=as_get_block_words_preg();
					$sendhandle=isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] : as_lang('main/anonymous'));
					$sendcontext=as_block_words_replace($context, $blockwordspreg);
					$sendtext=as_block_words_replace($params['text'], $blockwordspreg);
					$sendurl=as_q_path($question['postid'], $question['title'], true, 'C', $params['postid']);
						
					if (isset($parent['notify']) && !as_post_is_by_user($parent, $userid, $cookieid)) {
						$senduserid=$parent['userid'];
						$sendemail=@$parent['notify'];
						
						if (as_email_validate($sendemail))
							$senttoemail[$sendemail]=true;
						elseif (isset($senduserid))
							$senttouserid[$senduserid]=true;
			
						as_send_notification($senduserid, $sendemail, @$parent['handle'], $subject, $body, array(
							'^c_handle' => $sendhandle,
							'^c_context' => $sendcontext,
							'^c_content' => $sendtext,
							'^url' => $sendurl,
						));
					}
					
					foreach ($params['thread'] as $comment)
						if (isset($comment['notify']) && !as_post_is_by_user($comment, $userid, $cookieid)) {
							$senduserid=$comment['userid'];
							$sendemail=@$comment['notify'];
							
							if (as_email_validate($sendemail)) {
								if (@$senttoemail[$sendemail])
									continue;
									
								$senttoemail[$sendemail]=true;
								
							} elseif (isset($senduserid)) {
								if (@$senttouserid[$senduserid])
									continue;
									
								$senttouserid[$senduserid]=true;
							}
		
							as_send_notification($senduserid, $sendemail, @$comment['handle'], as_lang('emails/c_commented_subject'), as_lang('emails/c_commented_body'), array(
								'^c_handle' => $sendhandle,
								'^c_context' => $sendcontext,
								'^c_content' => $sendtext,
								'^url' => $sendurl,
							));
						}
					break;

					
				case 'q_queue':
				case 'q_requeue':
					if (as_opt('moderate_notify_admin'))
						as_send_notification(null, as_opt('feedback_email'), null,
							($event=='q_requeue') ? as_lang('emails/remoderate_subject') : as_lang('emails/moderate_subject'),
							($event=='q_requeue') ? as_lang('emails/remoderate_body') : as_lang('emails/moderate_body'),
							array(
								'^p_handle' => isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] :
									(strlen(@$oldquestion['name']) ? $oldquestion['name'] : as_lang('main/anonymous'))),
								'^p_context' => trim(@$params['title']."\n\n".$params['text']), // don't censor for admin
								'^url' => as_q_path($params['postid'], $params['title'], true),
								'^a_url' => as_path_absolute('admin/moderate'),
							)
						);
					break;
					

				case 'a_queue':
				case 'a_requeue':
					if (as_opt('moderate_notify_admin'))
						as_send_notification(null, as_opt('feedback_email'), null,
							($event=='a_requeue') ? as_lang('emails/remoderate_subject') : as_lang('emails/moderate_subject'),
							($event=='a_requeue') ? as_lang('emails/remoderate_body') : as_lang('emails/moderate_body'),
							array(
								'^p_handle' => isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] :
									(strlen(@$oldanswer['name']) ? $oldanswer['name'] : as_lang('main/anonymous'))),
								'^p_context' => $params['text'], // don't censor for admin
								'^url' => as_q_path($params['parentid'], $params['parent']['title'], true, 'A', $params['postid']),
								'^a_url' => as_path_absolute('admin/moderate'),
							)
						);
					break;
					

				case 'c_queue':
				case 'c_requeue':
					if (as_opt('moderate_notify_admin'))
						as_send_notification(null, as_opt('feedback_email'), null,
							($event=='c_requeue') ? as_lang('emails/remoderate_subject') : as_lang('emails/moderate_subject'),
							($event=='c_requeue') ? as_lang('emails/remoderate_body') : as_lang('emails/moderate_body'),
							array(
								'^p_handle' => isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] :
									(strlen(@$oldcomment['name']) ? $oldcomment['name'] : // could also be after answer converted to comment
									(strlen(@$oldanswer['name']) ? $oldanswer['name'] : as_lang('main/anonymous')))),
								'^p_context' => $params['text'], // don't censor for admin
								'^url' => as_q_path($params['questionid'], $params['question']['title'], true, 'C', $params['postid']),
								'^a_url' => as_path_absolute('admin/moderate'),
							)
						);
					break;

					
				case 'q_flag':
				case 'a_flag':
				case 'c_flag':
					$flagcount=$params['flagcount'];
					$oldpost=$params['oldpost'];
					$notifycount=$flagcount-as_opt('flagging_notify_first');
					
					if ( ($notifycount>=0) && (($notifycount % as_opt('flagging_notify_every'))==0) )
						as_send_notification(null, as_opt('feedback_email'), null, as_lang('emails/flagged_subject'), as_lang('emails/flagged_body'), array(
							'^p_handle' => isset($oldpost['handle']) ? $oldpost['handle'] :
								(strlen($oldpost['name']) ? $oldpost['name'] : as_lang('main/anonymous')),
							'^flags' => ($flagcount==1) ? as_lang_html_sub('main/1_flag', '1', '1') : as_lang_html_sub('main/x_flags', $flagcount),
							'^p_context' => trim(@$oldpost['title']."\n\n".as_viewer_text($oldpost['content'], $oldpost['format'])), // don't censor for admin
							'^url' => as_q_path($params['questionid'], $params['question']['title'], true, $oldpost['basetype'], $oldpost['postid']),
							'^a_url' => as_path_absolute('admin/flagged'),
						));
					break;
		
		
				case 'a_select':
					$answer=$params['answer'];
								
					if (isset($answer['notify']) && !as_post_is_by_user($answer, $userid, $cookieid)) {
						$blockwordspreg=as_get_block_words_preg();
						$sendcontent=as_viewer_text($answer['content'], $answer['format'], array('blockwordspreg' => $blockwordspreg));
		
						as_send_notification($answer['userid'], $answer['notify'], @$answer['handle'], as_lang('emails/a_selected_subject'), as_lang('emails/a_selected_body'), array(
							'^s_handle' => isset($handle) ? $handle : as_lang('main/anonymous'),
							'^q_title' => as_block_words_replace($params['parent']['title'], $blockwordspreg),
							'^a_content' => $sendcontent,
							'^url' => as_q_path($params['parentid'], $params['parent']['title'], true, 'A', $params['postid']),
						));
					}
					break;
				
				case 'u_register':
					if (as_opt('register_notify_admin'))
						as_send_notification(null, as_opt('feedback_email'), null, as_lang('emails/u_registered_subject'),
							as_opt('moderate_users') ? as_lang('emails/u_to_approve_body') : as_lang('emails/u_registered_body'), array(
							'^u_handle' => $handle,
							'^url' => as_path_absolute('user/'.$handle),
							'^a_url' => as_path_absolute('admin/approve'),
						));
					break;
					
				case 'u_level':
					if ( ($params['level']>=QA_USER_LEVEL_APPROVED) && ($params['oldlevel']<QA_USER_LEVEL_APPROVED) )
						as_send_notification($params['userid'], null, $params['handle'], as_lang('emails/u_approved_subject'), as_lang('emails/u_approved_body'), array(
							'^url' => as_path_absolute('user/'.$params['handle']),
						));
					break;
				
				case 'u_wall_post':
					if ($userid!=$params['userid']) {
						$blockwordspreg=as_get_block_words_preg();
						
						as_send_notification($params['userid'], null, $params['handle'], as_lang('emails/wall_post_subject'), as_lang('emails/wall_post_body'), array(
							'^f_handle' => isset($handle) ? $handle : as_lang('main/anonymous'),
							'^post' => as_block_words_replace($params['text'], $blockwordspreg),
							'^url' => as_path_absolute('user/'.$params['handle'], null, 'wall'),
						));
					}
					break;
			}
		}
	
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/