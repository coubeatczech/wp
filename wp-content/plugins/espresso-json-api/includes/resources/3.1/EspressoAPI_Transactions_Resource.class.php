<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
/**
 * IMPORTANT NOTE TO DEVELOPER!!!
 * When getting a single transaction, don't forget to get all the registrations for that 
 * transaction. It's easier said tahn done. only query to transaction (attendee row entries)
 * which are marked as 'primary', and then for each of them, get all 'registrations'
 * that have teh same 'code' (registration_id in the db) 
 */
class EspressoAPI_Transactions_Resource extends EspressoAPI_Transactions_Resource_Facade{
	/**
	 * primary ID column for SELECT query when selecting ONLY the primary id
	 */
	protected $primaryIdColumn='Attendee.id';
	var $APIqueryParamsToDbColumns=array();
		//'id'=>'Attendee.id',
		//'timestamp'=>'Attendee.date',
		//'total'=>'Attendee.total_cost',
		//'amount_paid'=>'Attendee.amount_pd',
		////'registrations_on_transaction'=>'Attendee.quantity',
		//'payment_gateway'=>'Attendee.txn_type');
		
	var $calculatedColumnsToFilterOn=array(
		'Transaction.id',
		'Transaction.timestamp',
		'Transaction.total',
		'Transaction.amount_paid',
		'Transaction.payment_gateway',
		'Transaction.details',
		'Transaction.tax_data',
		'Transaction.session_data',
		'Transaction.status');
	
	var $selectFields="
		Attendee.id as 'Transaction.id',
		Attendee.id as 'Attendee.id',
		Attendee.date as 'Attendee.date',
		Attendee.total_cost as 'Attendee.total_cost',
		Attendee.amount_pd as 'Attendee.amount_pd',
		Attendee.payment_status as 'Attendee.payment_status',
		Attendee.quantity as 'Attendee.quantity',
		Attendee.txn_type as 'Attendee.txn_type',
		Attendee.checked_in_quantity as 'Attendee.checked_in_quantity'";
	var $relatedModels=array();
	
	/**
	 * used for caching 'primary transactions' (that is, transaction info
	 * on events_attendee rows that are marked as 'primary')
	 * @var type 
	 */
	private $primaryTransactions=array();
	/**
	 * used for converting between api version of Transaction.status and the DB version
	 * keys are DB versions, valuesare teh api versions
	 * @var type 
	 */
	private $statusMapping=array(
				'Completed'=>'complete',
				'Pending'=>'pending',
				'Payment Declined'=>'incomplete',//note: when array_flipping, this value gets forgotten
				'Incomplete'=>'incomplete',
				'Not Completed'=>'incomplete',
				'Cancelled'=>'incomplete',
				'Declined'=>'incomplete');
	protected function processSqlResults($rows,$keyOpVals){
		$processedRows=array();
		foreach($rows as $row){
			//if this is a primary registration, use this data
			//if its not, call the private function getPrimaryTransaction
			if(!$row['Attendee.is_primary']){
				//convert the 
				$primaryTransaction=$this->getPrimaryTransaction($row);
				$row['Transaction.id']=$primaryTransaction['Transaction.id'];
				$row['Transaction.timestamp']=$primaryTransaction['Attendee.date'];
				$row['Transaction.total']=$primaryTransaction['Attendee.total_cost'];
				$row['Transaction.amount_paid']=$primaryTransaction['Attendee.amount_pd'];
				$row['Transaction.payment_gateway']=$primaryTransaction['Attendee.txn_type'];
			}else{
				$row['Transaction.timestamp']=$row['Attendee.date'];
				$row['Transaction.total']=$row['Attendee.total_cost'];
				$row['Transaction.amount_paid']=$row['Attendee.amount_pd'];
				$row['Transaction.payment_gateway']=$row['Attendee.txn_type'];
			}
			$row['Transaction.status']=$this->statusMapping[$row['Attendee.payment_status']];
			$row['Transaction.details']=null;
			$row['Transaction.tax_data']=null;
			$row['Transaction.session_data']=null;
			if(!$this->rowPassesFilterByCalculatedColumns($row,$keyOpVals))
				continue;			
			$processedRows[]=$row;
			
		}
		return $processedRows;
	}
	/**
	 *for taking the info in the $sql row and formatting it according
	 * to the model
	 * @param $sqlRow a row from wpdb->get_results
	 * @return array formatted for API, but only toplevel stuff usually (usually no nesting)
	 */
	protected function _extractMyUniqueModelsFromSqlResults($sqlResult){
			
			
			$transaction=array(
				'id'=>$sqlResult['Transaction.id'],
				'timestamp'=>$sqlResult['Transaction.timestamp'],
				'total'=>$sqlResult['Transaction.total'],
				'amount_paid'=>$sqlResult['Transaction.amount_paid'],
				'status'=>$sqlResult['Transaction.status'],
				'details'=>$sqlResult['Transaction.details'],
				'tax_data'=>$sqlResult['Transaction.tax_data'],
				'session_data'=>$sqlResult['Transaction.session_data'],
				'payment_gateway'=>$sqlResult['Transaction.payment_gateway']
				);
			return $transaction;
	}

