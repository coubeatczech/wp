<?php
/**
 *this file should actually exist in the Event Espresso Core Plugin 
 */
class EspressoAPI_Events_Resource extends EspressoAPI_Events_Resource_Facade{
   /* var $apiParamToDBColumn=array(
	   "id"=>'id',
		"event_code"=>"event_code",
		"event_name"=>"event_name",
		"slug"=>"slug",
		"event_desc"=>"event_desc",
	   "display_desc"=>,
	   "event_identifier"=>, 
	   "start_date"=>,
	   "end_date"=>,	
	   "registration_start"=>,
	   "registration_end"=>,
		"registration_startT"=>,
	   "registration_endT"=>, 
	   "visible_on"=>,
	   "address"=>,
	   "address2"=>,
	   "city"=>,
	   "state"=>,
	   "zip"=>,
	   "phone"=>,
	   "venue_title"=>,
	   "venue_url"=>,
	   "venue_image"=>,
		"venue_phone"=>,
	   "reg_limit"=>,
	   "allow_multiple"=>,
	   "additional_limit"=>,
	   "is_active"=>,
	   "event_status"=>,
	   "use_coupon_code"=>,
	   "use_groupon_code"=>,
	   "category_id"=>,
	   "coupon_id"=>,
		"tax_percentage"=>,
	   "tax_mode"=>,
	   "member_only"=>,
	   "post_id"=>,
	   "post_type"=>,
	   "country"=>,
	   "externalURL"=>,
	   "early_disc"=>,
	   "early_disc_date"=>,
	   "early_disc_percentage"=>,
	   "question_groups"=>,
		"item_groups"=>,
	   "event_type"=>,
	   "allow_overflow"=>,
	   "overflow_event_id"=>,
	   "recurrence_id"=>,
	   "alt_email"=>,
	   "event_meta"=>,
	   "wp_user"=>,
	   "require_pre_approval"=>,
	   "timezone_string"=>,
		"likes"=>,
	   "submitted"=>,
	   "ticket_id"=>,
	   "certificate_id"=>,
	   "confirmation_email_id"=>,
	   "payment_email_id");
 */
	
	/**
     * gets all events in the database, according to query parmeters
     * @global type $wpdb
     * @param array $queryParameters of key=>values. eg: "array("start_date"=>"2012-04-23","name"=>"Mike Party").
     * @return type 
     */
    function _getMany($queryParameters){
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
        return array("status"=>"OK","status_code"=>"200","events"=>$results);
    }
    function _create($createParameters){
        return array("status"=>"Not Yet Implemented","status_code"=>"500");
    }
    /**
     *for handling requests liks '/events/14'
     * @param int $id id of event
     */
	protected function _getOne($id){
		global $wpdb;
		$result=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}events_detail WHERE id='$id'",ARRAY_A);
		if(empty($result)){
			throw new EspressoAPI_ObjectDoesNotExist($id);
		}
		return array("event"=>$result);
	}
	
	
}
//new Events_Controller();