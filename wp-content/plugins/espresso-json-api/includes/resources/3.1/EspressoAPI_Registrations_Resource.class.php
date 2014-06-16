<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
class EspressoAPI_Registrations_Resource extends EspressoAPI_Registrations_Resource_Facade{
	/**
	 * primary ID column for SELECT query when selecting ONLY the primary id
	 */
	protected $primaryIdColumn='Attendee.id';
	var $APIqueryParamsToDbColumns=array(
		//"id"=>"Attendee.id",
		"date_of_registration"=>"Attendee.date_of_registration",
		'final_price'=>'Attendee.final_price',
		'code'=>'Attendee.registration_id',
		//'is_primary'=>'Attendee.is_primary',
		'is_checked_in'=>'Attendee.checked_in');
	var $calculatedColumnsToFilterOn=array('Registration.id', 'Registration.status','Registration.url_link','Registration.is_going','Registration.is_primary');
	var $selectFields="
		Attendee.id AS 'Registration.id',
		Attendee.id AS 'Attendee.id',
		Attendee.date AS 'Attendee.date',
		Attendee.final_price as 'Attendee.final_price',
		Attendee.orig_price as 'Attendee.orig_price',
		Attendee.registration_id as 'Attendee.registration_id',
		Attendee.is_primary as 'Attendee.is_primary',
		Attendee.quantity as 'Attendee.quantity',
		Attendee.checked_in as 'Attendee.checked_in',
		Attendee.checked_in_quantity as 'Attendee.checked_in_quantity',
		Attendee.price_option as 'Attendee.price_option',
		Attendee.event_time as 'Attendee.event_time',
		Attendee.end_time as 'Attendee.end_time'";
	var $relatedModels=array(
		"Event"=>array('modelName'=>'Event', 'modelNamePlural'=>"Events",'hasMany'=>false),
		"Attendee"=>array('modelName'=>'Attendee','modelNamePlural'=>"Attendees",'hasMany'=>false),
		"Transaction"=>array('modelName'=>'Transaction','modelNamePlural'=>"Transactions",'hasMany'=>false),
		'Datetime'=>array('modelName'=>'Datetime','modelNamePlural'=>'Datetimes','hasMany'=>false),
		'Price'=>array('modelName'=>'Price','modelNamePlural'=>'Prices','hasMany'=>false));
/**
 * an array for caching  registration ids taht related to group registrations
 * it coudl look like array('2etf2w24rtw'=>true, '54tgsdsf'=>false), meaning
 * '2etf2w24rtw' is a known group registration, but '54tgsdsf' is known to NOT 
 * be a gruop registration. All other registartion ids are not yet known andshould eb cached.
 * @var type 
 */
	private $knownGroupRegistrationRegIds=array();
	function getManyConstructQuery($sqlSelect,$whereSql){
		global $wpdb;
		$sql = "
            SELECT			
				{$sqlSelect}
            FROM
                {$wpdb->prefix}events_attendee Attendee
			LEFT JOIN
				{$wpdb->prefix}events_detail Event ON Event.id=Attendee.event_id
			LEFT JOIN
				{$wpdb->prefix}events_attendee_meta AttendeeMeta ON Attendee.id=AttendeeMeta.attendee_id
			LEFT JOIN
				{$wpdb->prefix}events_prices Price ON Attendee.event_id=Price.event_id and 
													(
														(Price.surcharge_type='flat_rate'
														AND(
															Price.member_price+Price.surcharge=Attendee.orig_price
															OR
															Price.event_cost+Price.surcharge=Attendee.orig_price
															)
														)
													OR
														(Price.surcharge_type='pct'
														AND(
															Price.member_price*Price.surcharge/100=Attendee.orig_price
															OR
															Price.event_cost*Price.surcharge/100=Attendee.orig_price
															)
														)
													)
			LEFT JOIN
				{$wpdb->prefix}events_start_end StartEnd ON StartEnd.start_time=Attendee.event_time AND StartEnd.end_time=Attendee.end_time AND StartEnd.event_id=Attendee.event_id
			$whereSql";
				
		return $sql;
	}
	
