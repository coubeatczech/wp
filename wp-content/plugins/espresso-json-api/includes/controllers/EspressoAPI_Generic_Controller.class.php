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
 * Generic Controller class
 * hanldes requests like "espresso-api/events/*
 * if you want to create another controller, to hanlde, say, "venues", name the class "Venues_Controller" and place it in "Venues_Controller.class.php" in this same folder.
 * 
 * @package			Espresso REST API
 * @subpackage	includes/controllers/EspressoAPI_Generic_Controller.class.php
 * @author				Mike Nelson
 *
 * ------------------------------------------------------------------------
 */
abstract class EspressoAPI_Generic_Controller {
	/**
	 * Names of the resources requested, plural. Eg, 'Events','Registrations', etc.
	 * @var string
	 */
	var $resourceName;
	/**
	 * THe Resource Facade generally associated with this controller
	 * @var EspressoAPI_Generic_Resource_Facade
	 */
	var $apiFacade;

	function __construct() {
		//@todo while in development, use local API implementations. but once we're done, w
		//we should start using the API implementations in teh core EE plugin
		//they should be hooked with a do_action("include_Resource_{controlelrName}")
		//echo "espressoeventscontroller32:";var_dump(get_class($this));
		preg_match('~^EspressoAPI_(.*)_Controller~', get_class($this), $matches);
		$this->resourceName = $matches[1];
		$this->apiFacade = EspressoAPI_ClassLoader::load($this->resourceName, 'Resource');//new $apiFacadeName;
	}
	/**
	 * for handling http requests for the api. We've already confirmed it's a request for the api.
	 * the requestsis like {wpsite.com}/espresso-api/v1/{controllerName}/$param1/$param2/{sessionId}.$frmat
	 * @param string $param1
	 * @param string $param2
	 * @param string $format
	 * @return type 
	 */
	function handleRequest($param1, $param2,$format) {
		if (empty($param1) && empty($param2)) {
			return $this->generalRequest($format);
		} elseif (!empty($param1) && empty($param2)) {
			return $this->specificRequest($param1,$format);
		} else {
			return $this->specificAttributeRequest($param1, $param2,$format);
		}
	}

