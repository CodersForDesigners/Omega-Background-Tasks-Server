<?php

namespace Comms;

ini_set( "display_errors", 1 );
ini_set( "error_reporting", E_ALL );

// Set the timezone
date_default_timezone_set( 'Asia/Kolkata' );
// Do not let this script timeout
set_time_limit( 0 );





/*
 *
 * Set global constants
 *
 */
class DATA {
	public static $apiUrl;
	public static $authCredentials;
}

$authCredentialsFilename = __DIR__ . '/../../__environment/configuration/2factor.json';

DATA::$authCredentials = json_decode( file_get_contents( $authCredentialsFilename ), true );
DATA::$apiUrl = 'http://2factor.in/API/V1/' . DATA::$authCredentials[ 'apiKey' ] . '/';





function getFormBoundary () {
	return '----ThisIsNotAWallButABoundaryt1n4W34b';
}

/*
 *
 * Returns a `form-data` formatted string for use in a POST request
 *
 * **NOTE**: Leave the double quotes as is in this function.
 * 	The HTTP request won't work otherwise!
 *
 */
function formatToMultipartFormData ( $data ) {

	$formBoundary = getFormBoundary();
	$eol = "\r\n";
	$fieldMeta = "Content-Disposition: form-data; name=";
	$nameFieldQuote = "\"";
	$dataString = '';

	foreach ( $data as $name => $content ) {
		$dataString .= "--" . $formBoundary . $eol
					. $fieldMeta . $nameFieldQuote . $name . $nameFieldQuote
					. $eol . $eol
					. $content
					. $eol;
	}

	$dataString .= "--" . $formBoundary . "--";

	return $dataString;

}

function getAPIResponse ( $endpoint, $method, $data = [ ] ) {

	$httpRequest = curl_init();
	curl_setopt( $httpRequest, CURLOPT_URL, $endpoint );
	curl_setopt( $httpRequest, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $httpRequest, CURLOPT_USERAGENT, 'Two too' );
	curl_setopt( $httpRequest, CURLOPT_HTTPHEADER, [
		'Cache-Control: no-cache, no-store, must-revalidate',
		'Content-Type: multipart/form-data; boundary=' . getFormBoundary()
	] );
	curl_setopt( $httpRequest, CURLOPT_POSTFIELDS, formatToMultipartFormData( $data ) );
	curl_setopt( $httpRequest, CURLOPT_CUSTOMREQUEST, $method );
	$response = curl_exec( $httpRequest );
	curl_close( $httpRequest );

	return $response;

}

/*
 *
 * Error codes:
 * 	21. The SMS did was not sent for some reason.
 *
 */
function sendSMS ( $to, $from, $template, $data = [ ] ) {

	$vars = array_merge( ...array_map( function ( $index, $value ) {
		return [ 'VAR' . $index => $value ];
	}, array_keys( $data ), $data ) );

	$endpoint = DATA::$apiUrl . 'ADDON_SERVICES/SEND/TSMS';
	$requestBody = array_merge( [
		'From' => $from,
		'To' => $to,
		'TemplateName' => $template
	], $vars );

	$response = getAPIResponse( $endpoint, 'POST', $requestBody );
	$response = json_decode( $response, true );

	if ( $response[ 'Status' ] != 'Success' ) {
		$errorMessage = $response[ 'Status' ] . ': ' . $response[ 'Details' ];
		throw new \Exception( $errorMessage, 21 );
	}

	return $response;

}
