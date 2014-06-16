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
abstract class EspressoAPI_Pricetypes_Resource_Facade extends EspressoAPI_Generic_Resource_Facade{
	var $modelName="Pricetype";
	var $modelNamePlural="Pricetypes";
	/**
	 * array of requiredFields allowed for querying and which must be returned. other requiredFields may be returned, but this is the minimum set
	 * @var type 
	 */
	var $requiredFields=array(
		array('var'=>'id','type'=>'int'),
		array('var'=>'name','type'=>'string'),
		array('var'=>'is_member','type'=>'bool'),
		array('var'=>'is_discount','type'=>'bool'),
		array('var'=>'is_tax','type'=>'bool'),
		array('var'=>'is_percent','type'=>'bool'),
		array('var'=>'is_global','type'=>'bool'),
		array('var'=>'order','type'=>'int')
	);
	 /**
	  * creation of event facade, calls concrete child class' _creatEvent function
	  * @param array $createParameters
	  * @return array 
	  */
     function create($createParameters){
         return $this->_createDatetime($createParameters);
     }
     abstract protected function _create($createParameters);
	 
}