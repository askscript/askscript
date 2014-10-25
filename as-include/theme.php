<?php
function get_theme_directory_uri(){
	global $current_theme;
	
	return THEME_DIR_URL.'/'.$current_theme;
}

function get_template_file($file){
	global $current_theme;
	if (file_exists(THEME_DIR.$current_theme.'/'.$file.'.php')) {
		return THEME_DIR.$current_theme.'/'.$file.'.php';
	}else{
		as_fatal_error($file.'.php file missing in '.$current_theme.' theme');
	}
}

function get_header(){
	include get_template_file('header');
}

function get_footer(){
	include get_template_file('footer');
}

function get_index_file(){
	return get_template_file('index');
}

function get_content_type(){
	$content_type = 'text/html; charset=utf-8';
	return apply_filters('content_type', $content_type);
}

function site_title(){
	global $content;
	$title = $content['site_title'];
	echo apply_filters('site_title', $title);
}

function get_main_stylesheet(){
	global $current_theme;
	if (file_exists(THEME_DIR.$current_theme.'/style.css')) {
		return get_theme_directory_uri().'/style.css';
	}else{
		as_fatal_error('style.css file missing in '.$current_theme.' theme');
	}	
}

function add_css($name, $location, $version = false, $depend = false){
	global $css;
	$css[$name] = array('location' => $location);
	
	if($version)
		$css[$name]['version'] = $version;
	
	if($depend)
		$css[$name]['depend'] = $depend;
}

function add_js($name, $location, $version = false, $depend = false){
	global $js;
	$js[$name] = array('location' => $location);
	
	if($version)
		$css[$name]['version'] = $version;
	
	if($depend)
		$css[$name]['depend'] = $depend;
}

function site_head(){
	global $css, $js;
	
	add_css('style', get_main_stylesheet(), 1);
	$css = apply_filters('css', $css);
	
	if(!empty($css) && is_array($css))
		foreach($css as $name => $args){
			echo '<link id="css-'.$name.'" href="'.$args['location'].'" rel="stylesheet">';
		}
	
	add_js('jQuery', LIB_DIR_URL.'/jquery-2.1.1.min.js');
	add_js('page', LIB_DIR_URL.'/as-page.js', AS_VERSION, 'jQuery');
	$js = apply_filters('js', $js);
	
	
	if(!empty($js) && is_array($js))
		foreach($js as $name => $args){
			echo '<script id="js-'.$name.'" src="'.$args['location'].'"></script>';
		}
	
	do_action('site_head');
}

function as_footer(){

}
