<?php
/**
 * Demonstration of the LoadImpact REST API. Please note that when extracting
 * value from a test result, what value you want will depend on your test config.
 * In the sample, I'm assiming that the test is a simple rampup test. At the end
 * of this script, I want to return the response time when running with the most
 * active clients. 
 * 
 * For more information about the API: http://developer.loadimpact.com/
 * 
 * Requires libcurl and php5-curl.
 *
 * Some notes:
 * List of status codes http://developer.loadimpact.com/#get-tests-id
 *
 * Your parameters:
 * =====================
 * $token: Find or generate a token at https://loadimpact.com/account/
 * 
 * $test_config_id. Once you have created a test configuration you can find the
 *                  id of your test config in the URL. Go to https://loadimpact.com/test/config/list/ a
 *                  nd click edit on your test. The test id is the last segment of the URL, i.e:  
 *                  https://loadimpact.com/test/config/edit/12345 
 * 
 * 
 * @category  demonstration
 * @copyright Erik Torsner <erik@torgesta.com>
 * @license   CC-BY-SA 2.5 Generic http://creativecommons.org/licenses/by-sa/2.5/
 *
 */

$token = 'NNNNNNNNNNNNNNNNNNN';
$test_config_id = 999999;
$verbose = TRUE;
$base = 'https://api.loadimpact.com/v2/';

$resp = loadimpactapi("test-configs/$test_config_id/start", 'POST');
if(isset($resp->id)) {
	$test_id = $resp->id; // The Id of the running test.
	$running = TRUE;
	$status = loadimpactapi("tests/$test_id", 'GET');

	while($running) {
		if($verbose) echo "Test $test_id is {$status->status_text} \n";
		if($status->status > 2) {	
			$running = FALSE; 
			break;
		}
		sleep(15);
		$status = loadimpactapi("tests/$test_id", 'GET');
	}

	// At this point, a status code != 3 would indicate a failure
	if($status->status == 3) {
		$jsonresult = loadimpactapi("tests/$test_id/results", 'GET');
		$timestamps = resulttoarray($jsonresult);
		echo responsetimeatmaxclients($timestamps) ."\n";
	}
} else {
	echo "Test $test_config_id failed to start \n";
}


/**
 * A wrapper for CURL. Helps make API requests to the LoadImpact API
 * 
 * @param  String $command    The API command to run ()
 * @param  String $method     GET or POST
 * @param  String $postfields POSTDATA
 * 
 * @return Object             Decoded JSON from server.
 */
function loadimpactapi($command, $method, $postfields = '') {
	global $token, $base;	
	$cmd = $base . $command;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $cmd);
	curl_setopt($ch, CURLOPT_USERPWD, $token . ':');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	if($method == 'POST') {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	}		
	$result = curl_exec($ch);
	return json_decode($result);
}


/**
 * Iterates an array of timestamps. Every item in the array
 * is an array like: Array('clients' => X, 'responsetime' => Y)
 * 
 * @param  Array $timestamps   An array of timestamps
 * 
 * @return Number              The responsetime found
 */
function responsetimeatmaxclients($timestamps) {
	$maxclients = 0;
	$responsetime = 0;
	foreach($timestamps as $timestamp) {
		if($timestamp['clients'] >= $maxclients) {
			$responsetime = $timestamp['responsetime'];
		}
	}
	return $responsetime;
}


/**
 * Take the result object from LoadImpact and transform it into a 
 * table that's a bit easier to work with
 * 
 * @param  Object $result  The json_decoded data from LoadImpact API
 *
 * @return Array           An ordered array of timestamps.
 */
function resulttoarray($result) {
	$data = array();
	foreach($result->__li_user_load_time as $t) {
		$data[$t->timestamp] = array('responsetime' => $t->value);
	}
	foreach($result->__li_clients_active as $t) {
		if(isset($data[$t->timestamp])) {
			$data[$t->timestamp]['clients'] = $t->value;
		}
	}
	return $data;	
}


?>
