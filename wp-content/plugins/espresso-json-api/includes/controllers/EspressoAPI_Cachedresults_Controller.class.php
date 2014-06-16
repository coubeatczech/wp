<?php
/**
 * EspressoAPI
 *
 * RESTful API for Even tEspresso
 *
 * @ package			Espresso REST API
 * @ author				Mike Nelson
 * @ copyright		(c) 2008-2011 Event Espresso  All Rights Reserved.
 * @ license			{@link http://eventespresso.com/support/terms-conditions/}   * see Plugin Licensing *
 * @ link					{@link http://www.eventespresso.com}
 * @ since		 		3.2.P
 *
 * ------------------------------------------------------------------------
 *
 * Attendees Controller class
 * hanldes requests like "espresso-api/attendees/*
 * if you want to create another controller, to hanlde, say, "venues", name the class "Venues_Controller" and place it in "Venues_Controller.class.php" in this same folder.
 * 
 * @package			Espresso REST API
 * @subpackage	includes/controllers/EspressoAPI_Attendees_Controller.class.php
 * @author				Mike Nelson
 *
 * ------------------------------------------------------------------------
 */
//require_once("EspressoAPI_Generic_Controller.class.php");
class EspressoAPI_Cachedresults_Controller{
	function handleRequest($param1, $param2) {
		$cachedResults=get_transient($param1);
		if($cachedResults!==FALSE){
			$cachedResults=$this->handleLimit($cachedResults);
			return array(EspressoAPI_STATUS=>"OK",EspressoAPI_STATUS_CODE=>200,EspressoAPI_RESPONSE_BODY=>$cachedResults);
		}else{
			throw new EspressoAPI_ObjectDoesNotExist($param1);
		}
	}
	protected function handleLimit($results){
		$limitedResult=array();
		foreach($results as $key=>$models){
			if(array_key_exists('limit',$_REQUEST)){
				$limit=$_REQUEST['limit'];
				$limitParts=explode(",",$limit);
				if(count($limitParts)==2){
					$models=  array_slice($models, $limitParts[0], $limitParts[1]);
				}else{
					$models= array_slice($models,0, $limit);
				}
			}
			$limitedResult[$key]=$models;
		}
		return $limitedResult;
	}
}