	protected function constructSQLWhereSubclause($columnName,$operator,$value){
		switch($columnName){
			/*case 'Registration.status':
			case 'Registration.url_link':
			case 'Registration.is_going':
				return null;*/
			case 'Registration.is_group_registration':
				if($value=='true'){
					return "Attendee.quantity > 1";
				}else{
					return "Attendee.quantity <= 1";
				}
				
			
		}
		return parent::constructSQLWhereSubclause($columnName,$operator,$value);
	}
protected function processSqlResults($rows,$keyOpVals){
		global $wpdb;
		if(!function_exists('is_attendee_approved')){
			require_once(EVENT_ESPRESSO_PLUGINFULLPATH.'includes/functions/attendee_functions.php');
		}
		$attendeeStatuses=array();
		$processedRows=array();
		foreach($rows as $row){
			if(!array_key_exists($row['Attendee.id'],$attendeeStatuses)){
				$isApproved=is_attendee_approved(intval($row['Event.id']),intval($row['Attendee.id']));
				$status=$isApproved?'approved':'not_approved';
				$attendeeStatuses[$row['Attendee.id']]=$status;
			}
			$attendeeStatus=$attendeeStatuses[$row['Attendee.id']];
			$row['Registration.status']=$attendeeStatus;
			$row['Registration.is_going']=true;
			$row['Registration.url_link']=null;
			$row['Registration.is_group_registration']=$this->determineIfGroupRegistration($row);
			$row['Registration.is_primary']=$row['Attendee.is_primary']?true:false;
			
			//in 3.2, every single row in registrationtable relates to a ticket for somebody
			//to get into the event. In 3.1 each row in the event_attendee table corresponds to an attendee
			//who may have multiple tickets/registrations for an event.
			//so, in order to have multiple registrations per row, each having their own ID,
			//we add a decimal point onto the row's ID, and make it unique for each row.
			//Eg, if we 3.1 attendee row has 13 tickets on it, then we should get 13 registrations 
			//from that single row. They should have the following IDs: 13.0, 13.1, 13.2, 13.3, 13.4, 13.5,
			//13.6, 13.7, 13.8, 13.9, 13.11, 13.12, 13.13. Notice, however, that there wasn't a 13.10, because that's
			//actually identical to 13.1. So the decimal value simply implies that it's unique, it cannot be 
			//reliably used for counting registrations on an attendee
			$baseRegId=$row['Registration.id'];
			$checkedInQuantity=$row['Attendee.checked_in_quantity'];
			$i = 0;
			$ids = array();
			for($count = 0; $count < $row['Attendee.quantity']; $count++){
				//make sure we don't already have this ID
				while(in_array((float)"$baseRegId.$i",$ids)){
					$i++;
				}
				$currentId="$baseRegId.$i";
				$ids[]=(float)$currentId;
				$row['Registration.id']=$currentId;
				 if($i>1){  
					$row['Registration.is_primary']=false;  
				}  
				if(!$this->rowPassesFilterByCalculatedColumns($row,$keyOpVals))
					continue;		
				$row['Registration.is_checked_in']=($count<$checkedInQuantity || ($i==0 && $row['Attendee.checked_in']))?true:false;
			
				$processedRows[]=$row;
			}	
		}
		return $processedRows;
	}
	