	/**
	 * for handling requests like '/events/' 
	 */
	protected function generalRequest($format) {
		if( ! EspressoAPI_Permissions_Wrapper::current_user_can_access_some($_SERVER['REQUEST_METHOD'], $this->resourceName)){
			 throw new EspressoAPI_UnauthorizedException();
		}
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $this->generalRequestGet());
		} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
			return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $this->generalRequestPost($format));
		} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {//technically this oen should only be used for updates, but we'll be generous
			return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $this->generalRequestPut($format));
		} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
			return array(EspressoAPI_STATUS => __("Denied. You requested to delete all events, and we don't think you meant to do that","event_espresso"), EspressoAPI_STATUS_CODE => 405);
		}
		return array(EspressoAPI_STATUS => __("Request Method Not Recognized:","event_espresso") . $_SERVER['REQUEST_METHOD'], EspressoAPI_STATUS_CODE => 405);
	}

	/**
	 * for handling requsts like GET /events for getting all events 
	 * @return array list of objects
	 */
	protected function generalRequestGet(){
		 return $this->apiFacade->getMany($this->realQueryString());
	}
	

	/**
	 *for handling reuqests like POST /events for creating a new event 
	 * @return array with 'id' of newly created object
	 */
	 protected function generalRequestPost($format){
		$parsedInput=$this->parseInputBodyOrError($format);
		return $this->apiFacade->createOrUpdateMany($parsedInput);
	 }
	 
	 /**
	 *for handling reuqests like POST /events for creating a new event 
	 * @return array with 'id' of newly created object
	 */
	 protected function generalRequestPut($format){
		$parsedInput=$this->parseInputBodyOrError($format);
		return $this->apiFacade->createOrUpdateMany($parsedInput);
	 }

	/**
	 * for handling requests liks '/events/14'
	 * @param int $id id of event
	 */
	protected function specificRequest($id,$format) {
		if(!EspressoAPI_Permissions_Wrapper::current_user_can_access_specific($_SERVER['REQUEST_METHOD'], $this->resourceName, $id)){
			 throw new EspressoAPI_UnauthorizedException();
		}
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			$object = $this->specificRequestGet($id);
			if (empty($object))
				return array(EspressoAPI_STATUS => __("Could not find object with id","event_espresso") . $id, EspressoAPI_STATUS_CODE => 404);
			else
				return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $object);
		}elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
			return array(EspressoAPI_STATUS => __("POST (create) on a specific item is not supported. You probably meant to PUT:","event_espresso"), EspressoAPI_STATUS_CODE => 405);
		} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
			$object = $this->specificRequestPut($id,$format);
			if (empty($object))
				return array(EspressoAPI_STATUS => __("Could not find object with id for update:","event_espresso") . $id, EspressoAPI_STATUS_CODE => 404);
			else
				return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $object);
		}elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
			$object = $this->specificRequestDelet__($id);
			if (empty($object))
				return array(EspressoAPI_STATUS => __("Could not find object with id for deletion:","event_espresso") . $id, EspressoAPI_STATUS_CODE => 404);
			else
				return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $object);
		}
		return array(EspressoAPI_STATUS => __("Request Method Not Recognized:","event_espresso") . $_SERVER['REQUEST_METHOD'], EspressoAPI_STATUS_CODE => 405);
	}

	/**
	 * for handling requests like GET /events/13 to get an event with id 13 
	 * @param $id id of the object
	 * @return single object
	 */
	protected function specificRequestGet($id){
		 return $this->apiFacade->getOne($id);
	}
	/**
	 * $takes the input (EG $_REQUEST), finds it's element
	 * called 'body' (or throws an error), and parses it into a php array according to 
	 * the $format it was sent in.
	 * @param string $format 'json','xml'
	 * @return array
	 * @throws EspressoAPI_BadRequestException 
	 */
	protected function parseInputBodyOrError($format='json'){
		 if(array_key_exists('body',$_REQUEST)){
			 $formattedInput=  EspressoAPI_Response_Formatter::parse($_REQUEST['body'], $format);
			 return $formattedInput;
		 }else{
			 throw new EspressoAPI_BadRequestException(__("POST and PUT requests must contain all input in a field called 'body'. Eg: 'body:\"{\'Registrations\':[{\'id:\'12,...}]}\"",'event_espresso')); 
		 }
	}

	/**
	 * for handling requests like PUT /events/13 for updating an event with id 13 
	 * @param $id id of the object
	 * @return boolean success of updating object
	 */
	protected function specificRequestPut($id,$format){
		$parsedInput=$this->parseInputBodyOrError($format);
		return $this->apiFacade->updateOne($id,$parsedInput);

	}

	/**
	 * for handling requests like DELETE /events/23 for deleting an event with id 23 
	 * @param $id id of the object
	 * @return boolean success fo deleting the event
	 */
	protected function specificRequestDelete($id){
		throw new EspressoAPI_MethodNotImplementedException();
	}
	
	/**
	 * Should be overriden by subclass controller which have attributes which aren't proper models.
	 * Eg, the endpoint 'espresso-api/v1/registrations/34/checkin'. They can call parent::canAccessAttribute
	 * if they like to handle all 'normal'
	 * @param string $id
	 * @param string $attribute like 'attendees' from a request like espresso-api/v1/events/23/attendees
	 * @return boolean
	 */
	protected function canAccessAttribute($id, $attribute){
		return EspressoAPI_Permissions_Wrapper::current_user_can_access_some($_SERVER['REQUEST_METHOD'], ucwords($attribute));
	}
	/**
	 * for handling requests like 'events/14/attendees'
	 * @param string $id id of event
	 * @param string $attribute attribute like 'attendees' or 'venue'
	 * @return array of response data for formatting
	 */
	protected function specificAttributeRequest($id, $attribute,$format) {
		//don't do the usual permissions check. This may require special logic. 	
		if( ! $this->canAccessAttribute($id, $attribute, $format)){
			 throw new EspressoAPI_UnauthorizedException();
		}
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			$object = $this->specificAttributeRequestGet($id, $attribute);
			if (empty($object))
				return array(EspressoAPI_STATUS => __("Attribute on object was not found: ","event_espresso") . $attribute, EspressoAPI_STATUS_CODE => 404);
			else
				return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $object);
		}elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$object = $this->specificAttributeRequestPost($id, $attribute);
			if (empty($object))
				return array(EspressoAPI_STATUS => __("Attribute on object could not be created: ","event_espresso") . $attribute, EspressoAPI_STATUS_CODE => 404);
			else
				return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $object);
		}elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {//technically this oen should only be used for updates, but we'll be generous
			$object = $this->specificAttributeRequestPut($id, $attribute);
			if (empty($object))
				return array(EspressoAPI_STATUS => __("Attribute on object was not found for updating","event_espresso") . $attribute, EspressoAPI_STATUS_CODE => 404);
			else
				return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $object);
		}elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
			$object = $this->specificAttributeRequestDelete($id, $attribute);
			if (empty($object))
				return array(EspressoAPI_STATUS => __("Attribute on object was not found for deletion: ","event_espresso") . $attribute, EspressoAPI_STATUS_CODE => 404);
			else
				return array(EspressoAPI_STATUS => __("OK","event_espresso"), EspressoAPI_STATUS_CODE => 200, EspressoAPI_RESPONSE_BODY => $object);
		}
		return array(EspressoAPI_STATUS => __("Request Method Not Recognized:","event_espresso") . $_SERVER['REQUEST_METHOD'], EspressoAPI_STATUS_CODE => '500');
	}

	/**
	 * request like GET events/13/attendees, for getting all attendees at an event 
	 * @param $id id of the object
	 * @param $attribute 3rd part of the URI, in teh above example it would 'attendees'
	 * @return array list of objects (if in a has-many,belongs-to-many,or has-and-bleongs-to-many relationship) or single object (if in a has-one relationship)
	 */
	abstract protected function specificAttributeRequestGet($id, $attribute);

	/**
	 * request like POST events/13/venue, for creating a venue for this event
	 * @param $id id of the object
	 * @param $attribute 3rd part of the URI, in teh above example it would 'attendees'
	 * @return boolean success of object-creation
	 */
	abstract protected function specificAttributeRequestPost($id, $attribute);

	/**
	 * request like PUT events/13/venue, for updating the one-and-only venu for this event
	 * @param $id id of the object
	 * @param $attribute 3rd part of the URI, in teh above example it would 'attendees'
	 * @return boolean success fo object update
	 */
	abstract protected function specificAttributeRequestPut($id, $attribute);

	/**
	 * requests like DELETE events/13/venue, for deleting the venue of ane vent 
	 * @param $id id of the object
	 * @param $attribute 3rd part of the URI, in teh above example it would 'attendees'
	 * @return boolean success of deletion
	 */
	abstract protected function specificAttributeRequestDelete($id, $attribute);
	/**
	 * Function to fix up PHP's messing up POST input containing dots, etc.
	 * @return array 
	 */
	protected function realQueryString() {
		$pairs = explode("&", $_SERVER['QUERY_STRING']);
		$vars = array();
		foreach ($pairs as $pair) {
			$nv = explode("=", $pair);
			if(count($nv)>1){
				$name = urldecode($nv[0]);
				$value = urldecode($nv[1]);
				$vars[$name] = $value;
			}
		}
		return $vars;
	}
}