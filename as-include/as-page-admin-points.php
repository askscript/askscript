<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-admin-points.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for admin page for settings about user points


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

	require_once AS_INCLUDE_DIR.'as-db-recalc.php';
	require_once AS_INCLUDE_DIR.'as-db-points.php';
	require_once AS_INCLUDE_DIR.'as-app-options.php';
	require_once AS_INCLUDE_DIR.'as-app-admin.php';
	require_once AS_INCLUDE_DIR.'as-util-sort.php';
	
	
//	Check admin privileges

	if (!as_admin_check_privileges($content))
		return $content;


//	Process user actions
	
	$securityexpired=false;
	$recalculate=false;
	$optionnames=as_db_points_option_names();

	if (as_clicked('doshowdefaults')) {
		$options=array();
		
		foreach ($optionnames as $optionname)
			$options[$optionname]=as_default_option($optionname);
		
	} else {
		if (as_clicked('docancel'))
			;

		elseif (as_clicked('dosaverecalc')) {
			if (!as_check_form_security_code('admin/points', as_post_text('code')))
				$securityexpired=true;
		
			else {
				foreach ($optionnames as $optionname)
					as_set_option($optionname, (int)as_post_text('option_'.$optionname));
					
				if (!as_post_text('has_js'))
					as_redirect('admin/recalc', array('dorecalcpoints' => 1));
				else
					$recalculate=true;
			}
		}
	
		$options=as_get_options($optionnames);
	}
	
	
//	Prepare content for theme

	$content=as_content_prepare();

	$content['title']=as_lang_html('admin/admin_title').' - '.as_lang_html('admin/points_title');
	$content['error']=$securityexpired ? as_lang_html('admin/form_security_expired') : as_admin_page_error();

	$content['form']=array(
		'tags' => 'method="post" action="'.as_self_html().'" name="points_form" onsubmit="document.forms.points_form.has_js.value=1; return true;"',
		
		'style' => 'wide',
		
		'buttons' => array(
			'saverecalc' => array(
				'tags' => 'id="dosaverecalc"',
				'label' => as_lang_html('admin/save_recalc_button'),
			),
		),
		
		'hidden' => array(
			'dosaverecalc' => '1',
			'has_js' => '0',
			'code' => as_get_form_security_code('admin/points'),
		),
	);

	
	if (as_clicked('doshowdefaults')) {
		$content['form']['ok']=as_lang_html('admin/points_defaults_shown');
	
		$content['form']['buttons']['cancel']=array(
			'tags' => 'name="docancel"',
			'label' => as_lang_html('main/cancel_button'),
		);

	} else {
		if ($recalculate) {
			$content['form']['ok']='<span id="recalc_ok"></span>';
			$content['form']['hidden']['code_recalc']=as_get_form_security_code('admin/recalc');
			
			$content['script_rel'][]='as-content/as-admin.js?'.AS_VERSION;
			$content['script_var']['as_warning_recalc']=as_lang('admin/stop_recalc_warning');
			
			$content['script_onloads'][]=array(
				"as_recalc_click('dorecalcpoints', document.getElementById('dosaverecalc'), null, 'recalc_ok');"
			);
		}
		
		$content['form']['buttons']['showdefaults']=array(
			'tags' => 'name="doshowdefaults"',
			'label' => as_lang_html('admin/show_defaults_button'),
		);
	}

	
	foreach ($optionnames as $optionname) {
		$optionfield=array(
			'label' => as_lang_html('options/'.$optionname),
			'tags' => 'name="option_'.$optionname.'"',
			'value' => as_html($options[$optionname]),
			'type' => 'number',
			'note' => as_lang_html('admin/points'),
		);
		
		switch ($optionname) {
			case 'points_multiple':
				$prefix='&#215;';
				unset($optionfield['note']);
				break;
				
			case 'points_per_q_voted_up':
			case 'points_per_a_voted_up':
			case 'points_q_voted_max_gain':
			case 'points_a_voted_max_gain':
				$prefix='+';
				break;
			
			case 'points_per_q_voted_down':
			case 'points_per_a_voted_down':
			case 'points_q_voted_max_loss':
			case 'points_a_voted_max_loss':
				$prefix='&ndash;';
				break;
				
			case 'points_base':
				$prefix='+';
				break;
				
			default:
				$prefix='<span style="visibility:hidden;">+</span>'; // for even alignment
				break;
		}
		
		$optionfield['prefix']='<span style="width:1em; display:inline-block; display:-moz-inline-stack;">'.$prefix.'</span>';
		
		$content['form']['fields'][$optionname]=$optionfield;
	}
	
	as_array_insert($content['form']['fields'], 'points_post_a', array('blank0' => array('type' => 'blank')));
	as_array_insert($content['form']['fields'], 'points_vote_up_q', array('blank1' => array('type' => 'blank')));
	as_array_insert($content['form']['fields'], 'points_multiple', array('blank2' => array('type' => 'blank')));
	
	
	$content['navigation']['sub']=as_admin_sub_navigation();

	
	return $content;


/*
	Omit PHP closing tag to help avoid accidental output
*/