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
 * Generic API Facade class
 *
 * @package			Espresso REST API
 * @subpackage	includes/EspressoAPI_Response_Formatter.class.php
 * @author				Mike Nelson
 *
 * ------------------------------------------------------------------------
 */

class EspressoAPI_Response_Formatter{
	
	/**
	 * finds the format part of a request, eg: espresso-api/events.json or espresso-api/attendees/123.xml
	 * @param type $param1
	 * @param type $param2
	 * @param type $param3
	 * @return type 
	 */
	static function findFormatInParams($params){
		foreach($params as $param){
			$posOfDot=strpos($param,".");
			if($posOfDot!==FALSE){
				return substr($param,$posOfDot+1);
			}
		}
		return 'json';
	}
	static function setContentType($format){
		if($format=='xml'){
			header('Content-type: text/xml, application/xml');
		}elseif(in_array($format,array('json','pretty_json'))){
			header('Content-type: application/json');
		}
	}
    /**
     * formats the input to the specified format
     * @param $infoToFormat is an array, ro object, or whatever
     * @param $formatName can be json or xml
     * @return string of $infoToFormat converted to specified format
     */
    static function format($infoToFormat, $formatName='json'){

		switch($formatName){
			case 'xml':
				$xmlOutput="<?xml version='1.0'?>".EspressoAPI_Response_Formatter::arrayToXml("response",$infoToFormat);
				return $xmlOutput;
			case 'pretty_json':
				return EspressoAPI_Response_Formatter::prettyJson(EspressoAPI_Response_Formatter::arrayToJson($infoToFormat));
			case 'json':
			default:
				return EspressoAPI_Response_Formatter::arrayToJson($infoToFormat);
		}
    }
    static function arrayToJson($infoToFormat){
		return json_encode($infoToFormat);
    }
    
	static function arrayToXml($thisNodeName,$input){
		if(empty($thisNodeName))
			return;
		if(is_numeric($thisNodeName))
			throw new Exception("cannot parse into xml. remainder :".print_r($input,true));
		if(!(is_array($input) || is_object($input))){
			return "<$thisNodeName><![CDATA[$input]]></$thisNodeName>";
		}
		else{
			$newNode="<$thisNodeName>";
			foreach($input as $key=>$value){
				if(is_numeric($key))
					$key=substr($thisNodeName,0,strlen($thisNodeName)-1);
				if(isset($key))
					$newNode.=EspressoAPI_Response_Formatter::arrayToXml($key,$value);
			}
			$newNode.="</$thisNodeName>";
			return $newNode;
		}
	}
	static function parseJson($input){
		$input= $input;
			$output=json_decode($input,true);
			if(json_last_error()!=JSON_ERROR_NONE){
				$input=stripslashes($input);
				$output=json_decode($input,true);
			}
			if(json_last_error()!=JSON_ERROR_NONE){
				
				switch (json_last_error()) {
					case JSON_ERROR_NONE:
					break;
					case JSON_ERROR_DEPTH:
						$parseErrorMessage= ' JSON_ERROR_DEPTH - Maximum stack depth exceeded';
					break;
					case JSON_ERROR_STATE_MISMATCH:
						$parseErrorMessage= ' JSON_ERROR_STATE_MISMATCH - Underflow or the modes mismatch';
					break;
					case JSON_ERROR_CTRL_CHAR:
						$parseErrorMessage= ' JSON_ERROR_CTRL_CHAR - Unexpected control character found';
					break;
					case JSON_ERROR_SYNTAX:
						$parseErrorMessage= ' JSON_ERROR_SYNTAX - Syntax error, malformed JSON';
					break;
					case JSON_ERROR_UTF8:
						$parseErrorMessage= ' JSON_ERROR_UTF8 - Malformed UTF-8 characters, possibly incorrectly encoded';
					break;
					default:
						$parseErrorMessage= ' - Unknown error';
				}
				throw new EspressoAPI_InputParsingError($parseErrorMessage,400);
			}
			return $output;
	}
	
	static function parse($input,$formatName='json'){
		if(in_array($formatName,array('json','pretty_json'))){
			$output=EspressoAPI_Response_Formatter::parseJson($input);
		}else{
			
		}
		return $output;
	}
	/**
	 * grabbed from  http://www.php.net/manual/en/function.json-encode.php#80339, 
	 * it will put the json in a nicer format
	 * @param string $json
	 * @return string 
	 */
	static function prettyJson($json) 
{ 
    $tab = "  "; 
    $new_json = ""; 
    $indent_level = 0; 
    $in_string = false; 

    $json_obj = json_decode($json); 

    if($json_obj === false) 
        return false; 

    $json = json_encode($json_obj); 
    $len = strlen($json); 

    for($c = 0; $c < $len; $c++) 
    { 
        $char = $json[$c]; 
        switch($char) 
        { 
            case '{': 
            case '[': 
                if(!$in_string) 
                { 
                    $new_json .= $char . "\n" . str_repeat($tab, $indent_level+1); 
                    $indent_level++; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case '}': 
            case ']': 
                if(!$in_string) 
                { 
                    $indent_level--; 
                    $new_json .= "\n" . str_repeat($tab, $indent_level) . $char; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case ',': 
                if(!$in_string) 
                { 
                    $new_json .= ",\n" . str_repeat($tab, $indent_level); 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case ':': 
                if(!$in_string) 
                { 
                    $new_json .= ": "; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case '"': 
                if($c > 0 && $json[$c-1] != '\\') 
                { 
                    $in_string = !$in_string; 
                } 
            default: 
                $new_json .= $char; 
                break;                    
        } 
    } 

    return $new_json; 
} 
}
