<?php
/**
 * Description of EspressoAPI_SessionKey_Manager
 *
 * @author mnelson4
 */
define('EspressoAPI_SessionKey_MetaKey','EspressoAPI_SessionKey');
define('EspressoAPI_LastActivity_Metakey','EspressoAPI_LastActivity_MetaKey');
class EspressoAPI_SessionKey_Manager {
	static function updateSessionKeyActivity($userId){
		update_user_meta($userId,EspressoAPI_LastActivity_Metakey,time());
	}
	//fetch session key for user
	static function getSessionKeyForUser($userId){
		$sessionKey=get_user_meta($userId,EspressoAPI_SessionKey_MetaKey,true);
		if(empty($sessionKey)){
			$sessionKey=EspressoAPI_Functions::generateRandomString();
			update_user_meta($userId,EspressoAPI_SessionKey_MetaKey,$sessionKey);
		}
		EspressoAPI_SessionKey_Manager::updateSessionKeyActivity($userId);
		return $sessionKey;
	}
	//flush a single sessionkey
	static function regenerateSessionKeyForUser($userId){
		$sessionKey=EspressoAPI_Functions::generateRandomString();
		update_user_meta($userId,EspressoAPI_SessionKey_MetaKey,$sessionKey);
		return $sessionKey;
	}
	//flush all sessionkeys
	static function regeneratAllSessionKeys(){
		global $wpdb;
		$query="SELECT * FROM {$wpdb->users} INNER JOIN {$wpdb->usermeta} 
			ON {$wpdb->users}.ID={$wpdb->usermeta}.user_id 
			WHERE meta_key='".EspressoAPI_SessionKey_MetaKey."'";
		$users=$wpdb->get_results($query,ARRAY_A );
		foreach($users as $user){
			EspressoAPI_SessionKey_Manager::regenerateSessionKeyForUser($user['ID']);
		}
	}
	//get user from sessionKey
	static function getUserFromSessionKey($sessionKey){
		global $wpdb;
		$query=$wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} 
			WHERE meta_key=%s 
				AND
				meta_value=%s",EspressoAPI_SessionKey_MetaKey,$sessionKey);
		$userId=$wpdb->get_var($query);
		
		//check that their session key hasn't expired from inactivity
		$lastActivity=intval(get_user_meta($userId,EspressoAPI_LastActivity_Metakey,true));
		$sessionTimeout=intval(get_option(EspressoAPI_ADMIN_SESSION_TIMEOUT));
		$currentTime=time();
		if($sessionTimeout>0 && $lastActivity+$sessionTimeout<$currentTime){
			EspressoAPI_SessionKey_Manager::regenerateSessionKeyForUser($userId);
			throw new EspressoAPI_UnauthorizedException();
		}
		if(empty($userId)){//we couldn't find a user to match that session key, so they must not be authorized
			throw new EspressoAPI_UnauthorizedException();
		}
		$user=get_user_by('id',$userId);
		return $user;
	}
	//expire session key if older than 
	
	
	
}