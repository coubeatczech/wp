<?php

/**
 * Description of EspressoAPI_Generic_Resource_Facade_Create_Functions
 *
 * @author mnelson4
 */
require_once(dirname(__FILE__).'/EspressoAPI_Generic_Resource_Facade_Read_Functions.class.php');
abstract class EspressoAPI_Generic_Resource_Facade_Write_Functions extends EspressoAPI_Generic_Resource_Facade_Read_Functions{
	function createOrUpdateMany($apiInput){
		$apiInput=$this->validator->validate($apiInput,array('single'=>false,'requireRelated'=>false,'allowTempIds'=>true,'requireAllFields'=>false));
		$idsCreated=array();
		$idsUpdated=array();
		foreach($apiInput[$this->modelNamePlural] as $inputPerModelInstance){
			$this->performCreateOrUpdate(array($this->modelName=>$inputPerModelInstance));
			if(EspressoAPI_Temp_Id_Holder::isTempId($inputPerModelInstance['id'])){
				if(EspressoAPI_Temp_Id_Holder::previouslySet($inputPerModelInstance['id'])){
						$idsCreated[]=EspressoAPI_Temp_Id_Holder::get($inputPerModelInstance['id']);
				}else{
					throw new EspressoAPI_SpecialException(sprintf(__('Internal Error. "%s" is a temporary id, but hasn\'t yet been set. Contact Event Espresso support with the HTTP request info and DB dump.','event_espresso'),$inputPerModelInstance['id']));
				}
			}else{
				$idsUpdated[]=$inputPerModelInstance['id'];
			}
		}
		return $this->getAffected($idsCreated,$idsUpdated);//$this->getMany($this->generateAfterCreateOrUpdateManyQueryParams($idsCreated,$idsUpdated));//array('Transaction.id__in'=>implode(",",$idsAffected)));	
	}
	/**
	 * after we createOrUpdateMany, we want to return the resource just updated or created.
	 * In order to do that,we use getMany and pass it along some query parameters.
	 * This is where that query is created. The query should be key-value pairs just like
	 * what the api clients supply in the $_GET query parameters.
	 * @param array $idsCreated is a list of all the  IDs of the toplevel resources affected. So
	 * if createOrUpdateMany was called on registrations and we created 2 registrations 
	 * (and maybe created some related attendees and events, doesn't matter actually) and updated 1,
	 * then $idsCreated would be the DB row ids of the 2 newly-created registrations, and $idsUpdated would be the
	 * id of the one registration which was updated
	 * @param array $idsUpdated explained above
	 * @return array just like getMany
	 */
	protected function getAffected($idsCreaetd,$idsUpdated){
		$idsAffected=array_merge($idsCreaetd,$idsUpdated);
		return $this->getMany(array('id_in'=>implode(",",$idsAffected)));
	}
	
	/**
	 * called by controllers to update one resource, specified by $id
	 * @param type $id
	 * @param type $apiInput
	 * @return type
	 * @throw EspressoAPI_BadRequestException if $id does not match $apiInput[modelName]['id']
	 */
	function updateOne($id,$apiInput){
		$apiInput=$this->validator->validate($apiInput,array('single'=>true,'requireRelated'=>false,'allowTempIds'=>true,'requireAllFields'=>false));
		if($id!=$apiInput[$this->modelName]['id']){
			throw new EspressoAPI_BadRequestException(sprintf(__("The ID specified in the URL (%s) does not match the ID specified in the request body (%s)",'event_espresso'),$id,$apiInput[$this->modelName]['id']));
		}
		$this->performCreateOrUpdate($apiInput);
		return $this->getOne($id);
	}
	
