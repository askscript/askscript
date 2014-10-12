<?php
	
/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-page-admin-plugins.php
	Version: See define()s at top of as-include/as-base.php
	Description: Controller for admin page listing plugins and showing their options


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

	require_once AS_INCLUDE_DIR.'as-app-admin.php';

	
//	Check admin privileges

	if (!as_admin_check_privileges($as_content))
		return $as_content;
		
		
//	Map modules with options to their containing plugins
	
	$pluginoptionmodules=array();
	
	$tables=as_db_list_tables_lc();
	$moduletypes=as_list_module_types();
	
	foreach ($moduletypes as $type) {
		$modules=as_list_modules($type);
		
		foreach ($modules as $name) {
			$module=as_load_module($type, $name);
			
			if (method_exists($module, 'admin_form')) {
				$info=as_get_module_info($type, $name);
				$pluginoptionmodules[$info['directory']][]=array(
					'type' => $type,
					'name' => $name,
				);
			}
		}
	}


//	Prepare content for theme
	
	$as_content=as_content_prepare();

	$as_content['title']=as_lang_html('admin/admin_title').' - '.as_lang_html('admin/plugins_title');
	
	$as_content['error']=as_admin_page_error();
	
	$as_content['script_rel'][]='as-content/as-admin.js?'.AS_VERSION;

	$pluginfiles=glob(AS_PLUGIN_DIR.'*/as-plugin.php');
	
	foreach ($moduletypes as $type) {
		$modules=as_load_modules_with($type, 'init_queries');

		foreach ($modules as $name => $module) {
			$queries=$module->init_queries($tables);
		
			if (!empty($queries)) {
				if (as_is_http_post())
					as_redirect('install');
				
				else
					$as_content['error']=strtr(as_lang_html('admin/module_x_database_init'), array(
						'^1' => as_html($name),
						'^2' => as_html($type),
						'^3' => '<a href="'.as_path_html('install').'">',
						'^4' => '</a>',
					));
			}
		}
	}
	
	if ( as_is_http_post() && !as_check_form_security_code('admin/plugins', as_post_text('as_form_security_code')) ) {
		$as_content['error']=as_lang_html('misc/form_security_reload');
		$showpluginforms=false;
	} else
		$showpluginforms=true;

	if (count($pluginfiles)) {
		foreach ($pluginfiles as $pluginindex => $pluginfile) {
			$plugindirectory=dirname($pluginfile).'/';
			$hash=as_admin_plugin_directory_hash($plugindirectory);
			$showthisform=$showpluginforms && (as_get('show')==$hash);
			
			$contents=file_get_contents($pluginfile);
			
			$metadata=as_admin_addon_metadata($contents, array(
				'name' => 'Plugin Name',
				'uri' => 'Plugin URI',
				'description' => 'Plugin Description',
				'version' => 'Plugin Version',
				'date' => 'Plugin Date',
				'author' => 'Plugin Author',
				'author_uri' => 'Plugin Author URI',
				'license' => 'Plugin License',
				'min_q2a' => 'Plugin Minimum Question2Answer Version',
				'min_php' => 'Plugin Minimum PHP Version',
				'update' => 'Plugin Update Check URI',
			));
			
			if (strlen(@$metadata['name']))
				$namehtml=as_html($metadata['name']);
			else
				$namehtml=as_lang_html('admin/unnamed_plugin');
				
			if (strlen(@$metadata['uri']))
				$namehtml='<a href="'.as_html($metadata['uri']).'">'.$namehtml.'</a>';
			
			$namehtml='<b>'.$namehtml.'</b>';
				
			if (strlen(@$metadata['version']))
				$namehtml.=' v'.as_html($metadata['version']);
				
			if (strlen(@$metadata['author'])) {
				$authorhtml=as_html($metadata['author']);
				
				if (strlen(@$metadata['author_uri']))
					$authorhtml='<a href="'.as_html($metadata['author_uri']).'">'.$authorhtml.'</a>';
					
				$authorhtml=as_lang_html_sub('main/by_x', $authorhtml);
				
			} else
				$authorhtml='';
				
			if (strlen(@$metadata['version']) && strlen(@$metadata['update'])) {
				$elementid='version_check_'.md5($plugindirectory);
				
				$updatehtml='(<span id="'.$elementid.'">...</span>)';
				
				$as_content['script_onloads'][]=array(
					"as_version_check(".as_js($metadata['update']).", 'Plugin Version', ".as_js($metadata['version'], true).", 'Plugin URI', ".as_js($elementid).");"
				);

			} else
				$updatehtml='';
			
			if (strlen(@$metadata['description']))
				$deschtml=as_html($metadata['description']);
			else
				$deschtml='';
			
			if (isset($pluginoptionmodules[$plugindirectory]) && !$showthisform)
				$deschtml.=(strlen($deschtml) ? ' - ' : '').'<a href="'.
					as_admin_plugin_options_path($plugindirectory).'">'.as_lang_html('admin/options').'</a>';
				
			$pluginhtml=$namehtml.' '.$authorhtml.' '.$updatehtml.'<br>'.$deschtml.(strlen($deschtml) ? '<br>' : '').
				'<small style="color:#666">'.as_html($plugindirectory).'</small>';
				
			if (as_as_version_below(@$metadata['min_q2a']))
				$pluginhtml='<strike style="color:#999">'.$pluginhtml.'</strike><br><span style="color:#f00">'.
					as_lang_html_sub('admin/requires_q2a_version', as_html($metadata['min_q2a'])).'</span>';
					
			elseif (as_php_version_below(@$metadata['min_php']))
				$pluginhtml='<strike style="color:#999">'.$pluginhtml.'</strike><br><span style="color:#f00">'.
					as_lang_html_sub('admin/requires_php_version', as_html($metadata['min_php'])).'</span>';
				
			$as_content['form_plugin_'.$pluginindex]=array(
				'tags' => 'id="'.as_html($hash).'"',
				'style' => 'tall',
				'fields' => array(
					array(
						'type' => 'custom',
						'html' => $pluginhtml,
					)
				),
			);
			
			if ($showthisform && isset($pluginoptionmodules[$plugindirectory]))
				foreach ($pluginoptionmodules[$plugindirectory] as $pluginoptionmodule) {
					$type=$pluginoptionmodule['type'];
					$name=$pluginoptionmodule['name'];
				
					$module=as_load_module($type, $name);
				
					$form=$module->admin_form($as_content);

					if (!isset($form['tags']))
						$form['tags']='method="post" action="'.as_admin_plugin_options_path($plugindirectory).'"';
					
					if (!isset($form['style']))
						$form['style']='tall';
						
					$form['boxed']=true;
			
					$form['hidden']['as_form_security_code']=as_get_form_security_code('admin/plugins');
			
					$as_content['form_plugin_options']=$form;
				}

		}
	}
	
	$as_content['navigation']['sub']=as_admin_sub_navigation();

	
	return $as_content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/