	/**
	 * special function for getting the transaction id and other data from the primary 
	 * attendee's row, not the current row being processed. 
	 * Eg: 
	 * @global type $wpdb
	 * @param type $sqlResult
	 * @return type 
	 */
	private function getPrimaryTransaction($sqlResult){
		//based on the Attendee.registration_id, and Attendee.is_primary
		//get the primary transaction
		if(!array_key_exists($sqlResult['Attendee.registration_id'],$this->primaryTransactions)){
			global $wpdb;
			$primaryTransactionRow=$wpdb->get_row("SELECT {$this->selectFields},
			Attendee.registration_id AS 'Attendee.registration_id'
			FROM {$wpdb->prefix}events_attendee Attendee
			WHERE Attendee.is_primary=1 AND Attendee.registration_id='{$sqlResult['Attendee.registration_id']}'",ARRAY_A );
			if(empty($primaryTransactionRow)){
				//the database is somehow corrupted. There should always be a primary attendee for each registration_id
				//so we'll just make do with what we already have
				return $sqlResult;
			}
			$this->primaryTransactions[$sqlResult['Attendee.registration_id']]=$primaryTransactionRow;
		}
		return $this->primaryTransactions[$sqlResult['Attendee.registration_id']];
	}
	
	protected function constructSQLWhereSubclause($columnName,$operator,$value){
		switch($columnName){
			case 'Transaction.status':
				//if 'incomplete'
				if(strpos($value,'incomplete')!==FALSE){
					$exceptionValue=$this->constructValueInWhereClause($operator, 'Payment Declined');
					$exceptionSQL="OR Attendee.payment_status $operator $exceptionValue";
				}else{
					$exceptionSQL='';
				}
				$apiStatusToDbStatus=array_flip($this->statusMapping);
				$value=$this->constructValueInWhereClause($operator,$value,$apiStatusToDbStatus,'Transaction.status');
				return "Attendee.payment_status $operator $value $exceptionSQL";	
		}
		return parent::constructSQLWhereSubclause($columnName, $operator, $value);		
	}
	
	
	/**
	 * gets all the database column values from api input
	 * @param array $apiInput either like array('events'=>array(array('id'=>... 
	 * //OR like array('event'=>array('id'=>...
	 * @return array like array('wp_events_attendee'=>array(12=>array('id'=>12,name=>'bob'... 
	 */
	function extractMyColumnsFromApiInput($apiInput,$dbEntries,$options=array()){
		global $wpdb;
		$options=shortcode_atts(array('correspondingAttendeeId'=>null,'correspondingEvent'=>null),$options);
		$models=$this->extractModelsFromApiInput($apiInput);
		//$dbEntries=array(EVENTS_ATTENDEE_TABLE=>array());
		
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
						//if both this trasnaction's id is a temp ID, and its been suuplied a 'correspondingAttendeeId' 
						//that's a temp ID, set the two of them to be equal
						$dbValue=$thisModelId;
						break;
					case 'timestamp':
						$dbCol='date';
						if($useDefault){
							$dbValue=date("Y-m-d H:i:s");
						}else{
							$dbValue=$apiValue;
						}
						break;
					case 'total':
						$dbCol='total_cost';
						if($useDefault){
							$dbValue=0;
						}else{
							$dbValue=$apiValue;
						}
						break;
					case 'amount_paid':
						$dbCol='amount_pd';
						if($useDefault){
							$dbValue=0;
						}else{
							$dbValue=$apiValue;
						}
						break;
					case 'payment_gateway':
						$dbCol='txn_type';
						if($useDefault){
							$dbValue='';
						}else{
							$dbValue=$apiValue;
						}
						break;
					case 'status':
						$dbCol='payment_status';
						if($useDefault){
							if(!empty($options['correspondingEvent'])){
								$eventMeta=maybe_unserialize($options['correspondingEvent']['event_meta']);
								$defaultPaymentStatusOnEvent=$eventMeta['default_payment_status'];
								if(empty($defaultPaymentStatusOnEvent)){
									$defaultPaymentStatusOnEvent='Incomplete';
								}
								$dbValue=$defaultPaymentStatusOnEvent;
							}else{
								$dbValue='Incomplete';
							}
						}else{
							$statusMappingFlipped=array_flip($this->statusMapping);
							$dbValue=$statusMappingFlipped[$apiValue];
						}
						break;
					case 'details':
					case 'tax_data':
					case 'session_data':
						continue;			
				}
				$dbEntries[EVENTS_ATTENDEE_TABLE][$thisModelId][$dbCol]=$dbValue;
			}
			//@todo determine quantity more intelligently
			$dbEntries[EVENTS_ATTENDEE_TABLE][$thisModelId]['quantity']=1;
			
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
		throw new EspressoAPI_MethodNotImplementedException(" current_user_has_specific_permission_for not implemented on ".get_class($this));
	}
}
//new Events_Controller();