<?php


	/* class as_html_theme extends as_html_theme_base
	{	

		function head_script() // change style of WYSIWYG editor to match theme better
		{
			as_html_theme_base::head_script();
			
			$this->output(
				'<script type="text/javascript">',
				"if (typeof as_wysiwyg_editor_config == 'object')",
				"\tas_wysiwyg_editor_config.skin='kama';",
				'</script>'
			);
		}
		
		function nav_user_search() // outputs login form if user not logged in
		{
			if (!as_is_logged_in()) {
				$login=@$this->content['navigation']['user']['login'];
				
				if (isset($login) && !AS_FINAL_EXTERNAL_USERS) {
					$this->output(
						'<!--[Begin: login form]-->',				
						'<form id="as-loginform" action="'.$login['url'].'" method="post">',
							'<input type="text" id="as-userid" name="emailhandle" placeholder="'.trim(as_lang_html(as_opt('allow_login_email_only') ? 'users/email_label' : 'users/email_handle_label'), ':').'" />',
							'<input type="password" id="as-password" name="password" placeholder="'.trim(as_lang_html('users/password_label'), ':').'" />',
							'<div id="as-rememberbox"><input type="checkbox" name="remember" id="as-rememberme" value="1"/>',
							'<label for="as-rememberme" id="as-remember">'.as_lang_html('users/remember').'</label></div>',
							'<input type="hidden" name="code" value="'.as_html(as_get_form_security_code('login')).'"/>',
							'<input type="submit" value="'.$login['label'].'" id="as-login" name="dologin" />',
						'</form>',				
						'<!--[End: login form]-->'
					);
					
					unset($this->content['navigation']['user']['login']); // removes regular navigation link to log in page
				}
			}
			
			as_html_theme_base::nav_user_search();
		}
		
		function logged_in() 
		{
			if (as_is_logged_in()) // output user avatar to login bar
				$this->output(
					'<div class="as-logged-in-avatar">',
					AS_FINAL_EXTERNAL_USERS
					? as_get_external_avatar_html(as_get_logged_in_userid(), 24, true)
					: as_get_user_avatar_html(as_get_logged_in_flags(), as_get_logged_in_email(), as_get_logged_in_handle(),
						as_get_logged_in_user_field('avatarblobid'), as_get_logged_in_user_field('avatarwidth'), as_get_logged_in_user_field('avatarheight'),
						24, true),
            		'</div>'
            	);				
			
			as_html_theme_base::logged_in();
			
			if (as_is_logged_in()) { // adds points count after logged in username
				$userpoints=as_get_logged_in_points();
				
				$pointshtml=($userpoints==1)
					? as_lang_html_sub('main/1_point', '1', '1')
					: as_lang_html_sub('main/x_points', as_html(number_format($userpoints)));
						
				$this->output(
					'<span class="as-logged-in-points">',
					'('.$pointshtml.')',
					'</span>'
				);
			}
		}
    
		function body_header() // adds login bar, user navigation and search at top of page in place of custom header content
		{
			$this->output('<div id="as-login-bar"><div id="as-login-group">');
			$this->nav_user_search();
            $this->output('</div></div>');
        }
		
		function header_custom() // allows modification of custom element shown inside header after logo
		{
			if (isset($this->content['body_header'])) {
				$this->output('<div class="header-banner">');
				$this->output_raw($this->content['body_header']);
				$this->output('</div>');
			}
		}
		
		function header() // removes user navigation and search from header and replaces with custom header content. Also opens new <div>s
		{	
			$this->output('<div class="as-header">');
			
			$this->logo();						
			$this->header_clear();
			$this->header_custom();

			$this->output('</div> <!-- END as-header -->', '');

			$this->output('<div class="as-main-shadow">', '');
			$this->output('<div class="as-main-wrapper">', '');
			$this->nav_main_sub();

		}
		
		function sidepanel() // removes sidebar for user profile pages
		{
			if ($this->template!='user')
				as_html_theme_base::sidepanel();
		}
		
		function footer() // prevent display of regular footer content (see body_suffix()) and replace with closing new <div>s
		{
			$this->output('</div> <!-- END main-wrapper -->');
			$this->output('</div> <!-- END main-shadow -->');		
		}		
		
		function title() // add RSS feed icon after the page title
		{
			as_html_theme_base::title();
			
			$feed=@$this->content['feed'];
			
			if (!empty($feed))
				$this->output('<a href="'.$feed['url'].'" title="'.@$feed['label'].'"><img src="'.$this->rooturl.'images/rss.jpg" alt="" width="16" height="16" border="0" class="as-rss-icon"/></a>');
		}
		
		function q_item_stats($q_item) // add view count to question list
		{
			$this->output('<div class="as-q-item-stats">');
			
			$this->voting($q_item);
			$this->a_count($q_item);
			as_html_theme_base::view_count($q_item);

			$this->output('</div>');
		}
		
		function view_count($q_item) // prevent display of view count in the usual place
		{	
			if ($this->template=='question')
				as_html_theme_base::view_count($q_item);
		}
		
		function body_suffix() // to replace standard Q2A footer
        {
			$this->output('<div class="as-footer-bottom-group">');
			as_html_theme_base::footer();
			$this->output('</div> <!-- END footer-bottom-group -->', '');
        }
		
		function attribution()
		{
			$this->output(
				'<div class="as-attribution">',
				'&nbsp;| Snow Theme by <a href="http://www.q2amarket.com">Q2A Market</a>',
				'</div>'
			);

			as_html_theme_base::attribution();
		}
		
	} */


/*
	Omit PHP closing tag to help avoid accidental output
*/