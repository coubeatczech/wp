<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
class EspressoAPI_Attendees_Resource extends EspressoAPI_Attendees_Resource_Facade{
	/**
	 * primary ID column for SELECT query when selecting ONLY the primary id
	 */
	protected $primaryIdColumn='Attendee.id';
	var $APIqueryParamsToDbColumns=array(
		'id'=>'Attendee.id',
		'firstname'=>'Attendee.fname',
		'lastname'=>'Attendee.lname',
		'address'=>'Attendee.address',
		'address2'=>'Attendee.address2',
		'city'=>'Attendee.city',
		'state'=>'Attendee.state',
		'country'=>'Attendee.country_id',
		'zip'=>'Attendee.zip',
		'email'=>'Attendee.email',
		'phone'=>'Attendee.phone'
		); 
    
	var $calculatedColumnsToFilterOn=array();
	var $selectFields="
		Attendee.id AS 'Attendee.id',
		Attendee.fname as 'Attendee.fname',
		Attendee.lname as 'Attendee.lname',
		Attendee.address as 'Attendee.address',
		Attendee.address2 as 'Attendee.address2',
		Attendee.city as 'Attendee.city',
		Attendee.state as 'Attendee.state',
		Attendee.country_id as 'Attendee.country',
		Attendee.zip as 'Attendee.zip',
		Attendee.email as 'Attendee.email',
		Attendee.phone as 'Attendee.phone'";
	var $relatedModels=array(
		"Event"=>array('modelName'=>'Event', 'modelNamePlural'=>"Events",'hasMany'=>true),
		"Registration"=>array('modelName'=>'Registration','modelNamePlural'=>"Registrations",'hasMany'=>true));
	function getManyConstructQuery($sqlSelect,$whereSql){
		global $wpdb;
		$sql = "
            SELECT				
				{$sqlSelect}
            FROM
                {$wpdb->prefix}events_attendee Attendee
			LEFT JOIN
				{$wpdb->prefix}events_detail Event ON Event.id=Attendee.event_id
			$whereSql";		
		return $sql;
	}
	/**
	 *for taking the info in the $sql row and formatting it according
	 * to the model
	 * @param $sqlRow a row from wpdb->get_results
	 * @return array formatted for API, but only toplevel stuff usually (usually no nesting)
	 */
	protected function _extractMyUniqueModelsFromSqlResults($sqlResult){
			$attendee=array(
				'id'=>$sqlResult['Attendee.id'],
				'firstname'=>$sqlResult['Attendee.fname'],
				'lastname'=>$sqlResult['Attendee.lname'],
				'address'=>$sqlResult['Attendee.address'],
				'address2'=>$sqlResult['Attendee.address2'],
				'city'=>$sqlResult['Attendee.city'],
				'state'=>$sqlResult['Attendee.state'],
				'country'=>$sqlResult['Attendee.country'],
				'zip'=>$sqlResult['Attendee.zip'],
				'email'=>$sqlResult['Attendee.email'],
				'phone'=>$sqlResult['Attendee.phone'],
				'comments'=>null,
				'notes'=>null
				);
			return $attendee;
	}
	


	/**
	 * gets all the database column values from api input
	 * @param array $apiInput either like array('events'=>array(array('id'=>... 
	 * //OR like array('event'=>array('id'=>...
	 * @return array like array('wp_events_attendee'=>array(12=>array('id'=>12,name=>'bob'... 
	 */
	function extractMyColumnsFromApiInput($apiInput,$dbEntries,$options=array()){
		$options=shortcode_atts(array('correspondingAttendeeId'=>null),$options);
		$models=$this->extractModelsFromApiInput($apiInput);
		
		foreach($models as $thisModel){
			if(!array_key_exists('id', $thisModel)){
				throw new EspressoAPI_SpecialException(__("No ID provided on registration","event_espresso"));
			}
			$thisModelId=$options['correspondingAttendeeId']?$options['correspondingAttendeeId']:$thisModel['id'];
			if(EspressoAPI_Temp_Id_Holder::isTempId($thisModelId)){
				$forCreate=true;
			}else{
				$forCreate=false;
			}
			foreach($this->requiredFields as $fieldInfo){
				$apiField=$fieldInfo['var'];
				if(array_key_exists($apiField,$thisModel)){//provide default value
					$apiValue=$thisModel[$apiField];
					$fieldMissing=false;
				}else{
					$fieldMissing=true;
				}
				//howe we assign the dbValue:
				//case 1: if the field is missing and we're creating: provide a default
				//case 2: if the field is present and we're creating: use it
				//case 3: if the field is missing and we're updating: ignore it (continue)
				//case 4: if the field is present and we're updating: use it
				if($fieldMissing && !$forCreate){//case 2
					continue;
				}
				$useDefault=$fieldMissing && $forCreate;//if $useDefault is true: case 1, otherwise case 2 or 4
				switch($apiField){
					case 'id':
						$dbCol=$apiField;
						////if both this attendee's id is a temp ID, and its been suuplied a 'correspondingAttendeeId' 
						//that's a temp ID, set the two of them to be equal
						$dbValue=$thisModelId;
						break;
					case 'firstname':
						$dbCol='fname';
						if($useDefault){
							$dbValue='';
						}else{
							$dbValue=$apiValue;
						}
						break;
					case 'lastname':
						$dbCol='lname';
						if($useDefault){
							$dbValue='';
						}else{
							$dbValue=$apiValue;
						}
						
						break;
					case 'address':
					case 'address2':
					case 'city':
					case 'state':
					case 'zip':
					case 'email':
					case 'phone':
						$dbCol=$apiField;
						if($useDefault){
							$dbValue='';
						}else{
							$dbValue=$apiValue;
						}
						break;
					case 'country':
						$dbCol='country_id';
						if($useDefault){
							$dbValue='';
						}else{
							$dbValue=$apiValue;
						}
						break;
				}
				$dbEntries[EVENTS_ATTENDEE_TABLE][$thisModelId][$dbCol]=$dbValue;
			}
			
		}
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
		//we don't care what http method they're using in this case.
		if(is_array($resource_instance_array) && isset($resource_instance_array['Events'][0]['id'])){
			$event_id = $resource_instance_array['Event']['id'];
		}else{
			global $wpdb;
			$event_id = $wpdb->get_var($wpdb->prepare("SELECT event_id FROM ".EVENTS_ATTENDEE_TABLE." WHERE id=%d",$id));
		}
		return EspressoAPI_Permissions_Wrapper::espresso_is_my_event($event_id);
	}
}
//new Events_Controller();