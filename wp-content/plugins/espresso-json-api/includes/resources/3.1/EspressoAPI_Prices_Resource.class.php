<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
/**
 *constants for referring to a 'subprice' on a row. IE, each row in the events_attendees table
 * actually corresponds to 3 prices: nonmember price, a surcharge, and a member price 
 */
define('ESPRESSOAPI_PRICE_NONMEMBER_INDICATOR',.0);
define('ESPRESSOAPI_PRICE_SURCHARGE_INDICATOR',.1);
define('ESPRESSOAPI_PRICE_MEMBER_INDICATOR',.2);

class EspressoAPI_Prices_Resource extends EspressoAPI_Prices_Resource_Facade{
	/**
	 * primary ID column for SELECT query when selecting ONLY the primary id
	 */
	protected $primaryIdColumn='Price.id';
	var $APIqueryParamsToDbColumns=array(
		'id'=>'Price.id',
		'name'=>'Price.price_type',
		'amount'=>'Price.event_cost',
		'limit'=>'Event.reg_limit'
	);
	var $calculatedColumnsToFilterOn=array('Price.remaining','Price.start_date','Price.end_date','Price.description');
	var $selectFields="
		Price.id AS 'Price.id',
		Price.event_id AS 'Price.event_id',
		Price.event_cost AS 'Price.event_cost',
		Price.price_type AS 'Price.price_type',
		Event.reg_limit AS 'Price.limit',
		Price.surcharge AS 'Price.surcharge',
		Price.surcharge_type AS 'Price.surcharge_type',
		Price.member_price AS 'Price.member_price',
		Price.member_price_type AS 'Price.member_price_type'
		";
	var $relatedModels=array();
	/**
	 * the 3.1 implementation of prices resource is tightly connected to the pricetype
	 * resource, so just just import it when constructed
	 * @var type 
	 */
	private $pricetypeModel;
	function __construct(){
		$this->pricetypeModel=EspressoAPI_ClassLoader::load("Pricetypes",'Resource');;
	}
	/**
	 * this APImodel overrides this function in order to return mutliple 'models' 
	 * from a single row, which is obviously not standard
	 * @param array $sqlResults 
	 * @param type $idKey
	 * @param type $idValue
	 * @return type 
	 */
	protected function extractMyUniqueModelsFromSqlResults($sqlResults,$idKey=null,$idValue=null){
		$filteredResults=array();
		foreach($sqlResults as $sqlResult){
			if((!empty($idKey) && !empty($idValue) && $sqlResult[$idKey]!= $idValue))
				continue;
			$prices=$this->_extractMyUniqueModelsFromSqlResults($sqlResult);
				foreach($prices as $key=>$price){
					if(isset($price['id'])){
						$filteredResults["{$price['id']}"]=$price;
					}
				}
		}
		return $filteredResults;
	}	
	
