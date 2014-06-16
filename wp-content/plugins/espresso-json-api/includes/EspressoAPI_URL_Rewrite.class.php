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
 * Router class
 * class for rewriting requests to the EspressoAPI_Router class, and capturing parameters and putting them into WP query vars we can access
 * 
 * @package			Espresso REST API
 * @subpackage	includes/EspressoAPI_Router.class.php
 * @author				Mike Nelson
 *
 * ------------------------------------------------------------------------
 */
class EventEspressoAPI_URL_Rewrite {
	
	function getRewriteRules(){
		$apiNamespace='espresso-api';
		$version="v1";
		$rewriteRules = array(
			"$apiNamespace/$version/authenticate$"=>'index.php?espresso-api-request=true&espresso-api-authenticate=true',
			"$apiNamespace/$version/\$" => 'index.php?espresso-api-request=true',
			"$apiNamespace/$version/([^/]+)\$" => 'index.php?espresso-api-request=true&espresso-sessionkey=$matches[1]',
			"$apiNamespace/$version/([^/]+)/([^/]+)$" => 'index.php?espresso-api-request=true&espresso-api1=$matches[1]&espresso-sessionkey=$matches[2]',
			"$apiNamespace/$version/([^/]+)/([^/]+)/([^/]+)$" => 'index.php?espresso-api-request=true&espresso-api1=$matches[1]&espresso-api2=$matches[2]&espresso-sessionkey=$matches[3]',
			"$apiNamespace/$version/([^/]+)/([^/]+)/([^/]+)/([^/]+)$" => 'index.php?espresso-api-request=true&espresso-api1=$matches[1]&espresso-api2=$matches[2]&espresso-api3=$matches[3]&espresso-sessionkey=$matches[4]'
		);
		return $rewriteRules;
	}

	function __construct() {
		add_action('init', array($this, 'init'));
		add_action('wp_loaded', array($this, 'flushRulesIfOursNotPresent'));
		add_filter('query_vars', array($this, 'insertQueryVars'));
	}

	function init() {
		add_filter('rewrite_rules_array', array($this, 'addURLRewrites'));
	}

	function addURLRewrites($wpRewriteRules) {
		$json_api_rules = $this->getRewriteRules();
		return array_merge($json_api_rules, $wpRewriteRules);
	}

	function noticeURLRewritesAreOff() {
		?>
		<div class='updated'>
		<?php echo __(" Event Espresso API cannot work because it appears URL Rewrite is turned off. Please activate it from  Settings -> Permalinks, and there select the 'post name' option for URLs.", "event_espresso") ?>
		</div>
		<?php
	}

	function flushRulesIfOursNotPresent() {
		$rules = get_option('rewrite_rules');
		if (empty($rules)) {
			//rules are empty because permalinks must be off
			add_action('admin_notices', array($this, 'noticeURLRewritesAreOff'));
			flush_rewrite_rules();
			return;
		}

		$ruleMapFroms = array_keys($rules);
		$needFlush = false;
		foreach ($this->getRewriteRules() as $mapFrom => $mapTo) {
			if (empty($ruleMapFroms) || !in_array($mapFrom, $ruleMapFroms)) {
				$needFlush = true;
			}
		}
		if ($needFlush) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}

	function insertQueryVars($vars) {
		array_push($vars,'espresso-api-authenticate');
		array_push($vars, 'espresso-api-request');
		array_push($vars, 'espresso-api1');
		array_push($vars, 'espresso-api2');
		array_push($vars, 'espresso-api3');
		array_push($vars, 'espresso-sessionkey');
		return $vars;
	}

}

new EventEspressoAPI_URL_Rewrite();