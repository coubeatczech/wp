<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of EspressoAPI_Admin_Common
 *
 * @author mnelson4
 */

define('EspressoAPI_ADMIN_REAUTHENTICATE','espressoapi_admin_reauthenticate');
define('EspressoAPI_ADMIN_SESSION_TIMEOUT_OPTIONS','EspressoAPI_session_timeout_options');
define('EspressoAPI_ADMIN_SETTINGS_PAGE_SLUG','espresso-api-settings');
class EspressoAPI_Generic_Admin {
	function __construct(){
		//echo "generic admin loaded";
		add_action('action_hook_espresso_include_admin_files_start', array($this,'loadVersionSpecificHooks'), 30);
	}
	function loadVersionSpecificHooks(){
		$version = substr(EVENT_ESPRESSO_VERSION, 0, 3);
		$genericAdminFilePath=EspressoAPI_DIR_PATH."includes/admin/EspressoAPI_Generic_Admin.class.php";
		$adminFileName="EspressoAPI_Admin";
		$adminFilePath=  EspressoAPI_DIR_PATH."includes/admin/$version/$adminFileName.class.php";
		if(file_exists($genericAdminFilePath) && file_exists($adminFilePath)){
			require_once($genericAdminFilePath);
			require_once($adminFilePath);
			return new $adminFileName($this);
		}
	}
	function display_api_settings_page(){
		if(isset($_POST[EspressoAPI_ADMIN_REAUTHENTICATE]) && $_POST[EspressoAPI_ADMIN_REAUTHENTICATE] == 'true'){
			EspressoAPI_SessionKey_Manager::regeneratAllSessionKeys();
		}
		if(isset($_POST[EspressoAPI_ADMIN_SESSION_TIMEOUT])){
			update_option(EspressoAPI_ADMIN_SESSION_TIMEOUT,$_POST[EspressoAPI_ADMIN_SESSION_TIMEOUT]);
		}
		if(isset($_POST[EspressoAPI_ALLOW_PUBLIC_API_ACCESS])){
			update_option(EspressoAPI_ALLOW_PUBLIC_API_ACCESS,$_POST[EspressoAPI_ALLOW_PUBLIC_API_ACCESS]);
		}
		if(isset($_POST[EspressoAPI_DEFAULT_QUERY_LIMITS])){
			update_option(EspressoAPI_DEFAULT_QUERY_LIMITS,$_POST[EspressoAPI_DEFAULT_QUERY_LIMITS]);
		}
		if(isset($_POST[EspressoAPI_DEBUG_MODE])){
			update_option(EspressoAPI_DEBUG_MODE,$_POST[EspressoAPI_DEBUG_MODE]);
		}
		//if roles and permissions addon is active, we add another var for that
		if(defined('ESPRESSO_MANAGER_PRO_VERSION')){
			if(isset($_POST[EspressoAPI_SHOW_RESOURCES_I_CANT_EDIT_BY_DEFAULT])){
				update_option(EspressoAPI_SHOW_RESOURCES_I_CANT_EDIT_BY_DEFAULT,$_POST[EspressoAPI_SHOW_RESOURCES_I_CANT_EDIT_BY_DEFAULT]);
			}
			
		}
		$templateVars=array();
		$templateVars[EspressoAPI_ADMIN_SESSION_TIMEOUT]=get_option(EspressoAPI_ADMIN_SESSION_TIMEOUT);
		$templateVars[EspressoAPI_ADMIN_SESSION_TIMEOUT_OPTIONS]=apply_filters("filter_hook_espresso_api_session_timeout_options",
					array(
						'1 Minute'=>60,
						'5 Minutes'=>60*5,
						'10 Minutes'=>60*10,
						'20 Minutes'=>60*20,
						'An Hour'=>60*60,
						'6 Hours'=>60*60*6,
						'Never'=>-1));
		$templateVars[EspressoAPI_DEFAULT_QUERY_LIMITS]=get_option(EspressoAPI_DEFAULT_QUERY_LIMITS);
		$templateVars[EspressoAPI_ALLOW_PUBLIC_API_ACCESS]=get_option(EspressoAPI_ALLOW_PUBLIC_API_ACCESS);
		$templateVars[EspressoAPI_DEBUG_MODE]=get_option(EspressoAPI_DEBUG_MODE);
		if(empty($templateVars[EspressoAPI_DEFAULT_QUERY_LIMITS])){
			$templateVars[EspressoAPI_DEFAULT_QUERY_LIMITS]=array(
				'Events'=>50,
				'Attendees'=>100,
				'Registrations'=>100);
		}
		if(defined('ESPRESSO_MANAGER_PRO_VERSION')){
			$templateVars[EspressoAPI_SHOW_RESOURCES_I_CANT_EDIT_BY_DEFAULT] = get_option(EspressoAPI_SHOW_RESOURCES_I_CANT_EDIT_BY_DEFAULT);
		}
		
		
		
		
		$this->includeVersionedTemplate('settings.php',$templateVars);
	}
	
	private function includeVersionedTemplate($templateFileName,$templateVars=null){
		$version = substr(EVENT_ESPRESSO_VERSION, 0, 3);	
		include(EspressoAPI_DIR_PATH."includes/admin/$version/templates/$templateFileName");
	}
}
new EspressoAPI_Generic_Admin();