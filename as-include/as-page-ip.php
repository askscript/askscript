<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-ip.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for page showing recent activity for an IP address


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
	require_once AS_INCLUDE_DIR.'as-app-format.php';

	
	$ip=as_request_part(1); // picked up from as-page.php
	if (long2ip(ip2long($ip))!==$ip)
		return include AS_INCLUDE_DIR.'as-page-not-found.php';


//	Find recently (hidden, queued or not) questions, answers, comments and edits for this IP

	$userid=as_get_logged_in_userid();

	list($qs, $qs_queued, $qs_hidden, $a_qs, $a_queued_qs, $a_hidden_qs, $c_qs, $c_queued_qs, $c_hidden_qs, $edit_qs)=
		as_db_select_with_pending(
			as_db_qs_selectspec($userid, 'created', 0, null, $ip, false),
			as_db_qs_selectspec($userid, 'created', 0, null, $ip, 'Q_QUEUED'),
			as_db_qs_selectspec($userid, 'created', 0, null, $ip, 'Q_HIDDEN', true),
			as_db_recent_a_qs_selectspec($userid, 0, null, $ip, false),
			as_db_recent_a_qs_selectspec($userid, 0, null, $ip, 'A_QUEUED'),
			as_db_recent_a_qs_selectspec($userid, 0, null, $ip, 'A_HIDDEN', true),
			as_db_recent_c_qs_selectspec($userid, 0, null, $ip, false),
			as_db_recent_c_qs_selectspec($userid, 0, null, $ip, 'C_QUEUED'),
			as_db_recent_c_qs_selectspec($userid, 0, null, $ip, 'C_HIDDEN', true),
			as_db_recent_edit_qs_selectspec($userid, 0, null, $ip, false)
		);
	
	
//	Check we have permission to view this page, and whether we can block or unblock IPs

	if (as_user_maximum_permit_error('permit_anon_view_ips')) {
		$content=as_content_prepare();
		$content['error']=as_lang_html('users/no_permission');
		return $content;
	}
	
	$blockable=as_user_level_maximum()>=AS_USER_LEVEL_MODERATOR; // allow moderator in one category to block across all categories
		

//	Perform blocking or unblocking operations as appropriate

	if (as_clicked('doblock') || as_clicked('dounblock') || as_clicked('dohideall')) {
		if (!as_check_form_security_code('ip-'.$ip, as_post_text('code')))
			$pageerror=as_lang_html('misc/form_security_again');

		elseif ($blockable) {
		
			if (as_clicked('doblock')) {
				$oldblocked=as_opt('block_ips_write');
				as_set_option('block_ips_write', (strlen($oldblocked) ? ($oldblocked.' , ') : '').$ip);
				
				as_report_event('ip_block', $userid, as_get_logged_in_handle(), as_cookie_get(), array(
					'ip' => $ip,
				));
				
				as_redirect(as_request());
			}
			
			if (as_clicked('dounblock')) {
				require_once AS_INCLUDE_DIR.'as-app-limits.php';
				
				$blockipclauses=as_block_ips_explode(as_opt('block_ips_write'));
				
				foreach ($blockipclauses as $key => $blockipclause)
					if (as_block_ip_match($ip, $blockipclause))
						unset($blockipclauses[$key]);
						
				as_set_option('block_ips_write', implode(' , ', $blockipclauses));
	
				as_report_event('ip_unblock', $userid, as_get_logged_in_handle(), as_cookie_get(), array(
					'ip' => $ip,
				));
	
				as_redirect(as_request());
			}
			
			if (as_clicked('dohideall') && !as_user_maximum_permit_error('permit_hide_show')) {
				// allow moderator in one category to hide posts across all categories if they are identified via IP page
				
				require_once AS_INCLUDE_DIR.'as-db-admin.php';
				require_once AS_INCLUDE_DIR.'as-app-posts.php';
			
				$postids=as_db_get_ip_visible_postids($ip);
	
				foreach ($postids as $postid)
					as_post_set_hidden($postid, true, $userid);
					
				as_redirect(as_request());
			}
		}
	}
	

