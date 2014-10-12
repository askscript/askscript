<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-plugin/tag-cloud-widget/as-tag-cloud.php
	Version: See define()s at top of as-include/as-base.php
	Description: Widget module class for tag cloud plugin


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

	class as_tag_cloud {
		
		function option_default($option)
		{
			if ($option=='tag_cloud_count_tags')
				return 100;
			elseif ($option=='tag_cloud_font_size')
				return 24;
			elseif ($option=='tag_cloud_size_popular')
				return true;
		}

		
		function admin_form()
		{
			$saved=false;
			
			if (as_clicked('tag_cloud_save_button')) {
				as_opt('tag_cloud_count_tags', (int)as_post_text('tag_cloud_count_tags_field'));
				as_opt('tag_cloud_font_size', (int)as_post_text('tag_cloud_font_size_field'));
				as_opt('tag_cloud_size_popular', (int)as_post_text('tag_cloud_size_popular_field'));
				$saved=true;
			}
			
			return array(
				'ok' => $saved ? 'Tag cloud settings saved' : null,
				
				'fields' => array(
					array(
						'label' => 'Maximum tags to show:',
						'type' => 'number',
						'value' => (int)as_opt('tag_cloud_count_tags'),
						'suffix' => 'tags',
						'tags' => 'name="tag_cloud_count_tags_field"',
					),

					array(
						'label' => 'Starting font size:',
						'suffix' => 'pixels',
						'type' => 'number',
						'value' => (int)as_opt('tag_cloud_font_size'),
						'tags' => 'name="tag_cloud_font_size_field"',
					),
					
					array(
						'label' => 'Font size represents tag popularity',
						'type' => 'checkbox',
						'value' => as_opt('tag_cloud_size_popular'),
						'tags' => 'name="tag_cloud_size_popular_field"',
					),
				),
				
				'buttons' => array(
					array(
						'label' => 'Save Changes',
						'tags' => 'name="tag_cloud_save_button"',
					),
				),
			);
		}

		
		function allow_template($template)
		{
			$allow=false;
			
			switch ($template)
			{
				case 'activity':
				case 'as':
				case 'questions':
				case 'hot':
				case 'ask':
				case 'categories':
				case 'question':
				case 'tag':
				case 'tags':
				case 'unanswered':
				case 'user':
				case 'users':
				case 'search':
				case 'admin':
				case 'custom':
					$allow=true;
					break;
			}
			
			return $allow;
		}

		
		function allow_region($region)
		{
			return ($region=='side');
		}
		

		function output_widget($region, $place, $themeobject, $template, $request, $as_content)
		{
			require_once AS_INCLUDE_DIR.'as-db-selects.php';
			
			$populartags=as_db_single_select(as_db_popular_tags_selectspec(0, (int)as_opt('tag_cloud_count_tags')));
			
			reset($populartags);
			$maxcount=current($populartags);
			
			$themeobject->output(
				'<h2 style="margin-top:0; padding-top:0;">',
				as_lang_html('main/popular_tags'),
				'</h2>'
			);
			
			$themeobject->output('<div style="font-size:10px;">');
			
			$maxsize=as_opt('tag_cloud_font_size');
			$scale=as_opt('tag_cloud_size_popular');
			$blockwordspreg=as_get_block_words_preg();
			
			foreach ($populartags as $tag => $count) {
				if (count(as_block_words_match_all($tag, $blockwordspreg)))
					continue; // skip censored tags
					
				$size=number_format(($scale ? ($maxsize*$count/$maxcount) : $maxsize), 1);
				
				if (($size>=5) || !$scale)
					$themeobject->output('<a href="'.as_path_html('tag/'.$tag).'" style="font-size:'.$size.'px; vertical-align:baseline;">'.as_html($tag).'</a>');
			}
			
			$themeobject->output('</div>');
		}
	
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/