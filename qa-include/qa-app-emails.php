<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-app-emails.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Wrapper functions for sending email notifications to users


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

	require_once QA_INCLUDE_DIR.'qa-app-options.php';


	function as_suspend_notifications($suspend=true)
/*
	Suspend the sending of all email notifications via as_send_notification(...) if $suspend is true, otherwise
	reinstate it. A counter is kept to allow multiple calls.
*/
	{
		global $as_notifications_suspended;
		
		$as_notifications_suspended+=($suspend ? 1 : -1);
	}
	
	
	function as_send_notification($userid, $email, $handle, $subject, $body, $subs)
/*
	Send email to person with $userid and/or $email and/or $handle (null/invalid values are ignored or retrieved from
	user database as appropriate). Email uses $subject and $body, after substituting each key in $subs with its
	corresponding value, plus applying some standard substitutions such as ^site_title, ^handle and ^email.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		global $as_notifications_suspended;
		
		if ($as_notifications_suspended>0)
			return false;
		
		require_once QA_INCLUDE_DIR.'qa-db-selects.php';
		require_once QA_INCLUDE_DIR.'qa-util-string.php';
		
		if (isset($userid)) {
			$needemail=!as_email_validate(@$email); // take from user if invalid, e.g. @ used in practice
			$needhandle=empty($handle);
			
			if ($needemail || $needhandle) {
				if (QA_FINAL_EXTERNAL_USERS) {
					if ($needhandle) {
						$handles=as_get_public_from_userids(array($userid));
						$handle=@$handles[$userid];
					}
					
					if ($needemail)
						$email=as_get_user_email($userid);
				
				} else {
					$useraccount=as_db_select_with_pending(
						as_db_user_account_selectspec($userid, true)
					);
					
					if ($needhandle)
						$handle=@$useraccount['handle'];
	
					if ($needemail)
						$email=@$useraccount['email'];
				}
			}
		}
			
		if (isset($email) && as_email_validate($email)) {
			$subs['^site_title']=as_opt('site_title');
			$subs['^handle']=$handle;
			$subs['^email']=$email;
			$subs['^open']="\n";
			$subs['^close']="\n";
		
			return as_send_email(array(
				'fromemail' => as_opt('from_email'),
				'fromname' => as_opt('site_title'),
				'toemail' => $email,
				'toname' => $handle,
				'subject' => strtr($subject, $subs),
				'body' => (empty($handle) ? '' : as_lang_sub('emails/to_handle_prefix', $handle)).strtr($body, $subs),
				'html' => false,
			));
		
		} else
			return false;
	}
	

	function as_send_email($params)
/*
	Send the email based on the $params array - the following keys are required (some can be empty): fromemail,
	fromname, toemail, toname, subject, body, html
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
	//	@error_log(print_r($params, true));
		
		require_once QA_INCLUDE_DIR.'qa-class.phpmailer.php';
		
		$mailer=new PHPMailer();
		$mailer->CharSet='utf-8';
		
		$mailer->From=$params['fromemail'];
		$mailer->Sender=$params['fromemail'];
		$mailer->FromName=$params['fromname'];
		$mailer->AddAddress($params['toemail'], $params['toname']);
		$mailer->Subject=$params['subject'];
		$mailer->Body=$params['body'];

		if ($params['html'])
			$mailer->IsHTML(true);
			
		if (as_opt('smtp_active')) {
			$mailer->IsSMTP();
			$mailer->Host=as_opt('smtp_address');
			$mailer->Port=as_opt('smtp_port');
			
			if (as_opt('smtp_secure'))
				$mailer->SMTPSecure=as_opt('smtp_secure');
				
			if (as_opt('smtp_authenticate')) {
				$mailer->SMTPAuth=true;
				$mailer->Username=as_opt('smtp_username');
				$mailer->Password=as_opt('smtp_password');
			}
		}
			
		return $mailer->Send();
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/