	/**
	 * 
	 * @global type $wpdb
	 * @param array $rows rows returned from a big join-query from the db, where results are all arrays (not objects)
	 * @param array $keyOpVals like array('key'=>__,'operator'=>__,'value'=>__), I believe
	 * @return array like $rows, but manipulated during this function
	 */
	protected function processSqlResults($rows,$keyOpVals){
		global $wpdb;
		$attendeesPerEvent=array();
		$processedRows=array();
		foreach($rows as $row){
			if(empty($attendeesPerEvent[$row['Event.id']])){
				//because in 3.1 there can't be a limit per datetime, only per event, just count total attendees of an event
				$quantitiesAttendingPerRow=$wpdb->get_col( $wpdb->prepare( "SELECT quantity FROM {$wpdb->prefix}events_attendee WHERE event_id=%d;", $row['Event.id']) );
				$totalAttending=0;
				foreach($quantitiesAttendingPerRow as $quantity){
					$totalAttending+=intval($quantity);
				}
				$attendeesPerEvent[$row['Event.id']]=$totalAttending;//basically cache the result
			}
			$row['Price.limit']=isset($row['StartEnd.reg_limit']) ? intval($row['StartEnd.reg_limit']) : intval($row['Event.reg_limit']); 
			$row['Price.remaining']=intval($row['Price.limit'])-$attendeesPerEvent[$row['Event.id']];//$row['Event.reg_limit'];// just reutnr  abig number for now. Not sure how to calculate this. $row['StartEnd.reg_limit']-$attendeesPerEvent[$row['Event.id']];
			$row['Price.description']=null;
			$row['Price.start_date']=null;
			$row['Price.end_date']=null;
//now that 'tickets_left' has been set, we can filter by it, if the query parameter has been set, of course
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
		$pricesToReturn=array();
		$pricesToReturn['base']=array(
			'id'=>floatval(intval($sqlResult['Price.id'])+ESPRESSOAPI_PRICE_NONMEMBER_INDICATOR),
			'amount'=>$sqlResult['Price.event_cost'],
			'name'=>isset($sqlResult['Attendee.price_option']) ? $sqlResult['Attendee.price_option'] : $sqlResult['Price.price_type'],
			'description'=>$sqlResult['Price.description'],
			'limit'=>$sqlResult['Price.limit'],
			'remaining'=>$sqlResult['Price.remaining'],
			'start_date'=>$sqlResult['Price.start_date'],
			'end_date'=>$sqlResult['Price.end_date'],
			'Pricetype'=>$this->pricetypeModel->fakeDbTable[ESPRESSOAPI_PRICETYPE_BASE]
		);
		if($sqlResult['Price.surcharge']!=0){
			if($sqlResult['Price.surcharge_type']=='pct')
				$priceType=$this->pricetypeModel->fakeDbTable[ESPRESSOAPI_PRICETYPE_PERCENT_SURCHARGE];
			else
				$priceType=$this->pricetypeModel->fakeDbTable[ESPRESSOAPI_PRICETYPE_AMOUNT_SURCHARGE];
			$price_name = isset($sqlResult['Attendee.price_option']) ? $sqlResult['Attendee.price_option'] : $sqlResult['Price.price_type'];
			$pricesToReturn['surcharge']=array(
				'id'=>floatval(intval($sqlResult['Price.id'])+ESPRESSOAPI_PRICE_SURCHARGE_INDICATOR),
				'amount'=>$sqlResult['Price.surcharge'],
				'name'=> sprintf(__("Surcharge for %s",'event_espresso'),$price_name),
				'description'=>null,
				'limit'=>$sqlResult['Price.limit'],
				'remaining'=>$sqlResult['Price.remaining'],
				'start_date'=>null,
				'end_date'=>null,
				"Pricetype"=>$priceType
			);
		}
		
		//add member price, if the members addon is active
		$member_plugin_is_active = get_option('events_members_active');
		//echo "members plugin is active:$member_plugin_is_active";
		if($member_plugin_is_active && 'true' == $member_plugin_is_active){
			$pricesToReturn['member']=array(
			'id'=>floatval(intval($sqlResult['Price.id'])+ESPRESSOAPI_PRICE_MEMBER_INDICATOR),
			'amount'=>$sqlResult['Price.member_price'],
			'name'=>isset($sqlResult['Attendee.price_option']) ? $sqlResult['Attendee.price_option'] : $sqlResult['Price.member_price_type'],
			'description'=>null,
			'limit'=>$sqlResult['Price.limit'],
			'remaining'=>$sqlResult['Price.remaining'],
			'start_date'=>null,
			'end_date'=>null,
			"Pricetype"=>$this->pricetypeModel->fakeDbTable[ESPRESSOAPI_PRICETYPE_MEMBER_BASE]
			);
		}
		return $pricesToReturn;
	}
	
	/**
	 * Overrides parent extractMyUniqueModelFromSqlResults because when finding a single price, in the case we
	 * want a single price for a registration, each db table actually contains 2 or 3 (normal rate, optional surcharge, and memebr rate)
	 * so this function should grab the appropriate one (based on the Price's amount compared with what the
	 * registration's orig_price was.) If it doesn't find an exact price match,
	 * invents one with some filled-in info, but with the orig_price set to
	 * be teh amount
	 * @param array $sqlResults
	 * @param string $idKey like 'Attendee.id'
	 * @param string $idValue
	 * @return type 
	 */
	protected function extractMyUniqueModelFromSqlResults($sqlResults,$idKey=null,$idValue=null){
		$foundOrigPrice=false;
		foreach($sqlResults as $sqlResult){
			if($sqlResult[$idKey]==$idValue){
				$origPrice=$sqlResult['Attendee.orig_price'];
				$rowWithOrigPrice=$sqlResult;
				$foundOrigPrice=true;
				break;
			}
		}
		if(!$foundOrigPrice)
			$origPrice=0;
		
		$foundOrigPrice=false;
		$modelRepresentations=$this->extractMyUniqueModelsFromSqlResults($sqlResults, $idKey, $idValue);
		foreach($modelRepresentations as $modelRepresentation){
			if($modelRepresentation['amount']==$origPrice){
				$priceWhichMatchesOrigPrice=$modelRepresentation;
				$foundOrigPrice=true;
				break;
			}
		}
		if($foundOrigPrice && isset($priceWhichMatchesOrigPrice)){
			return $priceWhichMatchesOrigPrice;
		}
		else{
			$priceTypeModel=  EspressoAPI_ClassLoader::load("Pricetypes",'Resource');
			return array(
			'id'=>0,
			'amount'=>$origPrice,
			'name'=>isset($rowWithOrigPrice['Attendee.price_option'])?$rowWithOrigPrice['Attendee.price_option']:'Unknown',
			'description'=>null,
			'limit'=>9999999,
			'remaining'=>999999,//$sqlResult['Event.remaining'],
			'start_date'=>null,
			'end_date'=>null,
			'Pricetype'=>$priceTypeModel->fakeDbTable[1]);
		}
	}
	
	
	
	/**
	 * gets all the database column values from api input. also, if in the $options array, 
	 * the setting for 'correspondingAttendeeId' is set, then we will also try to update
	 * the events_attendee row with the datetime information contained in $apiInput
	 * @param array $apiInput either like array('events'=>array(array('id'=>... 
	 * //OR like array('event'=>array('id'=>...
	 * @return array like array('wp_events_attendee'=>array(12=>array('id'=>12,name=>'bob'... 
	 */
	function extractMyColumnsFromApiInput($apiInput,$dbEntries,$options=array()){
		global $wpdb;
		$options=shortcode_atts(array('correspondingAttendeeId'=>null),$options);
		
		$models=$this->extractModelsFromApiInput($apiInput);
		//$dbEntries=array(EVENTS_PRICES_TABLE=>array());
		if(!empty($options['correspondingAttendeeId'])){
			//$dbEntries[EVENTS_ATTENDEE_TABLE]=array();
			$dbEntries[EVENTS_ATTENDEE_TABLE][$options['correspondingAttendeeId']]['id']=$options['correspondingAttendeeId'];
		}
		foreach($models as $thisModel){
			
			$rowId=floor($thisModel['id']);
			$paymentType=$thisModel['id']-$rowId;
			
			$dbEntries[EVENTS_PRICES_TABLE][$rowId]=array();
			foreach($thisModel as $apiField=>$apiValue){
				switch($apiField){
					case 'id':
						$dbCol='id';
						$dbValue=$rowId;
						$skipInsertionInArray=false;
						$thisModelId=$dbValue;
						break;
					case 'amount':
						if(EspressoAPI_Functions::floats_are_equal($paymentType,ESPRESSOAPI_PRICE_NONMEMBER_INDICATOR)){
							$dbCol='event_cost';
						}elseif(EspressoAPI_Functions::floats_are_equal($paymentType,ESPRESSOAPI_PRICE_SURCHARGE_INDICATOR)){
							$dbCol='surcharge';
						}elseif(EspressoAPI_Functions::floats_are_equal($paymentType,ESPRESSOAPI_PRICE_MEMBER_INDICATOR)){
							$dbCol='member_price';
						}
						if(!empty($options['correspondingAttendeeId'])){
							$dbEntries[EVENTS_ATTENDEE_TABLE][$options['correspondingAttendeeId']]['orig_price']=$apiValue;
						}
						$dbValue=$apiValue;
						$skipInsertionInArray=false;
						break;
					case 'name':
						if(EspressoAPI_Functions::floats_are_equal($paymentType,ESPRESSOAPI_PRICE_NONMEMBER_INDICATOR)){
							$dbCol='price_type';
							$skipInsertionInArray=false;
						}elseif(EspressoAPI_Functions::floats_are_equal($paymentType,ESPRESSOAPI_PRICE_SURCHARGE_INDICATOR)){
							$skipInsertionInArray=true;
						}elseif(EspressoAPI_Functions::floats_are_equal($paymentType,ESPRESSOAPI_PRICE_MEMBER_INDICATOR)){
							$dbCol='member_price_type';
							$skipInsertionInArray=false;
						}
						if(!empty($options['correspondingAttendeeId'])){
							$dbEntries[EVENTS_ATTENDEE_TABLE][$options['correspondingAttendeeId']]['price_option']=$apiValue;
						}
						$dbValue=$apiValue;
						break;
					case 'description':
					case 'limit':
					case 'remaining':
					case 'start_date':
					case 'end_date':
						$skipInsertionInArray=true;
						break;
					case 'Pricetype':
						$dbCol='surcharge_type';
						switch($apiValue['id']){
							case ESPRESSOAPI_PRICETYPE_BASE:
							case ESPRESSOAPI_PRICETYPE_MEMBER_BASE:
								$skipInsertionInArray=true;
								break;
							case ESPRESSOAPI_PRICETYPE_AMOUNT_SURCHARGE:
								$dbValue='flat_rate';
								$skipInsertionInArray=false;
								break;
							case ESPRESSOAPI_PRICETYPE_PERCENT_SURCHARGE:
								$dbValue='pct';
								$skipInsertionInArray=false;
								break;
							default:
								$skipInsertionInArray=true;
						}
				}
				if(!$skipInsertionInArray){
					$dbEntries[EVENTS_PRICES_TABLE][$thisModelId][$dbCol]=$dbValue;
				}
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
		throw new EspressoAPI_MethodNotImplementedException(" current_user_has_specific_permission_for not implemented on ".get_class($this));
	}
}
//new Events_Controller();