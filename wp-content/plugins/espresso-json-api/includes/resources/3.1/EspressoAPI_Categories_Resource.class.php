<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
class EspressoAPI_Categories_Resource extends EspressoAPI_Categories_Resource_Facade{
	/**
	 * primary ID column for SELECT query when selecting ONLY the primary id
	 */
	protected $primaryIdColumn='Category.id';
	var $APIqueryParamsToDbColumns=array(
		'id'=>'Category.id',
		'name'=>'Category.category_name',
		'identifier'=>'Category.category_identifier',
		'description'=>'Category.category_desc',
		'user'=>'Category.wp_user'
	);
	var $calculatedColumnsToFilterOn=array();
	var $selectFields="
		Category.id AS 'Category.id',
		Category.category_name AS 'Category.category_name',
		Category.category_identifier AS 'Category.identifier',
		Category.category_desc AS 'Category.description',
		Category.wp_user AS 'Category.wp_user'";
	var $relatedModels=array();
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
		$category=array(
		'id'=>$sqlResult['Category.id'],
		'name'=>$sqlResult['Category.category_name'],
		'identifier'=>$sqlResult['Category.identifier'],
		'description'=>$sqlResult['Category.description'],
		'user'=>$sqlResult['Category.wp_user']
		);
		return $category; 
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