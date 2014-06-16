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
abstract class EspressoAPI_Datetimes_Resource_Facade extends EspressoAPI_Generic_Resource_Facade{
	var $modelName="Datetime";
	var $modelNamePlural="Datetimes";
	/**
	 * array of requiredFields allowed for querying and which must be returned. other requiredFields may be returned, but this is the minimum set
	 * @var type 
	 */
	var $requiredFields=array(
		array('var'=>'id','type'=>'int'),
		array('var'=>'is_primary','type'=>'bool'),
		array('var'=>'event_start','type'=>'datetime'),
		array('var'=>'event_end','type'=>'datetime'),
		array('var'=>'registration_start','type'=>'datetime'),
		array('var'=>'registration_end','type'=>'datetime'),
		array('var'=>'limit','type'=>'int'),
		array('var'=>'tickets_left','type'=>'int')
	);
	
}