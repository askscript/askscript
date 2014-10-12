<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-plugin/basic-adsense/as-basic-adsense.php
	Version: See define()s at top of as-include/as-base.php
	Description: Widget module class for AdSense widget plugin


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

	class as_basic_adsense {
		
		function allow_template($template)
		{
			return ($template!='admin');
		}

		
		function allow_region($region)
		{
			$allow=false;
			
			switch ($region)
			{
				case 'main':
				case 'side':
				case 'full':
					$allow=true;
					break;
			}
			
			return $allow;
		}

		
		function admin_form(&$as_content)
		{
			$saved=false;
			
			if (as_clicked('adsense_save_button')) {	
				$trimchars="=;\"\' \t\r\n"; // prevent common errors by copying and pasting from Javascript
				as_opt('adsense_publisher_id', trim(as_post_text('adsense_publisher_id_field'), $trimchars));
				$saved=true;
			}
			
			return array(
				'ok' => $saved ? 'AdSense settings saved' : null,
				
				'fields' => array(
					array(
						'label' => 'AdSense Publisher ID:',
						'value' => as_html(as_opt('adsense_publisher_id')),
						'tags' => 'name="adsense_publisher_id_field"',
						'note' => 'Example: <i>pub-1234567890123456</i>',
					),
				),
				
				'buttons' => array(
					array(
						'label' => 'Save Changes',
						'tags' => 'name="adsense_save_button"',
					),
				),
			);
		}


		function output_widget($region, $place, $themeobject, $template, $request, $as_content)
		{
			$divstyle='';
			
			switch ($region) {
				case 'full': // Leaderboard
					$divstyle='width:728px; margin:0 auto;';
					
				case 'main': // Leaderboard
					$width=728;
					$height=90;
					$format='728x90_as';
					break;
					
				case 'side': // Wide skyscraper
					$width=160;
					$height=600;
					$format='160x600_as';
					break;
			}
			
?>
<div style="<?php echo $divstyle?>">
<script type="text/javascript">
google_ad_client = <?php echo as_js(as_opt('adsense_publisher_id'))?>;
google_ad_width = <?php echo as_js($width)?>;
google_ad_height = <?php echo as_js($height)?>;
google_ad_format = <?php echo as_js($format)?>;
google_ad_type = "text_image";
google_ad_channel = "";
</script>
<script type="text/javascript"
src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>
</div>
<?php
		}
	
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/