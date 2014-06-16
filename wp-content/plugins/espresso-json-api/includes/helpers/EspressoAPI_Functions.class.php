<?php

/**
 * Miscellaneous helper functions for EspressoAPI. Basically anything that should be in 
 * php core but wasn't.
 *
 * @author mnelson4
 */
class EspressoAPI_Functions {
	/**
	 * generates random string for sessino key
	 * mostly taken from http://stackoverflow.com/questions/853813/how-to-create-a-random-string-using-php
	 * @return string 
	 */
	static function generateRandomString($length=10){
		$valid_chars="qwertyuiopasdfghjklzxcvbnm1234567890";
		// start with an empty random string
		$random_string = "";

		// count the number of chars in the valid chars string so we know how many choices we have
		$num_valid_chars = strlen($valid_chars);

		// repeat the steps until we've created a string of the right length
		for ($i = 0; $i < $length; $i++){
			// pick a random number from 1 up to the number of valid chars
			$random_pick = mt_rand(1, $num_valid_chars);

			// take the random character out of the string of valid chars
			// subtract 1 from $random_pick because strings are indexed starting at 0, and we started picking at 1
			$random_char = $valid_chars[$random_pick-1];

			// add the randomly-chosen char onto the end of our string so far
			$random_string .= $random_char;
		}
		// return our finished random string
		return $random_string;
	}
	
	/**
	 * taken from http://php.net/manual/en/function.array-merge-recursive.php#usernotes.
	 * recursively merges two arrays like array_merge_recursive, except it will 
	 * overwrite duplicate keys' values, instead of turning value into an array
	 * and appending it
	 * @param array $Arr1
	 * @param array $Arr2
	 * @return array 
	 */
	static function array_merge_recursive_overwrite($Arr1, $Arr2){
		if(!is_array($Arr1))
			return $Arr2;
		if(!is_array($Arr2)){
			return $Arr1;
		}
		foreach($Arr2 as $key => $Value)
		{
			if(array_key_exists($key, $Arr1) && is_array($Value))
			$Arr1[$key] = EspressoAPI_Functions::array_merge_recursive_overwrite($Arr1[$key], $Arr2[$key]);

			else
			$Arr1[$key] = $Value;

		}
		return $Arr1;
	}
	
	/**
	 * for comparing two floats to see if they have the same value
	 *  
	 */
	static function floats_are_equal($float1,$float2){
		return (abs($float1-$float2)<0.00001);
	}
}
?>
