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
 * Registrations API Facade class
 *
 * @package			Espresso REST API
 * @subpackage	includes/APIFacades/Espresso_Events_Resource_Facade.class.php
 * @author				Mike Nelson
 *
 * ------------------------------------------------------------------------
 */
abstract class EspressoAPI_Registrations_Resource_Facade extends EspressoAPI_Generic_Resource_Facade{
	var $modelName="Registration";
	var $modelNamePlural="Registrations";
	var $requiredFields=array
		(array('var'=>'id','type'=>'float'),
		array('var'=>'status','type'=>'enum','allowedEnumValues'=>array('approved','not_approved')),
		array('var'=>'date_of_registration','type'=>'datetime'),
		array('var'=>'final_price','type'=>'float'),
		array('var'=>'code','type'=>'string'),
		array('var'=>'url_link','type'=>'string'),
		array('var'=>'is_primary','type'=>'bool'),
		array('var'=>'is_group_registration','type'=>'bool'),
		array('var'=>'is_going','type'=>'bool'),
		array('var'=>'is_checked_in','type'=>'bool'));
	 
	 /**
	  * checks the registration as being checked in, and updates the registration's check-in-quanity
	  * @param string  $registrationId
	  * @param array $queryParameters may contains keys 'quantity' and 'ignorePayment' (values of 'yes' or 'no)
	  * @return array like $this->getRegistration() 
	  */
	 function checkin($registrationId,$queryParameters=array()){
		 return $this->validator->validate($this->_checkin($registrationId,$queryParameters),array('single'=>false));
	}
	/**
	 *implemented in child class for updating a registration as checkedIn 
	 */
	abstract protected function _checkin($registrationId,$queryParameters=array());
	
	/**
	 * checks the registration out, and updates the checked-in-quantity
	 * @param int $registrationId
	 * @param int $queryParameters, may contain keys 'quantity' 
	 * @return array like $this->getRegistration
	 */
	function checkout($registrationId,$queryParameters=array()){
		return $this->validator->validate($this->_checkout($registrationId,$queryParameters),array('single'=>false));	
	}
	/**
	 *implemented in child class for checking-out a registration 
	 */
	abstract protected function _checkout($registrationId,$qty=1);
}