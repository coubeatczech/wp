<?php
/*
Plugin Name: Event Espresso API Plugin
Plugin URI: http://eventespresso.com
Description: A JSON/XML API for Event Espresso
Version: 2.1.1.P
Author: Event Espresso, (Mike Nelson)
Requiresa at least: Event Espresso 3.1.30, and Wordpress 3.3.0
 *  For Developers: How this plugin generally works:
 *  /includes/EspressoAPI_URL_Rewrite.class.php hooks in our URLs into wordpress, so that 
 * if a URL like /espresso-api/v1/ is requested, the /includes/EspressoAPI_Router.class.php will detect that request and hook in our code to generate a response.
 * The EspressoAPI_Router takes care of including the proper Espresso API Controller per request. Eg, if /espresso-api/v1/events is requested
 * the router will load /includes/controllers/EspressoAPI_Events_COntroller.class.php, and hand the request to it.
 * Most controllers extend /includes/controllers/EspressoAPI_Generic_Controller.class.php, and mostly only differ in terms of which
 * "API" (which is really more of a model/data access object) they themselves load, and how they handle requests for specific attributes 
 * (eg, /espresso-api/v1/events/{id}/registrations, where "registrations" is the attribute).
 * The /includes/helpers/EspressoAPI_ClassLoader.class.php takes care of loading controllers and "APIs", and even loading the API
which corresponds to the current Event Espresso Core version.
 * Each API, for a specific version extends the general API Facade for that model, which extends the Generic API Facade.
 * (Eg, /includes/APIs/3.1/EspressoAPI_Datetimes_API.class.php extends /includes/APIFacades/EspressoAPI_Datetimes_API_Facade.class.php, 
 * which extends /includes/APIFacades/EspressoAPI_Generic_API_Facade.class.php).
 * The Generic API Facade does the bulk of the work of translating an API request into a database query, and formulating the response 
 * and sending it back to the controller, but there are many important specifics each specific API defines (thus each API only
 * hooks into the process by overriding certain Generic API Facade functions, as needed).
 * Once the response array is created by the API, it is returned to the controller, which returns it to the Router, which 
 * puts it into JSON or XML format and prints it to the output buffer.
*/
define('EspressoAPI_DIR_PATH',plugin_dir_path(__FILE__));
define('EspressoAPI_VERSION','2.1.1.P');

//constants relating to responses
//(why use these instead of normal strings? because if you mispell these you get a warning
//...not so with normal strings.)
define('EspressoAPI_STATUS','status');
define('EspressoAPI_STATUS_CODE','status_code');
define('EspressoAPI_USER_FRIENDLY_STATUS','user_friendly_status');
define('EspressoAPI_RESPONSE_BODY','body');
//these constants should eb used as wp option names, input names and ids.
define('EspressoAPI_ADMIN_SESSION_TIMEOUT','espressoapi_admin_session_timeout');
define('EspressoAPI_ALLOW_PUBLIC_API_ACCESS','EspressoAPI_allow_public_api_access');
define('EspressoAPI_DEFAULT_QUERY_LIMITS','EspresoAPI_default_query_limits');
define('EspressoAPI_SHOW_RESOURCES_I_CANT_EDIT_BY_DEFAULT','EspressoAPI_show_resources_i_cant_edit_by_default');
define('EspressoAPI_DEBUG_MODE','EspressoAPI_debug_mode');
require (EspressoAPI_DIR_PATH.'/includes/helpers/EspressoAPI_Exceptions.php');
require (EspressoAPI_DIR_PATH.'/includes/helpers/EspressoAPI_ClassLoader.class.php');
require (EspressoAPI_DIR_PATH.'/includes/helpers/EspressoAPI_Permissions_Wrapper.class.php');
require (EspressoAPI_DIR_PATH.'/includes/helpers/EspressoAPI_SessionKey_Manager.class.php');
require (EspressoAPI_DIR_PATH.'/includes/helpers/EspressoAPI_Functions.class.php');
if(is_admin()){
	require(dirname(__FILE__).'/includes/admin/EspressoAPI_Generic_Admin.class.php');
	require (dirname(__FILE__).'/includes/EspressoAPI_URL_Rewrite.class.php');
}else{
	require (dirname(__FILE__).'/includes/EspressoAPI_URL_Rewrite.class.php');
	require (dirname(__FILE__).'/includes/EspressoAPI_Router.class.php');
	require (dirname(__FILE__).'/includes/helpers/EspressoAPI_Response_Formatter.class.php');
	require (dirname(__FILE__).'/includes/helpers/EspressoAPI_Validator.class.php');
}
/**
 * these helpers are only needed on updates or creates 
 */
if(in_array($_SERVER['REQUEST_METHOD'],array('POST','PUT'))){
	require (dirname(__FILE__).'/includes/helpers/EspressoAPI_Temp_Id_Holder.class.php');
}



/**
 * hook into PUE updates
 */
//Update notifications
add_action('action_hook_espresso_json_update_api', 'ee_json_api_load_pue_update');
function ee_json_api_load_pue_update() {
	global $org_options, $espresso_check_for_updates;
	if ( $espresso_check_for_updates == false )
		return;
		
	if (file_exists(EVENT_ESPRESSO_PLUGINFULLPATH . 'class/pue/pue-client.php')) { //include the file 
		require(EVENT_ESPRESSO_PLUGINFULLPATH . 'class/pue/pue-client.php' );
		$api_key = $org_options['site_license_key'];
		$host_server_url = 'http://eventespresso.com';
		$plugin_slug = array(
			'premium' => array('P' => 'espresso-json-api'),
			'prerelease' => array('B' => 'espresso-json-api-pr')
		);
		$options = array(
			'apikey' => $api_key,
			'lang_domain' => 'event_espresso',
			'checkPeriod' => '24',
			'option_key' => 'site_license_key',
			'options_page_slug' => 'event_espresso',
			'plugin_basename' => plugin_basename(__FILE__),
			'use_wp_update' => FALSE
		);
		$check_for_updates = new PluginUpdateEngineChecker($host_server_url, $plugin_slug, $options); //initiate the class and start the plugin update engine!
	}
}