	/**
	 * given API input like array('Events'=>array(0=>array('id'=>1,'name'=>'party1'...) 
	 * or array('Event'=>array('id'=>1,'name'=>'party1'...)
	 * produces array(0=>array('id'=>1,'name'='party1'...)
	 * @param array $apiInput (se above)
	 * @return see above 
	 */
	protected function extractModelsFromApiInput($apiInput){
		if(array_key_exists($this->modelName,$apiInput)){
			$models=array($apiInput[$this->modelName]);
		}elseif(array_key_exists($this->modelNamePlural,$apiInput)){
			$models=$apiInput[$this->modelNamePlural];
		}
		return $models;
	}
	
	
	/**
	 * given data in the form array(TABLE_NAME=>array(1=>array('columnName'=>'columnValue'...
	 * this will update or create the appropriate tables
	 * note: it's important to do the creations first, because they may have temporary IDs. This way the temporary
	 * ids get set in the EspressoAPI_Temp_Id_Holder and can be used in subsequent updates (and maybe creates?)
	 * @param type $dbUpdateData 
	 * @return true if all updates are successful, false is there was an error
	 */
	protected function updateAndCreateDbEntries($dbEntries){
		//first do creates
		$success=$this->loopThroughDbEntriesAndPerform('create', $dbEntries);
		if($success){
			//next do updates
			$success=$this->loopThroughDbEntriesAndPerform('update', $dbEntries);
		}
		return $success;
	}
	/**
	 * loops through all the $dbEntries, some of which may be for db insertions, others
	 * may be for updates. Will only handle the dbEntries marked by $action
	 * @param string $action should be only 'update' or 'create'
	 * @param dbEntries array(TABLE_NAME=>array(1=>array('columnName'=>'columnValue'...
	 * @return success of all the db updates and creates
	 */
	protected function loopThroughDbEntriesAndPerform($action,$dbEntries){
		if(!in_array($action,array('create','update'))){
			throw new EspressoAPI_SpecialException(sprintf(__("Internal error. Trying to loop thruogh DB entries to update or create, but supplied action was %s. It should be 'update' or 'create'.","event_espresso"),$action));
		}
		foreach($dbEntries as $tableName=>$rowsToUpdate){
			foreach($rowsToUpdate as $rowId=>$columns){
				if(EspressoAPI_Temp_Id_Holder::isTempId($rowId)){
					if($action=='create'){
						$success=$this->updateOrCreateRow($tableName,$columns);
					}else{
						$success=true;
						//ignore. we're wanting to only update, but this has a temp Id
					}
				}else{
					//it's not a temp id
					if($action=='update'){
						$success=$this->updateOrCreateRow($tableName,$columns);
					}else{
						$success=true;
						//ignore. it's not a temp id, and we're creating.
					}
				}
				if($success===false){
					return false;
				}
			}
		}
		return true;
	}
	
