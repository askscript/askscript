<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-message.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for private messaging page


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

	require_once AS_INCLUDE_DIR.'as-db-selects.php';
	require_once AS_INCLUDE_DIR.'as-app-users.php';
	require_once AS_INCLUDE_DIR.'as-app-format.php';
	require_once AS_INCLUDE_DIR.'as-app-limits.php';
	
	$handle=as_request_part(1);
	$loginuserid=as_get_logged_in_userid();


//	Check we have a handle, we're not using Q2A's single-sign on integration and that we're logged in

	if (AS_FINAL_EXTERNAL_USERS)
		as_fatal_error('User accounts are handled by external code');
	
	if (!strlen($handle))
		as_redirect('users');

	if (!isset($loginuserid)) {
		$as_content=as_content_prepare();
		$as_content['error']=as_insert_login_links(as_lang_html('misc/message_must_login'), as_request());
		return $as_content;
	}


//	Find the user profile and questions and answers for this handle
	
	list($toaccount, $torecent, $fromrecent)=as_db_select_with_pending(
		as_db_user_account_selectspec($handle, false),
		as_db_recent_messages_selectspec($loginuserid, true, $handle, false),
		as_db_recent_messages_selectspec($handle, false, $loginuserid, true)
	);


//	Check the user exists and work out what can and can't be set (if not using single sign-on)
	
	if ( (!as_opt('allow_private_messages')) || (!is_array($toaccount)) || ($toaccount['flags'] & AS_USER_FLAGS_NO_MESSAGES) )
		return include AS_INCLUDE_DIR.'as-page-not-found.php';
	

//	Check that we have permission and haven't reached the limit

	$errorhtml=null;
	
	switch (as_user_permit_error(null, AS_LIMIT_MESSAGES)) {
		case 'limit':
			$errorhtml=as_lang_html('misc/message_limit');
			break;
			
		case false:
			break;
			
		default:
			$errorhtml=as_lang_html('users/no_permission');
			break;
	}

	if (isset($errorhtml)) {
		$as_content=as_content_prepare();
		$as_content['error']=$errorhtml;
		return $as_content;
	}


//	Process sending a message to user

	$messagesent=(as_get_state()=='message-sent');
	
	if (as_post_text('domessage')) {
		$inmessage=as_post_text('message');
		
		if (!as_check_form_security_code('message-'.$handle, as_post_text('code')))
			$pageerror=as_lang_html('misc/form_security_again');
			
		else {
			if (empty($inmessage))
				$errors['message']=as_lang('misc/message_empty');
			
			if (empty($errors)) {
				require_once AS_INCLUDE_DIR.'as-db-messages.php';
				require_once AS_INCLUDE_DIR.'as-app-emails.php';
	
				if (as_opt('show_message_history'))
					$messageid=as_db_message_create($loginuserid, $toaccount['userid'], $inmessage, '', false);
				else
					$messageid=null;
	
				$fromhandle=as_get_logged_in_handle();
				$canreply=!(as_get_logged_in_flags() & AS_USER_FLAGS_NO_MESSAGES);
				
				$more=strtr(as_lang($canreply ? 'emails/private_message_reply' : 'emails/private_message_info'), array(
					'^f_handle' => $fromhandle,
					'^url' => as_path_absolute($canreply ? ('message/'.$fromhandle) : ('user/'.$fromhandle)),
				));
	
				$subs=array(
					'^message' => $inmessage,
					'^f_handle' => $fromhandle,
					'^f_url' => as_path_absolute('user/'.$fromhandle),
					'^more' => $more,
					'^a_url' => as_path_absolute('account'),
				);
				
				if (as_send_notification($toaccount['userid'], $toaccount['email'], $toaccount['handle'],
						as_lang('emails/private_message_subject'), as_lang('emails/private_message_body'), $subs))
					$messagesent=true;
				else
					$pageerror=as_lang_html('main/general_error');
	
				as_report_event('u_message', $loginuserid, as_get_logged_in_handle(), as_cookie_get(), array(
					'userid' => $toaccount['userid'],
					'handle' => $toaccount['handle'],
					'messageid' => $messageid,
					'message' => $inmessage,
				));
				
				if ($messagesent && as_opt('show_message_history')) // show message as part of general history
					as_redirect(as_request(), array('state' => 'message-sent'));
			}
		}
	}


//	Prepare content for theme
	
	$as_content=as_content_prepare();
	
	$as_content['title']=as_lang_html('misc/private_message_title');

	$as_content['error']=@$pageerror;

	$as_content['form_message']=array(
		'tags' => 'method="post" action="'.as_self_html().'"',
		
		'style' => 'tall',
		
		'fields' => array(
			'message' => array(
				'type' => $messagesent ? 'static' : '',
				'label' => as_lang_html_sub('misc/message_for_x', as_get_one_user_html($handle, false)),
				'tags' => 'name="message" id="message"',
				'value' => as_html(@$inmessage, $messagesent),
				'rows' => 8,
				'note' => as_lang_html_sub('misc/message_explanation', as_html(as_opt('site_title'))),
				'error' => as_html(@$errors['message']),
			),
		),
		
		'buttons' => array(
			'send' => array(
				'tags' => 'onclick="as_show_waiting_after(this, false);"',
				'label' => as_lang_html('main/send_button'),
			),
		),
		
		'hidden' => array(
			'domessage' => '1',
			'code' => as_get_form_security_code('message-'.$handle),
		),
	);
	
	$as_content['focusid']='message';

	if ($messagesent) {
		$as_content['form_message']['ok']=as_lang_html('misc/message_sent');
		unset($as_content['form_message']['buttons']);

		if (as_opt('show_message_history'))
			unset($as_content['form_message']['fields']['message']);
		else {
			unset($as_content['form_message']['fields']['message']['note']);
			unset($as_content['form_message']['fields']['message']['label']);
		}
	}
	

//	If relevant, show recent message history

	if (as_opt('show_message_history')) {
		$recent=array_merge($torecent, $fromrecent);
		
		as_sort_by($recent, 'created');
		
		$showmessages=array_slice(array_reverse($recent, true), 0, AS_DB_RETRIEVE_MESSAGES);
		
		if (count($showmessages)) {
			$as_content['message_list']=array(
				'title' => as_lang_html_sub('misc/message_recent_history', as_html($toaccount['handle'])),
			);
			
			$options=as_message_html_defaults();
			
			foreach ($showmessages as $message)
				$as_content['message_list']['messages'][]=as_message_html_fields($message, $options);
		}
	}


	$as_content['raw']['account']=$toaccount; // for plugin layers to access
	

	return $as_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/