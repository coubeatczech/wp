<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class for validating responses and input arrays. The validator operates on 
 * data in array form (ie, not in xml or json itself. it's a layer abstracted away from either of those).
 * Validation is clearly highly dependent on WHAT is being validated (ie, 
 * are we validating a response which should contain a list of events, a response for
 * a single registration, or the input to update a single attendee?)
 * So at first it made sense for these functions to be part of the Generic
 * Resource Facade, but it got factored out because that class was gettting unruly.
 *
 * @author mnelson4
 */
class EspressoAPI_Validator {
	/**
	 * the resource (model) this validator validates
	 * @var type 
	 */
	var $resource;
	
	function __construct($resource){
		$this->resource=$resource;
	}
	
	/**
	 * validates all the queyr parameters provided in $keyOpVals
	 * @param array $keyOpVals like array(0=>array('key'=>'Transaction.id','operator'=>'=','value'=>'23')
	 * @return array of the same $keyOpVals, but where the values are cast to their correct type, eg from strings to ints, floats, etc.
	 * Also, the model's name is prepended onto each key-op-val. IE, if a user submits a querystring like
	 * "espresso-api/v1/events/public?id__lt=23", keyOpVals will be 
	 * array(
	 *		array(
	 *			'key'=>'Event.id',
	 *			'operator'=>'<',
	 *			'value'=>23
	 *		)
	 * )
	 * @throws EspressoAPI_BadRequestException if any of the requets parameters are of teh wrong type
	 */
	function validateQueryParameters($keyOpVals){
		$api_debug_mode = get_option(EspressoAPI_DEBUG_MODE);
		//get related models
		
		$relatedModels=$this->resource->getFullRelatedModels();
		$normalizedKeyOpVals=array();
		foreach($keyOpVals as $keyOpVal){
			list($modelName,$fieldName)=explode(".",$keyOpVal['key']);
			$fieldInfo=null;
			if($this->resource->modelName==$modelName){
				$fieldInfo=$this->resource->getRequiredFieldInfo($fieldName);
			}else{
				foreach($relatedModels as $relatedModel){
					if($relatedModel['modelName']==$modelName){
						$fieldInfo=$relatedModel['class']->getRequiredFieldInfo($fieldName);
						break;
					}
				}
			}
			if((empty($fieldInfo) || empty($fieldInfo['type']))){
				//only throw an error if the field ALSO isn't a global query parameter, and we're in debug 
				if($api_debug_mode){
					throw new EspressoAPI_BadRequestException(sprintf(__("Query parameter '%s' not a valid parameter of resource '%s'"),$fieldName,$modelName));
				}
				//otherwise, ignore the query parameter. forget about it. pretend it doesn't exist. you didn't see anything...
			}
			elseif(!$this->queryParameterValueIs($keyOpVal['value'],$fieldInfo['type'],$fieldInfo['allowedEnumValues'])){
				if($api_debug_mode){
					if($fieldInfo['type']=='enum'){
						$enumInfoString=sprintf(__("Allowed values are :%s"),implode(",",$fieldInfo['allowedEnumValues']));
					}else{
						$enumInfoString='';
					}
					throw new EspressoAPI_BadRequestException(sprintf(
									__("Param '%s' with value '%s' is not of allowed type '%s'. %s","event_espresso"),$keyOpVal['key'],$keyOpVal['value'],$fieldInfo['type'],$enumInfoString));
				}
				//otherwise, ignore the query parameter entirely. It didn't happen. Forget you ever saw it.
				
			}else{//it passed, so now just normalize the value and prepare it to be returned
				$normalizedKeyOpVals[]=array('key'=>$keyOpVal['key'],
						'operator'=>$keyOpVal['operator'],
						'value'=>$this->castToType($keyOpVal['value'], $fieldInfo['type']));
			}
			
		}
		return $normalizedKeyOpVals;
	}
	function validate($models,$options=array()){
		/*if(array_key_exists('single',$options) && $options['single']==true){
			$single=true;
		}else{
			$single=false;
		}
		if(array_key_exists('requireRelated',$options) && $options['requireRelated']==false){
			$requireRelated=false;
		}else{
			$requireRelated=true;
		}*/
		$options=shortcode_atts(array('single'=>false,'requireRelated'=>true,'allowTempIds'=>false,'requireAllFields'=>true),$options);

		if($options['single']){
			unset($options['single']);
			return 	$models=$this->forceResponseIntoFormat($models,
		     array($this->resource->modelName=>$this->getRequiredFullResponse()),$options);	
		}else{
			unset($options['single']);
			return 	$models=$this->forceResponseIntoFormat($models,
		     array($this->resource->modelNamePlural=>array($this->getRequiredFullResponse())),$options);	
		}
	}
	
	/**
	 * ensures that the response is in the format specified.	 * 
	 * @param response $format eg, array("body"=>array("events"=>array(array("id","name")))). this would require the array key "body" to be set in topmost array. 
	 * It would then allow 0 or more numeric keys. 
	 * Within the value pointed to by each numeric key, it will require it to be an array with keys "id" and "name'.
	 * @param array $response eg, array("body"=>array("events"=>array(array("id"=>1,"name"=>"party132"),array("id"=>2,"name"=>"grad"))))
	 * @return array just passes the response on if there were no errors thrown
	 * @throws Exception if the response is not in the specified format
	 */
	private function forceResponseIntoFormat($response,$format,$options=array()){
		$api_debug_mode = get_option(EspressoAPI_DEBUG_MODE);
		$options=shortcode_atts(array('requireRelated'=>true,'allowTempIds'=>false,'requireAllFields'=>true),$options);
		
		$filteredResponse=array();
		//$format is an array showing the format that $response should be in. Go through each key in $response...
		foreach($format as $key=>$value){
			if(is_numeric($key)){//if $key (of $response) is numeric, it's a list of attribute (eg array(array('var'=>'id','type'=>'int'),array(...)) or some other ordered list
				if(is_array($value)){
					if(array_key_exists('var', $value)){//this should be a variable
						$variableInfo=$value;
						if(!is_array($response)){
							throw new EspressoAPI_InputParsingError(sprintf(__("Parsing error. Expected %s to be an array","event_espresso"),$response));
						}
						//validate the key of the response
						if($options['requireAllFields'] && !array_key_exists($variableInfo['var'],$response)){
							if(WP_DEBUG){
								throw new Exception(sprintf(__("%s in wrong Event Espresso Format! Expected value: %s but it wasnt set in %s. ","event_espresso"),$this->resource->modelName,$variableInfo['var'],print_r($response,true)));
							}
							else{ 
								throw new Exception(__("Response in wrong format. For more information please turn on WP_DEBUG in wp-config","event_espresso"));
							}

						}
						//validate the value of the response
						if(array_key_exists('type',$variableInfo)){
							if($options['requireAllFields'] ||  array_key_exists($variableInfo['var'],$response)){
								if($this->valueIs($response[$variableInfo['var']], $variableInfo['type'],  array_key_exists('allowedEnumValues', $variableInfo)?$variableInfo['allowedEnumValues']:null)){
									$filteredResponse[$variableInfo['var']]=$this->castToType($response[$variableInfo['var']], $variableInfo['type']);
								}else{
									//the value is of the wrong type for this variable. what are we going to do?
									if($options['allowTempIds'] && strpos($response[$variableInfo['var']],"temp-")==0 || !$api_debug_mode){//if the value is like 'temp-%', and we're accepting temporary ids, then let it be. or if we're in LIVE mode (non-debug mode), leave it be too
											$filteredResponse[$variableInfo['var']]=$response[$variableInfo['var']];
									}else{//it's not a temporary id, and it's an invalid value for this type of variable, so error
										throw new EspressoAPI_BadRequestException(sprintf(
												__("Param %s with value %s is not of allowed type %s.","event_espresso"),$variableInfo['var'],$response[$variableInfo['var']],$variableInfo['type']));
									}
								}
							}elseif(!$options['requireAllFields'] && array_key_exists($variableInfo['var'],$response)){
								//if it's not in the response, just leave it out of the filtered Response
							}
						}else{//variable info doesn't have an array key of 'type', so one of the resource facades must have been improperly configured
							throw new EspressoAPI_SpecialException(__("Event Espresso Internal bug. Misconfiguered Resource variables. Please contact Event Espresso.","event_espresso"));
						}
					}else{
						//we're probably iterating through a list of things like events,
						//so if there's an subelement in teh response, force it into teh correct format too, otherwise continue
						foreach($response as $responseSubElement){
							$filteredResponse[]=$this->forceResponseIntoFormat($responseSubElement,$value,$options);
						}	
					}
				}else{//if the value is just a string and the key is numeric, this is an error. it shouldn't happen
					throw new EspressoAPI_SpecialException(sprintf(__e("Event Espresso Internal bug. We have a badly configuered validation array at %s",'event_espresso'),$value));
					 
				}
			}else{//it's a string key, require it in the response
				if(!isset($response[$key])){
					//if it's a related model, and we're not requiring them, ignore it and carry on (pricetype is an exception: it's often an indirectly related model)
					if(($this->resource->isARelatedModel($key) || $key=='Pricetype' )&& !$options['requireRelated']){
						continue;
					}
					if(WP_DEBUG){
						throw new Exception(sprintf(__("%s in wrong Event Espresso Format! Expected value: %s but it wasnt set in %s. ","event_espresso"),$this->resource->modelName,$key,print_r($response,true)));
					}else {
						throw new Exception(__("Response in wrong format. For more information please turn on WP_DEBUG in wp-config","event_espresso"));
					}	
				}else{ 
					//we're looking at a related, nested model. Eg: an Event's Datetime.
					//with key 'Datetime' and value of array('id'=>12,'start_time'=>'2012-23-12 12:23:34', etc.
					//OR, if we're not requiring related models, the value could simply be it's id, eg 12
					if($this->resource->isARelatedModel($key) && !$options['requireRelated'] && !is_array($response[$key])){//it should only 
						continue;
					}else{
						$filteredResponse[$key]=$this->forceResponseIntoFormat ($response[$key],$value,$options);
					}
				}
			}
		}
		return $filteredResponse;
	}
	
	/**
	 * Used to verify that query parameter is of the given type. This is nearly identical
	 * to when we're normally using EspressoAPI_Validtor::valueIs(), except that we want to allow
	 * users to provide the strings 'true' or 'false' for booleans. Eg "?member_only=true" instead
	 * of requiring "member_only=1".
	 * @param mixed $value
	 * @param string $type one of 'int','float','string','bool','datetime','enum','array'
	 * @param array $allowedEnumValues
	 * @return boolean
	 */
	function queryParameterValueIs($value,$type,$allowedEnumValues=array()){
		if($type=='bool'){
			return $this->valueIs($value,'enum',array('true','false'));
		}else{
			return $this->valueIs($value,$type,$allowedEnumValues);
		}
	}
	/**
	 * validates that $value is of the specified type. Eg, if $type is 'datetime' verifies that $value is a string in the mysql datetime format.
	 * If it isn't, returns false. (Note: checks that 'bool's are actually a 1 or 0, not the strings 'true' or 'false'. If you want those strings
	 * to be acceptable values for a 'bool', use EspressoAPI_Validator::queryParameterValueIs which is a wrapper for this function.)
	 * @param mixed $value
	 * @param string $type one of 'int','float','string','bool','datetime','enum','array'
	 * @param array $allowedEnumValues where each value is a possibel value for $value
	 * @return boolean 
	 */
	function valueIs($value,$type,$allowedEnumValues=array()){
		if(is_array($value)){
			$csvValues=array($value);
		}else{
			$csvValues=explode(",",$value);
		}
		foreach($csvValues as $value){
			if($value==null)
				return true;
			switch($type){
				case 'int':
					if(ctype_digit($value) || is_numeric($value)){
						continue;
					}
					else{
						return false;
					}
				case 'float':
					if(is_numeric($value)){
						continue;
					}else{
						return false;
					}
				case 'string':
					if(is_string($value)){
						continue;
					}else{
						return false;
					}
				case 'bool':
					if(is_bool($value) || intval($value)===0 || intval($value)===1){
						continue;
					}else{
						return false;
					}
				case 'datetime':
					if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$value)){
						continue;
					}else{
						return false;
					}
				case 'enum':
					if(in_array($value, $allowedEnumValues)){
						continue;
					}else{
						return false;
					}
				case 'array':
					if(is_array($value)){
						continue;
					}else{
						return false;
					}
				default:
					throw new EspressoAPI_SpecialException(sprintf(__("Internal Event Espresso API Error while validating data. Type %s not permitted","event_espresso"),$type));
			}
		}
		return true;
	}
	
	/**
	 * ensures the value is of the specified type
	 * @param type $value
	 * @param type $type
	 * @return boolean 
	 */
	function castToType($value,$type){
		switch($type){
			case 'int':
				return intval($value);	
			case 'float':
				return floatval($value);				
			case 'string':
				return "$value";
			case 'bool':
				return $value;
			case 'datetime':
				if( ! get_option(EspressoAPI_DEBUG_MODE) && ! $this->valueIs($value,'datetime')){
					//if an invalid datetime has made it this far, and its still not right...
					//just return the start of the unix epoc
					$value = '1970-01-01 00:00:00';
				}
				return $value;
			case 'enum':
				return $value;
			case 'array':
				return (array)$value;
			default:
				throw new EspressoAPI_SpecialException(sprintf(__("Internal Event Espresso API Error while validating data. Type %s not permitted","event_espresso"),$type));
		}
	}
	
	/**
	 * gets the required full response from the requiredFields of the current
	 * model and related ones. For example, returns
	 * array("events"=>array(
	 *		array("id","name","description"...
	 *			"Datetimes"=>array(
	 *				array("id","event_start",...)
	 *			)
	 *		)
	 * )
	 * @return type 
	 */
	private function getRequiredFullResponse(){
		$requiredFullResponse=$this->resource->getRequiredFields();
		foreach($this->resource->relatedModels as $modelName=>$modelInfo){
			//only require the related model's attributes as part of the response 
			//if the current user should eb able to see them anyway
			if(EspressoAPI_Permissions_Wrapper::current_user_can_access_some('get',$modelInfo['modelNamePlural'])){
				$modelClass=  EspressoAPI_ClassLoader::load($modelInfo['modelNamePlural'],'Resource');
				if($modelInfo['hasMany']){
					$requiredFullResponse[$modelInfo['modelNamePlural']][]=$modelClass->getRequiredFields();
				}else{
					$requiredFullResponse[$modelName]=$modelClass->getRequiredFields();
				}
			}
		}
		return $requiredFullResponse;
	}
}

?>
