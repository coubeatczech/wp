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
 * Events API Facade class
 *
 * @package			Espresso REST API
 * @subpackage	includes/APIFacades/Espresso_Events_Resource_Facade.class.php
 * @author				Mike Nelson
 *
 * ------------------------------------------------------------------------
 */
//require_once("EspressoAPI_Generic_Resource_Facade.class.php");
abstract class EspressoAPI_Promocodes_Resource_Facade extends EspressoAPI_Generic_Resource_Facade{
	var $modelName="Promocode";
	var $modelNamePlural="Promocodes";
	/**
	 * array of requiredFields allowed for querying and which must be returned. other requiredFields may be returned, but this is the minimum set
	 * @var type 
	 */
	var $requiredFields=array(
		array('var'=>'id','type'=>'int'),
		array('var'=>'coupon_code','type'=>'string'),
		array('var'=>'amount','type'=>'float'),
		array('var'=>'use_percentage','type'=>'bool'),
		array('var'=>'apply_to_each_attendee','type'=>'bool'),
		array('var'=>'user','type'=>'int'),
	);
	
	
	 
}