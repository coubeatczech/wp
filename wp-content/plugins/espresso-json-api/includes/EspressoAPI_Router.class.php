<?php
/**
 * EspressoAPI
 *
 * RESTful API for Even tEspresso
 *
 * @ package			Espresso REST API
 * @ author				Mike Nelson
 * @ copyright		(c) 2008-2011 Event Espresso  All Rights Reserved.
 * @ license			{@link http://eventespresso.com/support/terms-conditions/}   * see Plugin Licensing *
 * @ link					{@link http://www.eventespresso.com}
 * @ since		 		3.2.P
 *
 * ------------------------------------------------------------------------
 *
 * Router class
 *
 * @package			Espresso REST API
 * @subpackage	includes/EspressoAPI_Router.class.php
 * @author				Mike Nelson
 *
 * ------------------------------------------------------------------------
 */
class EspressoAPI_Router{
    function __construct(){
          add_action('parse_query',array($this,'template_redirect'));//template_redirect was original action
    }
	
	/**
	 * Determine if the current query is a public access query, and whether the site
	 * permits it.
	 * @param string $sessionKey what the user used as the "session key" in teh request,
	 * ...specifically, we're looking to see if it's 'public'
	 * @return boolean
	 */
	protected function  publicAccessQuery($sessionKey){
		$allowPublicAccess=get_option(EspressoAPI_ALLOW_PUBLIC_API_ACCESS);
		if($sessionKey=='public' && $allowPublicAccess)
			return true;
		else
			return false;
	}
    /**
     *intercepts requests, and finds ones for the espresso API, and routes them to the appropriate controller, if there is one
	 * and echoes out the controller's output, and exits. Removes ALL actions on 'shutdown' so to prevent warnings
	 * @global WP_User $current_user 
	 * @global boolean $espressoAPI_public_access_query declared here, used within the EspressoAPI_Permissions_Wrapper
     * @return void 
     */
     function template_redirect(){
		 
		//fetch params and sanitize. $_REQUEST variables must be passed by the api before doing anything in teh db
		$apiRequest=get_query_var('espresso-api-request');
		$apiAuthenticate=get_query_var('espresso-api-authenticate');
		$sessionKeyAndMaybeFormat=sanitize_text_field(mysql_real_escape_string(get_query_var('espresso-sessionkey')));
		$sessionKey=$this->stripFormat($sessionKeyAndMaybeFormat);
        $apiParam1=sanitize_text_field(mysql_real_escape_string(get_query_var('espresso-api1')));
        $apiParam2=sanitize_text_field(mysql_real_escape_string(get_query_var('espresso-api2')));
        $apiParam3=sanitize_text_field(mysql_real_escape_string(get_query_var('espresso-api3')));
        
		
		
		if(empty($apiRequest))//this wasn't actually a request to the espresso API, let it go through the normal Wordpress response process
            return;
		
		//checks for the METHOD parameter in $_REQUEST, which sets the request method if
		//the client is unable to use PUT and DELETE methods
		if(array_key_exists('request_method',$_REQUEST) && in_array($_REQUEST['request_method'],array('GET','POST','PUT','DELETE','get','post','put','delete'))){
			$_SERVER['REQUEST_METHOD']=$_REQUEST['request_method'];
			unset($_REQUEST['request_method']);
		}
		
		$format=EspressoAPI_Response_Formatter::findFormatInParams(array($sessionKeyAndMaybeFormat));
		try{
			if($apiAuthenticate=='true'){		
				$controller=EspressoAPI_ClassLoader::load('Authentication',"Controller");
				$response=$controller->authenticate();
			}else{
				if(!empty($sessionKey) && empty($apiParam1))
					throw new EspressoAPI_BadRequestException(__("Invalid request. You should also provide a resource, eg: 'events'. You only provided the following api key:","event-espresso").$sessionkey);
				
				global $current_user, $espressoAPI_public_access_query;
				if($this->publicAccessQuery($sessionKey)){
					$current_user = null;
					wp_set_current_user(0);
					$espressoAPI_public_access_query = true;
				}else{
					$current_user=EspressoAPI_SessionKey_Manager::getUserFromSessionKey($sessionKey);
					wp_set_current_user($current_user->ID);
					//before we proceed ONE INCH
					//ensure they're a valid EE user
					if( ! EspressoAPI_Permissions_Wrapper::current_user_is_any_ee_user()){
						throw new EspressoAPI_UnauthorizedException();
					}
					EspressoAPI_SessionKey_Manager::updateSessionKeyActivity($current_user->ID);
					$espressoAPI_public_access_query = false;
				}
				$controller=EspressoAPI_ClassLoader::load(ucwords($apiParam1),"Controller");
				$response=$controller->handleRequest($apiParam2,$apiParam3,$format);
			}
        } catch (EspressoAPI_MethodNotImplementedException $e) {
			$response= array(EspressoAPI_STATUS => __("Endpoint not yet implemented","event_espresso"), EspressoAPI_STATUS_CODE => 500);
		} catch (EspressoAPI_UnauthorizedException $e) {
			$response= array(EspressoAPI_STATUS => __("Not authorized to access that endpoint","event_espresso"), EspressoAPI_STATUS_CODE => 403);
		} catch(EspressoAPI_ObjectDoesNotExist $e){
			$response= array(EspressoAPI_STATUS => __("Request is ok, but there is no object of specified type with id:","event_espresso")." ".$e->getMessage(), EspressoAPI_STATUS_CODE => 404);
		} catch(EspressoAPI_SpecialException $e){
			$response=array(EspressoAPI_STATUS=>$e->getMessage(),EspressoAPI_STATUS_CODE=>$e->getStatusCode());
		} catch(EspressoAPI_BadRequestException $e){
			$response=array(EspressoAPI_STATUS=>$e->getMessage(),EspressoAPI_STATUS_CODE=>400);
		}catch(EspressoAPI_BadCredentials $e){
			$response=array(EspressoAPI_STATUS=>"Bad username and password combination.",EspressoAPI_STATUS_CODE=>401);
		}
		catch (Exception $e) {
			$response= array(EspressoAPI_STATUS => $e->getMessage(), EspressoAPI_STATUS_CODE => 500);
		}
		if(array_key_exists('debug',$_REQUEST) && isset($e)){
			$response['error'] = $e;
		}
		
		EspressoAPI_Response_Formatter::setContentType($format);
		//NOBODY BUFFERS MY OUTPUT! Because some other plugins, like NextGen gallery
		//and HTTPS plugin, buffer output and echo it on shutdown. But we want to remove
		//the shutdown hooks because OTHER plugins (like wp shopping cart) output
		//stuff on shutdown, leading us to want to deactivate ALL shutdown hooks
		wp_ob_end_flush_all();
		echo EspressoAPI_Response_Formatter::format($response,$format);
		//prevent any silly shutdown functions from outputting warnings on 'shutdown', etc., like wp-e-commerce's wpsc-functions.php
		remove_all_actions('shutdown',1000);
        exit;
    }
	/**
	 * removes the format part of the URL. eg: espresso-api/v1/regisration/32trfwse4.xml 
	 * would be remove the ".xml" part
	 * @param string $urlPart
	 * @return string 
	 */
    function stripFormat($urlPart){
		$posOfDot=strpos($urlPart,".");
		if($posOfDot===FALSE)
			return $urlPart;
		else{
			return substr($urlPart,0,$posOfDot);
		}	
	}
    
}
new EspressoAPI_Router();