//	Combine sets of questions and get information for users

	$questions=as_any_sort_by_date(array_merge($qs, $qs_queued, $qs_hidden, $a_qs, $a_queued_qs, $a_hidden_qs, $c_qs, $c_queued_qs, $c_hidden_qs, $edit_qs));
	
	$usershtml=as_userids_handles_html(as_any_get_userids_handles($questions));

	$hostname=gethostbyaddr($ip);
	

//	Prepare content for theme
	
	$content=as_content_prepare();

	$content['title']=as_lang_html_sub('main/ip_address_x', as_html($ip));
	$content['error']=@$pageerror;

	$content['form']=array(
		'tags' => 'method="post" action="'.as_self_html().'"',
		
		'style' => 'wide',
		
		'fields' => array(
			'host' => array(
				'type' => 'static',
				'label' => as_lang_html('misc/host_name'),
				'value' => as_html($hostname),
			),
		),
		
		'hidden' => array(
			'code' => as_get_form_security_code('ip-'.$ip),
		),
	);
	

	if ($blockable) {
		require_once AS_INCLUDE_DIR.'as-app-limits.php';
		
		$blockipclauses=as_block_ips_explode(as_opt('block_ips_write'));
		$matchclauses=array();
		
		foreach ($blockipclauses as $blockipclause)
			if (as_block_ip_match($ip, $blockipclause))
				$matchclauses[]=$blockipclause;
		
		if (count($matchclauses)) {
			$content['form']['fields']['status']=array(
				'type' => 'static',
				'label' => as_lang_html('misc/matches_blocked_ips'),
				'value' => as_html(implode("\n", $matchclauses), true),
			);
			
			$content['form']['buttons']['unblock']=array(
				'tags' => 'name="dounblock"',
				'label' => as_lang_html('misc/unblock_ip_button'),
			);
			
			if (count($questions) && !as_user_maximum_permit_error('permit_hide_show'))
				$content['form']['buttons']['hideall']=array(
					'tags' => 'name="dohideall" onclick="as_show_waiting_after(this, false);"',
					'label' => as_lang_html('misc/hide_all_ip_button'),
				);

		} else
			$content['form']['buttons']['block']=array(
				'tags' => 'name="doblock"',
				'label' => as_lang_html('misc/block_ip_button'),
			);
	}

	
	$content['q_list']['qs']=array();
	
	if (count($questions)) {
		$content['q_list']['title']=as_lang_html_sub('misc/recent_activity_from_x', as_html($ip));
	
		foreach ($questions as $question) {
			$htmloptions=as_post_html_options($question);
			$htmloptions['tagsview']=false;
			$htmloptions['voteview']=false;
			$htmloptions['ipview']=false;
			$htmloptions['answersview']=false;
			$htmloptions['viewsview']=false;
			$htmloptions['updateview']=false;
			
			$htmlfields=as_any_to_q_html_fields($question, $userid, as_cookie_get(), $usershtml, null, $htmloptions);
			
			if (isset($htmlfields['what_url'])) // link directly to relevant content
				$htmlfields['url']=$htmlfields['what_url'];
			
			$hasother=isset($question['opostid']);
			
			if ($question[$hasother ? 'ohidden' : 'hidden'] && !isset($question[$hasother ? 'oupdatetype' : 'updatetype'])) {
				$htmlfields['what_2']=as_lang_html('main/hidden');

				if (@$htmloptions['whenview']) {
					$updated=@$question[$hasother ? 'oupdated' : 'updated'];
					if (isset($updated))
						$htmlfields['when_2']=as_when_to_html($updated, @$htmloptions['fulldatedays']);
				}
			}

			$content['q_list']['qs'][]=$htmlfields;
		}

	} else
		$content['q_list']['title']=as_lang_html_sub('misc/no_activity_from_x', as_html($ip));
	
	
	return $content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/