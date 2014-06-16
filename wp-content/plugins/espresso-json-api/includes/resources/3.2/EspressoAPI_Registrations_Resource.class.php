<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
class EspressoAPI_Registrations_Resource extends EspressoAPI_Registrations_Resource_Facade{
   
    /**
     * gets all events in the database, according to query parmeters
     * @global type $wpdb
     * @param array $queryParameters of key=>values. eg: "array("start_date"=>"2012-04-23","name"=>"Mike Party").
     * @return type 
     */
    function _getRegistrations($queryParameters){
		//echo "get attendees in api 32";
         // @TODO handle $_GET parameters, specifically allowing for ORs, and LIKE
		if(!empty($queryParameters))
			$whereSql="WHERE ".implode(" AND ",$this->constructSQLWhereSubclauses($queryParameters));
		else
			$whereSql='';
        global $wpdb;
        $sql="
            SELECT
                *
            FROM
                {$wpdb->prefix}events_detail
			$whereSql";
        $results=$wpdb->get_results($sql,ARRAY_A);
        return array("registrations"=>$results);
    }
    function _createRegistration($createParameters){
        return array("status"=>"Not Yet Implemented","status_code"=>"500");
    }
    /**
     *for handling requests liks '/events/14'
     * @param int $id id of event
     */
	function _getRegistration($id){
		global $wpdb;
		$result=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}events_attendee WHERE registration_id='$id'",ARRAY_A);
		if(empty($result)){
			throw new EspressoAPI_ObjectDoesNotExist($id);
		}
		return array("registration"=>$result);
	}
	function _checkin($id,$queryParameters=array()){
		
	}
	function _checkout($id,$queryParameters=array()){
		
	}
	
}
//new Events_Controller();