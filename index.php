<?php
/**
 * Campaign: RossIUrbina42442
 * Created: 2022-02-23 19:55:33 UTC
 */

require 'leadcloak-16rux3dxej30.php';

// ---------------------------------------------------
// Configuration

// Set this to false if application is properly installed.
$enableDebugging = true;

// Set this to false if you won't want to log error messages
$enableLogging = true;

if ($enableDebugging) {
	isApplicationReadyToRun();
}

$data = httpRequestMakePayload($campaignId, $campaignSignature);

$response = httpRequestExec($data);

$handler = httpHandleResponse($response, $enableLogging);

if ($handler) {
	exit();
}

?>