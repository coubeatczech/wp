<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of EspressoAPI_Admin
 *
 * @author mnelson4
 */
class EspressoAPI_Admin {
	var $genericAdmin;//
	function __construct($genericAdmin){
		$this->genericAdmin=$genericAdmin;
		add_action('admin_menu', array($this,'add_menu'),11);
		add_filter('filter_hook_espresso_admin_pages_list',array($this,'add_admin_pages_to_espresso_list'));
	}
	function add_menu(){
		add_submenu_page('event_espresso', __('Event Espresso - API Settings', 'event_espresso'), __('API Settings', 'event_espresso'), 'administrator', EspressoAPI_ADMIN_SETTINGS_PAGE_SLUG, array($this->genericAdmin,'display_api_settings_page'));
	}
	/**
	 * adds our page to the list of EE admin pages listed in {core}/espresso.php about line 509. This way EE core knows
	 * to include our stylesheets and javascript in a request to our event espresso admin page
	 * @param array $pagesArray
	 * @return string 
	 */
	function add_admin_pages_to_espresso_list($pagesArray){
		$pagesArray[]=EspressoAPI_ADMIN_SETTINGS_PAGE_SLUG;
		return $pagesArray;
	}
}

?>
