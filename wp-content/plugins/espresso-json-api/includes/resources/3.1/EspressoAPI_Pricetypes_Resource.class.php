<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
define('ESPRESSOAPI_PRICETYPE_BASE',1);
define('ESPRESSOAPI_PRICETYPE_AMOUNT_SURCHARGE',2);
define('ESPRESSOAPI_PRICETYPE_PERCENT_SURCHARGE',3);
define('ESPRESSOAPI_PRICETYPE_MEMBER_BASE',4);
class EspressoAPI_Pricetypes_Resource extends EspressoAPI_Pricetypes_Resource_Facade{
	/**
	 * primary ID column for SELECT query when selecting ONLY the primary id
	 */
	protected $primaryIdColumn;//SHOULDN'T BE USED BECAUSE THIS IS NEVER PART OF A MYSQL QUERY IN 3.1
	var $APIqueryParamsToDbColumns=array(
		'id'=>null,
		'name'=>null,
		'is_member'=>null,
		'is_discount'=>null,
		'is_tax'=>null,
		'is_percent'=>null,
		'is_global'=>null,
		'order'=>null
	);
	var $calculatedColumnsToFilterOn=array();
	var $selectFields="";
	var $relatedModels=array();
	/**
	 * in 3.1 there is no price_type table. But there are effectively 
	 * 4 price types: “base Price”,”Surcharge Amount”,”Surcharge Percent”, 
	 * and “Member Price”.  
	 */
	var $fakeDbTable=array(
		ESPRESSOAPI_PRICETYPE_BASE=>array(
			"id"=>1,
			'name'=>'Base Price',
			'is_member'=>false,
			'is_discount'=>false,
			'is_tax'=>false,
			'is_percent'=>false,
			'is_global'=>true,
			'order'=>0),
		ESPRESSOAPI_PRICETYPE_AMOUNT_SURCHARGE=>array(
			"id"=>2,
			"name"=>"Surcharge Amount",
			"is_member"=>false,
			"is_discount"=>false,
			"is_tax"=>false,
			"is_percent"=>false,
			"is_global"=>true,
			"order"=>10),
		ESPRESSOAPI_PRICETYPE_PERCENT_SURCHARGE=>array(
			"id"=>3,
			"name"=>"Surcharge Percent",
			"is_member"=>false,
			"is_discount"=>false,
			"is_tax"=>false,
			"is_percent"=>true,
			"is_global"=>true,
			"order"=>10),
		ESPRESSOAPI_PRICETYPE_MEMBER_BASE=>array(
			"id"=>4,
			"name"=>"Member Price",
			"is_member"=>true,
			"is_discount"=>false,
			"is_tax"=>false,
			"is_percent"=>false,
			"is_global"=>true,
			"order"=>0));
	/**
     * gets all events in the database, according to query parmeters
     * @global type $wpdb
     * @param array $queryParameters of key=>values. eg: "array("start_date"=>"2012-04-23","name"=>"Mike Party").
     * @return type 
     */
    function _getMany($queryParameters){
		return new EspressoAPI_MethodNotImplementedException();
    }
    function _create($createParameters){
       return new EspressoAPI_MethodNotImplementedException();
    }
	 /**
	  * shouldn't ever get called
	  */
	 protected function getPrimaryIdColInDb(){
		 return new EspressoAPI_MethodNotImplementedException();
	 }
    /**
     *for handling requests liks '/events/14'
     * @param int $id id of event
     */
	protected function _getOne($id) {
		return new EspressoAPI_MethodNotImplementedException();
	}
	
	/**
	 * takes the results acquired from a DB selection, and extracts
	 * each instance of this model, and compiles into a nice array like
	 * array(12=>("id"=>12,"name"=>"mike party","description"=>"all your base"...)
	 * Also, if we're going to just be finding models that relate
	 * to a specific foreign_key on any table in the query, we can specify
	 * to only return those models using the $idKey and $idValue,
	 * for example if you have a bunch of results from a query like 
	 * "select * FROM events INNER JOIn attendees", and you just want
	 * all the attendees for event with id 13, then you'd call this as follows:
	 * $attendeesForEvent13=parseSQLREsultsForMyDate($results,'Event.id',13);
	 * @param array $sqlResults
	 * @param string/int $idKey
	 * @param string/int $idValue 
	 * @return array compatible with the required reutnr type for this model
	 */
	protected function _extractMyUniqueModelsFromSqlResults($sqlResult){
		
		$price=array(
		'id'=>$sqlResult['Price.id'],
		'amount'=>$sqlResult['Price.event_cost'],
		'name'=>$sqlResult['Price.price_type'],
		'description'=>null,
		'limit'=>$sqlResult['Price.reg_limit'],
		'remaining'=>99999,//$sqlResult['Event.remaining'],
		'start_date'=>null,
		'end_date'=>null,
		);
		return $price; 
	}
	function extractMyColumnsFromApiInput($apiInput,$dbEntries,$options=array()){
		return $dbEntries;
	}
	
	/**
	 * Determines if the current user has specific permission to accesss/manipulate
	 * the resource indicated by $id. If we're calling this just after creating an array representing a resource instance
	 * (array which only needs to be json-encoded before displaying to the user)
	 * then $resource_instance_array can be provided in hopes of avoiding extra querying
	 * @param string $httpMethod like 'get' or 'put'
	 * @param int|float $id
	 * @param array $api_model_object array that could be returned to the user, like for an event that would be array('id'=>1,'code'=>'3ffw3', 'name'=>'party'...)
	 * @return boolean
	 */
	function current_user_has_specific_permission_for($httpMethod,$id,$resource_instance_array = array()){
		throw new EspressoAPI_MethodNotImplementedException(" current_user_has_specific_permission_for not implemented on ".get_class($this));
	}
}
//new Events_Controller();