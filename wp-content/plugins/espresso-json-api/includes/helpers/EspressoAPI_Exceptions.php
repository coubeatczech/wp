<?php

class EspressoAPI_MethodNotImplementedException extends Exception{
	
}
class EspressoAPI_UnauthorizedException extends Exception{
	
}
/**
 *for sending when a user tries to login with the wrong username/crednetials 
 */
class EspressoAPI_BadCredentials extends Exception{
	
}
/**
 * used to indicate when a specific request returns nothing, like /events/13 and it doesn't exist 
 */
/*class EspressoAPI_ObjectDoesNotExist extends Exception{
	
}*/
/**
 * to be outputted when something failed within teh espressoAPI addon and we
 * want to signal it (we could have just used Exception, but we MIGHT want to distinguish someday) 
 */
class EspressoAPI_OperationFailed extends Exception{
	
}
/**
 * exception to indicate when users try to modify/view a resource that doesn't exist,eg: PUT /events/1 when it doesn't exist 
 */
class EspressoAPI_ObjectDoesNotExist extends Exception{
	
}
/**
 * when this exception is caught in the espressoAPI addon, the exception's "status" 
 * and "status_code" are outputted instead of the generic 500 status code 
 */
class EspressoAPI_SpecialException extends Exception{
	var $status_code;
	function __construct($status,$status_code=500){
		parent::__construct($status);
		$this->status_code=$status_code;
	}
	function getStatusCode(){
		return $this->status_code;
	}
}
/**
 *for handling when a client submits a bad request. Eg: a request with an illegal query parameter 
 */
class EspressoAPI_BadRequestException extends Exception{
	
}
/**

 *for handling when we try to load a class that doesn't exist from teh EspressoAPI_ClassLoader 
 */
class EspressoAPI_ClassNotFound extends Exception{
	
}

/**

 * for handling json or xml input parsing errors 
 */
class EspressoAPI_InputParsingError extends Exception{
	
}