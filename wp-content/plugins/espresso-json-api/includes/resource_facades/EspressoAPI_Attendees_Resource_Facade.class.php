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
abstract class EspressoAPI_Attendees_Resource_Facade extends EspressoAPI_Generic_Resource_Facade{
	var $modelName="Attendee";
	var $modelNamePlural="Attendees";
	var $requiredFields=array(
		array('var'=>'id','type'=>'int'),
		array('var'=>'firstname','type'=>'string'),
		array('var'=>'lastname','type'=>'string'),
		array('var'=>'address','type'=>'string'),
		array('var'=>'address2','type'=>'string'),
		array('var'=>'city','type'=>'string'),
		array('var'=>'state','type'=>'string'),
		array('var'=>'country','type'=>'string'),
		array('var'=>'zip','type'=>'string'),
		array('var'=>'email','type'=>'string'),
		array('var'=>'phone','type'=>'string')
		);
}