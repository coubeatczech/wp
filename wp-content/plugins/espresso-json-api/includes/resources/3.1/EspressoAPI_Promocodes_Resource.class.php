<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
class EspressoAPI_Promocodes_Resource extends EspressoAPI_Promocodes_Resource_Facade{
	/**
	 * primary ID column for SELECT query when selecting ONLY the primary id
	 */
	protected $primaryIdColumn='Promocode.id';
	var $APIqueryParamsToDbColumns=array(
		'id'=>'Promocode.id',
		'coupon_code'=>'Promocode.coupon_code',
		'amount'=>'Promocode.coupon_code_price',
		'use_percentage'=>'Promocode.use_percentage',
		'description'=>'Promocode.coupon_code_description',
		'apply_to_each_attendee'=>'Promocode.each_attendee',
		'user'=>'Promocode.wp_user',
	);
	var $calculatedColumnsToFilterOn=array('Promocode.quantity_available','Promocode.expiration_date');
	var $selectFields="
		Promocode.id AS 'Promocode.id',
		Promocode.coupon_code AS 'Promocode.coupon_code',
		Promocode.coupon_code_price AS 'Promocode.coupon_code_price',
		Promocode.use_percentage AS 'Promocode.use_percentage',
		Promocode.coupon_code_description AS 'Promocode.coupon_code_description',
		Promocode.each_attendee AS 'Promocode.each_attendee',
		Promocode.wp_user AS 'Promocode.wp_user'";
	var $relatedModels=array();


	protected function constructSQLWhereSubclause($columnName,$operator,$value){
		switch($columnName){
			case 'Promocode.expiration_date':
			case 'Promocode.quantity_available':
				return null;
		}
		return parent::constructSQLWhereSubclause($columnName, $operator, $value);		
	}
	protected function processSqlResults($rows,$keyOpVals){
		global $wpdb;
		$processedRows=array();
		foreach($rows as $row){
			$row['Promocode.quantity_available']=999999;
			$row['Promocode.expiration_date']='9999-01-01 01:01:01';
			
			if(!$this->rowPassesFilterByCalculatedColumns($row,$keyOpVals))
				continue;
			$processedRows[]=$row;
		}
		return $processedRows;
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
		$promocode=array(
		'id'=>$sqlResult['Promocode.id'],
		'coupon_code'=>$sqlResult['Promocode.coupon_code'],
		'amount'=>$sqlResult['Promocode.coupon_code_price'],
		'use_percentage'=>$sqlResult['Promocode.use_percentage'],
		'description'=>$sqlResult['Promocode.coupon_code_description'],
		'apply_to_each_attendee'=>$sqlResult['Promocode.each_attendee'],
		'quantity_available'=>$sqlResult['Promocode.quantity_available'],
		'expiration_date'=>$sqlResult['Promocode.expiration_date'],
		'user'=>$sqlResult['Promocode.wp_user']
		);
		return $promocode; 
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