	private function determineIfGroupRegistration($sqlResult){
		//if it hasa quantity over 1
		//or there are other registrations with teh same Attendee.registration_id
		if(!array_key_exists($sqlResult['Attendee.registration_id'],$this->knownGroupRegistrationRegIds)){
			if($sqlResult['Attendee.quantity']>1){
				$this->knownGroupRegistrationRegIds[$sqlResult['Attendee.registration_id']]=true;
			}else{
				//check for other attendee rows with teh same registration id
				global $wpdb;
				$count=$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}events_attendee Attendee
					WHERE Attendee.registration_id='{$sqlResult['Attendee.registration_id']}'");
				if($count>1){
					$this->knownGroupRegistrationRegIds[$sqlResult['Attendee.registration_id']]=true;
				}else{
					$this->knownGroupRegistrationRegIds[$sqlResult['Attendee.registration_id']]=false;
				}

			}
		}
		return $this->knownGroupRegistrationRegIds[$sqlResult['Attendee.registration_id']];
			

//return in_array($sqlResult['Attendee.registration_id'],$this->knowGroupRegistrationRegIds);
	}
	/**
	 *for taking the info in the $sql row and formatting it according
	 * to the model
	 * @param $sqlRow a row from wpdb->get_results
	 * @return array formatted for API, but only toplevel stuff usually (usually no nesting)
	 */
	protected function _extractMyUniqueModelsFromSqlResults($sqlResult){
		
		$transaction=array(
			'id'=>$sqlResult['Registration.id'],
			'status'=>$sqlResult['Registration.status'],
			'date_of_registration'=>$sqlResult['Attendee.date'],
			'final_price'=>$sqlResult['Attendee.final_price'],
			'code'=>$sqlResult['Attendee.registration_id'],
			'url_link'=>$sqlResult['Registration.url_link'],
			'is_primary'=>$sqlResult['Registration.is_primary'],
			'is_group_registration'=>$sqlResult['Registration.is_group_registration'],
			'is_going'=>$sqlResult['Registration.is_going'],
			'is_checked_in'=>$sqlResult['Registration.is_checked_in']
			);
		return $transaction;
	}
	
	
	
	
	
	function _checkin($id,$queryParameters=array()){
		global $wpdb,$ticketing_installed;
		//note: they might be checking in a registrant with an id like 1.1 or 343.4, (this happens in group registrations
		//where all tickets use the same attendee info
		//if that's the case, we row we want to update is 1 or 343, respectively.
		//soo just strip everything out after the "."
		$idParts=explode(".",$id);
		if(count($idParts)>2){
			throw new EspressoAPI_SpecialException(sprintf(__("You did not provide a properly formatted ID of a registration. Remember registration IDs are actually floats (eg: 1.2, or 10.34) not strings. You provided: %s","event_espresso"),$id));
		}elseif(count($idParts)==2){
			$rowId=$idParts[0];
		}else{
			$rowId=$id;
		}
		//get the registration
		$fetchSQL="SELECT * FROM {$wpdb->prefix}events_attendee WHERE id=$rowId";
		$registration=$wpdb->get_row($fetchSQL,ARRAY_A);
		if(empty($registration))
			throw new EspressoAPI_ObjectDoesNotExist($id);
		if(!EspressoAPI_Permissions_Wrapper::espresso_is_my_event($registration['event_id']))
			throw new EspressoAPI_UnauthorizedException();
		$ignorePayment=(isset($queryParameters['ignore_payment']) && $queryParameters['ignore_payment']=='true')?true:false;
		$quantity=(isset($queryParameters['quantity']) && is_numeric($queryParameters['quantity']))?$queryParameters['quantity']:1;
		if(intval($registration['checked_in_quantity'])+$quantity>$registration['quantity']){
			throw new EspressoAPI_SpecialException(sprintf(__("Checkins Exceeded! Only %s checkins are permitted on for this attendee on this event, but you have requested to checkin %s when there were already %s","event_espresso"),$registration['quantity'],$quantity,$registration['checked_in_quantity']));
		}
		
		//check payment status
		if($registration['payment_status']=='Incomplete' && !$ignorePayment){
		//if its 'Incomplete' then stop
			throw new EspressoAPI_SpecialException(__("Checkin denied. Payment not complete and 'ignore_payment' flag not set.",412));
		}
		$sql="UPDATE {$wpdb->prefix}events_attendee SET checked_in_quantity = checked_in_quantity + $quantity, checked_in=1 WHERE id='{$registration['id']}'";
		//update teh attendee to checked-in-quanitty and checked_in columns
		$result=$wpdb->query($sql);
		if($result){
			//refetch the registration again
			//return $this->getOne($id);
			
			if ($ticketing_installed == true){
				//Add the date checked-in into the events_attendee_checkin table
				$columns_and_values = array(
					'attendee_id'			=> $registration['id'],
					'registration_id'		=> $registration['registration_id'],
					'event_id'				=> $registration['event_id'],
					'checked_in'			=> $quantity,//Checked-in
					'date_scanned'			=> date('Y-m-d H:i:s'),
					'method'				=> 'api',
					'type'					=> 'checkin',
				);
				$data_formats = array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', );
				$scan_date = $wpdb->insert( "{$wpdb->prefix}events_attendee_checkin", $columns_and_values, $data_formats );
				if(!$scan_date){
					throw new EspressoAPI_OperationFailed(__("Adding of date checked-in failed:","event_espresso").$scan_date);
				}
			}
			
			$allRegistrations= $this->getMany(array('Attendee.id'=>$rowId,'Event.id'=>$registration['event_id']));
			$updatedRegistrations=array_slice($allRegistrations['Registrations'], $registration['checked_in_quantity'], $quantity, true);
			return array('Registrations'=>$updatedRegistrations);
		}else{
			throw new EspressoAPI_OperationFailed(__("Updating of registration as checked-in failed:","event_espresso").$result);
		}
	}
	
	
	
	
	
	
	function _checkout($id,$queryParameters=array()){
		global $wpdb, $ticketing_installed;
		//note: they might be checking in a registrant with an id like 1.1 or 343.4, (this happens in group registrations
		//where all tickets use the same attendee info
		//if that's the case, we row we want to update is 1 or 343, respectively.
		//soo just strip everything out after the "."
		$idParts=explode(".",$id);
		if(count($idParts)>2){
			throw new EspressoAPI_SpecialException(sprintf(__("You did not provide a properly formatted ID of a registration. Remember registration IDs are actually floats (eg: 1.2, or 10.34) not strings. You provided: %s","event_espresso"),$id));
		}elseif(count($idParts)==2){
			$rowId=$idParts[0];
		}else{
			$rowId=$id;
		}
		
		
		//get the registration
		$fetchSQL="SELECT * FROM {$wpdb->prefix}events_attendee WHERE id=$rowId";
		$registration=$wpdb->get_row($fetchSQL,ARRAY_A);
		if(empty($registration))
			throw new EspressoAPI_ObjectDoesNotExist($id);
		if(!EspressoAPI_Permissions_Wrapper::espresso_is_my_event($registration['event_id']))
			throw new EspressoAPI_UnauthorizedException();
		//handle a special case.
		//there was a bug where sometimes checked_in was set to 1 (true), but
		//checked_in_quantity was left at 0. In this case, pretend 
		//checked_in_quantity were maxed-out (at 'quantity')
		if(intval($registration['checked_in'])==1 && intval($registration['checked_in_quantity'])==0){
			$registration['checked_in_quantity']=$registration['quantity'];
		}
		
		if(isset($queryParameters['quantity']) && is_numeric($queryParameters['quantity'])){
			$quantityToChange=$queryParameters['quantity'];
			$newCheckedInQuantity=intval($registration['checked_in_quantity'])-$quantityToChange;
		}else{
			$quantityToChange=1;
			$newCheckedInQuantity=intval($registration['checked_in_quantity'])-$quantityToChange;
		}
		//check not too many checkouts
		if($newCheckedInQuantity<0){
			throw new EspressoAPI_SpecialException(sprintf(__("Checkouts Exceeded! You tried to checkout %s when there were only %s checked in on registration %s","event_espresso"),$quantityToChange,$registration['checked_in_quantity'],$id));
		}
		//decide on what we're going to set the new 'checked_in' value to, 
		//based on whether new checked_in_quantity will be 0 or not
		$newCheckedInvalue=$newCheckedInQuantity==0?0:1;
		
		$sql="UPDATE {$wpdb->prefix}events_attendee SET checked_in_quantity = $newCheckedInQuantity, checked_in=$newCheckedInvalue WHERE id='{$registration['id']}'";
		//update teh attendee to checked-in-quanitty and checked_in columns
		$result=$wpdb->query($sql);
		if($result){
			
			if ($ticketing_installed == true){
				//Add the date checked-out into the events_attendee_checkin table
				$columns_and_values = array(
					'attendee_id'			=> $registration['id'],
					'registration_id'		=> $registration['registration_id'],
					'event_id'				=> $registration['event_id'],
					'checked_in'			=> $newCheckedInvalue,//Checked-out
					'date_scanned'			=> date('Y-m-d H:i:s'),
					'method'				=> 'api',
					'type'					=> 'checkout',
				);
				$data_formats = array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', );
				$scan_date = $wpdb->insert( "{$wpdb->prefix}events_attendee_checkin", $columns_and_values, $data_formats );
				if(!$scan_date){
					throw new EspressoAPI_OperationFailed(__("Adding of date checked-out failed:","event_espresso").$scan_date);
				}
			}
			
			
			//fetch the updated registrations. if we updated 4, we ought to return
			//4. If our first non-checked-in ID was 555.6, that means we should return 6 through 2 (so from .etc.
			//
			$allRegistrations= $this->getMany(array('Attendee.id'=>$rowId,'Event.id'=>$registration['event_id']));
			$updatedRegistrations=array_slice($allRegistrations['Registrations'], $newCheckedInQuantity, $quantityToChange, true);
			return array('Registrations'=>$updatedRegistrations);
		}else{
			throw new EspressoAPI_OperationFailed(__("Updating of registration as checked-out failed:","event_espresso").$result);
		}
		
	}
	
	/**
	 * handles converting an API Registration id to a database attendee id.
	 * handles teh case where $registrationId is actually a temp id, in which case
	 * it doesn't change it. However, otherwise the conversion is done by simply 
	 * removing the decimal
	 * @param float or string $registrationId, like 'temp-my-reg' or 12.3, etc.
	 * @return float or string
	 */
	protected function convertAPIRegistrationIdToDbAttendeeId($registrationId){
		if(EspressoAPI_Temp_Id_Holder::isTempId($registrationId)){
			return $registrationId;
		}else{
			return intval($registrationId);
		}
	}
	
	/**
	 * overrides parent. instead of creating query parameters  to search for API registration
	 * IDs, it searches for Transaction ids. This is done because 
	 * @param type $idsAffected
	 * @return type
	 */
	protected function getAffected($idsCreated,$idsUpdated){
		$affectedResources=array();
		if(!empty($idsCreated)){
			$transactionIdsAffected=array();
			foreach($idsCreated as $idCreated){
				$transactionIdsAffected[]=intval($idCreated);
			}
			$createdResources=$this->getMany(array('Transaction.id__in'=>implode(",",$transactionIdsAffected)));
			$affectedResources=array_merge($affectedResources,$createdResources);
		}
		if(!empty($idsUpdated)){
			$updatedResources=$this->getMany(array('id__in'=>implode(",",$idsUpdated)));
			$affectedResources=array_merge($affectedResources,$updatedResources);
		}
		return $affectedResources;
	}
	/**
	 * overrides parent's createorUpdateOne. Should create something in our db according to this
	 * @param type $model, array exactly like response of getOne, eg array('Registration'=>array('id'=>1.1,'final_price'=>123.20, 'Attendees'=>array(...
	 * 
	 */
    function performCreateOrUpdate($apiInput){
			
		//construct list of key-value pairs, for insertion or update
		$attendeeRowId=$this->convertAPIRegistrationIdToDbAttendeeId($apiInput[$this->modelName]['id']);
		if(EspressoAPI_Temp_Id_Holder::isTempId($apiInput[$this->modelName]['id'])){
			$create=true;
		}else{
			$create=false;
		}
		//first: extract related event info
		$relatedModels=$this->getFullRelatedModels();
		if(array_key_exists('Event',$apiInput[$this->modelName])){
			$dbEntries=$relatedModels['Event']['class']->extractMyColumnsFromApiInput($apiInput[$this->modelName],array(),array('correspondingAttendeeId'=>$attendeeRowId));
		}else{
			$dbEntries=array();
		}
		$eventRow=$this->getDbEventForAttendeeId($attendeeRowId,$dbEntries);//we need the event data correponding to this registration
		$dbEntries=$this->extractMyColumnsFromApiInput($apiInput,$dbEntries);
		
		
		foreach($relatedModels as $relatedModelInfo){
			if(array_key_exists($relatedModelInfo['modelName'],$apiInput[$this->modelName])){
				if(is_array($apiInput[$this->modelName][$relatedModelInfo['modelName']]) && $relatedModelInfo['modelName']!='Event'){
					$dbEntries=$relatedModelInfo['class']->extractMyColumnsFromApiInput($apiInput[$this->modelName],$dbEntries,array('correspondingAttendeeId'=>$attendeeRowId,'correspondingEvent'=>$eventRow));
				}/*else{
					//they only provided the id of the related model, 
					//eg on array('Registration'=>array('id'=>1,...'Event'=>1...)
					//instead of array('Registration'=>array('id'=>1...'Event'=>array('id'=>1,'name'=>'party1'...)
					//this is logic very specific to the current application
					if($this->modelName=='Event'){
						$dbEntries[EVENTS_ATTENDEE_TABLE][$apiInput['id']]['event_id']=$apiInput[$this->modelName];
					}
					//if it's 'Price', ignore it. There's nothing really to set. (When returning this it's just deduced
					//by the final_price on the registration anyway
					// @todo if it's Attendee, then we should update all the current row's attendee info to match
					//the attendee info found at that ID
					throw new EspressoAPI_MethodNotImplementedException(__("We have yet ot handle such updating of Attendees"));
					// @todo if it's Transaction, then we should update the current row's registration_db
					throw new EspressoAPI_MethodNotImplementedException(__("We have yet to handle such updating of transactions","event_espresso"));
					// @todo if it's Datetime, then we hsould update the times in the current row
					throw new EspressoAPI_MethodNotImplementedException(__("We have yet to handle such updating of datetimes","event_espresso"));
				}*/
			}elseif(array_key_exists($relatedModelInfo['modelNamePlural'],$apiInput[$this->modelName])){
				throw new EspressoAPI_MethodNotImplementedException(sprintf(__("We do not yet handle bulk updating/creating on %s","event_espresso"),$this->modelNamePlural));
			}elseif($create){
				throw new EspressoAPI_MethodNotImplementedException(__("When creating a new registration, you must at least indicate the ID of the related attendee, transaction, event, price and datetime",'event_espresso'));
			}
		}
		return $this->updateAndCreateDbEntries($dbEntries);
	}
	
	/**
	 * handles getting the related Event DB row for a given $attendeeId. 
	 * If the event already exists in the database, fetches it.
	 * If the event doesn't already exist, uses what's provided in the API input.
	 * If the event exists but there are updates from teh API input, merges the two
	 * @param type $attendeeId
	 * @param type $dbEntries
	 */
	protected function getDbEventForAttendeeId($attendeeId,$dbEntries){
		global $wpdb;
		//check if we are dealign with a temp Id
		$eventRow=array();
		$eventId=$dbEntries[EVENTS_ATTENDEE_TABLE][$attendeeId]['event_id'];//must get the event ID from api input
		if(empty($eventId)){//try getting the attendee row from the db
			if(!EspressoAPI_Temp_Id_Holder::isTempId($attendeeId)){
				$attendeeRow=$wpdb->get_row("SELECT * FROM ".EVENTS_ATTENDEE_TABLE." WHERE id=".$attendeeId,ARRAY_A);
				$eventId=$attendeeRow['event_id'];
			}
			if(empty($eventId)){
				throw new EspressoAPI_BadRequestException(__("You must provide a related event id in this request",'event_espresso'));
			}
		}
		//we definitely have an event Id. now we find the whole row  from teh db and mix with api input (if it exists)
		$eventRow=$wpdb->get_row("SELECT * FROM ".EVENTS_DETAIL_TABLE." WHERE id=".$eventId,ARRAY_A);
		if(isset($dbEntries[EVENTS_DETAIL_TABLE][$eventId])){
			$eventRow=EspressoAPI_Functions::array_merge_recursive_overwrite($eventRow,$dbEntries[EVENTS_DETAIL_TABLE][$eventId]);
		}
		return $eventRow;
	}


	/**
	 * gets all the database column values from api input
	 * @param array $apiInput either like array('events'=>array(array('id'=>... 
	 * //OR like array('event'=>array('id'=>...
	 * @return array like array('wp_events_attendee'=>array(12=>array('id'=>12,name=>'bob'... 
	 */
	function extractMyColumnsFromApiInput($apiInput,$dbEntries,$options=array()){
		$options=shortcode_atts(array('correspondingEvent'=>null),$options);
		$models=$this->extractModelsFromApiInput($apiInput);
		foreach($models as $thisModel){
			if(!array_key_exists('id', $thisModel)){
				throw new EspressoAPI_SpecialException(__("No ID provided on registration","event_espresso"));
			}
			
			if(EspressoAPI_Temp_Id_Holder::isTempId($thisModel['id'])){
				$forCreate=true;
				$thisModelId=$thisModel['id'];
			}else{
				$forCreate=false;
				$thisModelId=intval($thisModel['id']);
			}
			
			$relatedEvent=$options['correspondingEvent'];
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
						$dbCol='id';
						if($useDefault){
							throw new EspressoAPI_SpecialException(__("No ID provided on registration","event_espresso"));
						}else{
							$dbValue=$apiValue;
							$thisModelId=$dbValue;
						}
						break;
					case 'status':
						$dbCol='pre_approve';
						if($useDefault){
							if($relatedEvent['requires_pre_approval']){
								$dbValue=0;
							}else{
								$dbValue=1;
							}
						}else{
							if($apiValue=='approved'){
								$dbValue=1;
							}else{
								$dbValue=0;
							}
						}
						break;
					case 'date_of_registration':
						$dbCol='date';
						if($useDefault){
							$dbValue=date("Y-m-d H:i:s");
						}else{
							$dbValue=$apiValue;
						}
						
						break;
					case 'final_price':
						$dbCol='final_price';
						if($useDefault){
							$dbValue=0;
						}else{
							$dbValue=$apiValue;
						}
						
						break;
					case 'code':
						$dbCol='registration_id';
						if($useDefault){
							$dbValue=espresso_build_registration_id($relatedEvent['id']);
						}else{
							
							$dbValue=$apiValue;
						}
						break;
					case 'is_primary':
						$dbCol='is_primary';
						if($useDefault){//@todo deciding if a registration is primary probably requires more logic than just assuming they are...
							$dbValue=1;
						}else{
							if($apiValue=='true'){
								$dbValue=1;
							}else{
								$dbValue=0;
							}
						}
						break;
					case'is_checked_in':
						$dbCol='checked_in';
						if($useDefault){
							$dbValue=0;
						}else{
							if($apiValue=='true'){
								$dbValue=1;
							}else{
								$dbValue=0;
							}
						}
				}
				$dbEntries[EVENTS_ATTENDEE_TABLE][$thisModelId][$dbCol]=$dbValue;
			}
			
		}
		return $dbEntries;
	}
	
	/**
	 * Determines if the current user has specific permission to accesss/manipulate
	 * the resource indicated by $id. For Registrations, basically a user has SPECIFIC permissions to
	 * view & edit an Registration IF they have permission to view & edit the EVENT the registration is for.
	 * If we're calling this just after creating an array representing a resource instance
	 * (array which only needs to be json-encoded before displaying to the user)
	 * then $resource_instance_array can be provided in hopes of avoiding extra querying
	 * @param string $httpMethod like 'get' or 'put'
	 * @param int|float $id
	 * @param array $api_model_object array that could be returned to the user, like for an event that would be array('id'=>1,'code'=>'3ffw3', 'name'=>'party'...)
	 * @return boolean
	 */
	function current_user_has_specific_permission_for($httpMethod,$id,$resource_instance_array = array()){
		if(is_array($resource_instance_array) && isset($resource_instance_array['Event']['id'])){
			$event_id = $resource_instance_array['Event']['id'];
		}else{
			//get the registration's event's ID
			//ok so get the 3.1 attendee id from the api registration $id
			$db_attendee_id = $this->convertAPIRegistrationIdToDbAttendeeId($id);
			global $wpdb;
			$event_id = $wpdb->get_var($wpdb->prepare("SELECT event_id FROM ".EVENTS_ATTENDEE_TABLE." WHERE id=%d",$db_attendee_id));
		}
		//
		return EspressoAPI_Permissions_Wrapper::espresso_is_my_event($event_id);
	}
}
//new Events_Controller();