	/**
	 * updates the row indicated of $tableName, where $keyValPairs is an array of
	 * column-value-mappings for the update, or if none is found, creates it.
	 * By default, uses the 'id'=$keyValuPairs['id'] as the WHERE clause for the update,
	 * but the name of the ID field can be changed by setting $options['id'].
	 * Also, if you want to change the WHERE clause further, use $options['whereClauses']
	 * (note that this will override the default WHERE clause previous mentioned.)
	 * if overriding $options['whereClauses'], you'll also need to set $options['whereFormats'] 
	 * (which indicates the variable type of each value in the where clauses as %d (digit),
	 * %f (float) or %s (string). $options['whereFormats'] is, by default, array('%d'))
	 * @global type $wpdb
	 * @param string $tableName
	 * @param array $keyValPairs like array('id'=>12,'fname'=>'bob'... 
	 * @param array $options array of options:
	 * - 'whereClauses' is an array of key-value pairs
	 * to be used for 'where' conditions (replaces default of array('id'=>$keyValPairs['id'])
	 * - 'id' is the column name to be used instead of 'id',  default is 'id'
	 * - 'whereFormats' is an array of strings, each being one of '%s','%d',%f' like documented in http://codex.wordpress.org/Class_Reference/wpdb#UPDATE_rows
	 * 
	 * @return id of row updated
	 */
	protected function updateOrCreateRow($tableName, array $keyValPairs,array $options=array()){
		//skip updating if we only have 1 keyValPair,because it should be 'id'=>$modelId
		//and any creates should always define more than 1 field. (maybe there will be
		//an exception some day, but that would require a table to only have 2 columns: an id and a value. 
		//(even meta-tables have at least 3: id, meta-key, meta-value))
		if(count($keyValPairs)<2){
			return true;
		}
		global $wpdb;
		if(array_key_exists('whereClauses',$options)){
			$wheres=$options['whereClauses'];
			$idCol=null;
		}else{
			$wheres=array();
			if(array_key_exists('id',$options)){
				$idCol=$options['id'];
			}else{
				$idCol='id';
			}
		}
		
		
		$format=array();
		$create=true;//start off assuming we're inserting a new row. 
		$keyValPairsSansTemps=array();//keyValPairs but we've replaced all the temp ids with their real values
		foreach($keyValPairs as $columnName=>$columnValue){
			if($columnName!=$idCol){
				if(EspressoAPI_Temp_Id_Holder::isTempId($columnValue)){
					if(EspressoAPI_Temp_Id_Holder::previouslySet($columnValue)){
						$keyValPairsSansTemps[$columnName]=  EspressoAPI_Temp_Id_Holder::get($columnValue);
						$format[]='%d';
					}else{
						throw new EspressoAPI_SpecialException(sprintf(__('Internal Error. "%s" is a temporary id, but hasn\'t yet been set. Contact Event Espresso support with the HTTP request info and DB dump.','event_espresso'),$columnValue));
					}
				}else{
					$keyValPairsSansTemps[$columnName]=$columnValue;
					if(is_float($columnValue)){
						$format[]='%f';
					}else if(is_int($columnValue)){
						$format[]='%d';
					}else{
						$format[]='%s';
					}
				}
			}elseif(isset($idCol)){				
				if(EspressoAPI_Temp_Id_Holder::isTempId($keyValPairs[$idCol])){//so if the id starts with 'temp-'
					$create=true;
				}else{
					$wheres[$idCol]=$columnValue;
					$create=false;
				}
			}
		}
		if($create){
			$result=$wpdb->insert($tableName,$keyValPairsSansTemps,$format);
			EspressoAPI_Temp_Id_Holder::set($keyValPairs[$idCol], $wpdb->insert_id);
			return true;
		}else{
			$result= $wpdb->update($tableName,$keyValPairsSansTemps,$wheres,$format,
				array_key_exists('whereFormats',$options)?$options['whereFormats']:'%d');
			if($result>1){
				if(WP_DEBUG){
					throw new EspressoAPI_SpecialException(sprintf(__("Error updating entry! We accidentally updated more than 1 when we 
						only wanted to update one! We were updating the %s database table, with these values:%s, and these conditions: %s,
						and somehow updated %d rows!","event_espresso"),$tableName,print_r($keyValPairs,true),print_r($wheres,true),$result));
				}else{
					throw new EspressoAPI_SpecialException(__("Error updating entry! Turn on WP_DEBUG for more info.","event_espresso"));
				}
			}
			if($result!==false){
				return true;//$wheres[$idCol];
			}else{
				return false;
			}
		}
		//$updateSQL="UPDATE $tableName SET ".implode(",",$sqlAssignments).$wpdb->prepare(" WHERE $idCol=%d $extraSQL",$rowId);
		//return $wpdb->query($updateSQL);
	}
	/**
	 * gets all the database column values from apiInput
	 * @param array $apiInput either like array('events'=>array(array('id'=>... 
	 * //OR like array('event'=>array('id'=>...
	 * $dbEntries is an array of what we already have planned to update/add to the database, and which is added upon
	 * in this function. Eg array('wp_events_attendee'=>array(12=>array('id'=>12,name=>'bob'...)),'wp_events_detail'=>...)
	 * @options contains options like:
	 * 'correspondingAttendeeId', which indicates the events_attendee.id relating to the current model
	 * @return array like array('wp_events_attendee'=>array(12=>array('id'=>12,name=>'bob'... 
	 */
	abstract protected function extractMyColumnsFromApiInput($apiInput,$dbEntries,$options=array());
}
