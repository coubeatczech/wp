<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of EspressoAPI_Authenticate_Controller
 *
 * @author mnelson4
 */
class EspressoAPI_Authentication_Controller {
	//handle login request
		//check if username and password match
			//if so, fetch teh user's session key
			//if not, tell them it was wrong
	function authenticate(){
		
		$username=isset($_REQUEST['username'])?$_REQUEST['username']:null;
		$password=isset($_REQUEST['password'])?$_REQUEST['password']:null;
		$user=wp_authenticate($username,$password);
		if($user instanceof WP_Error){
			throw new EspressoAPI_BadCredentials();
		}
		global $current_user;
		$current_user = $user;
		//only allow admins to authenticate
		if( ! EspressoAPI_Permissions_Wrapper::current_user_is_any_ee_user()){
			throw new EspressoAPI_UnauthorizedException();
		}
		$sessionKey=EspressoAPI_SessionKey_Manager::getSessionKeyForUser($user->ID);
		return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY=>array('session_key'=>$sessionKey));
	}
	
	
	
}

?>
