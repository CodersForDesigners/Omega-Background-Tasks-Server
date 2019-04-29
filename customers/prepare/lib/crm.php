<?php

namespace CRM;

ini_set( "display_errors", 0 );
ini_set( "error_reporting", E_ALL );

// Set the timezone
date_default_timezone_set( 'Asia/Kolkata' );
// Do not let this script timeout
set_time_limit( 0 );




/*
 *
 * Set constant values
 *
 */
class DATA {
	public static $apiUrl = 'https://www.zohoapis.com/crm/v2/';
	// public static $apiUrl = 'https://sandbox.zohoapis.com/crm/v2/';
	public static $authCredentials;
}

/*
 *
 * Get the auth credentials
 *
 */
$authCredentialsFilename = __DIR__ . '/../../../__environment/configuration/zoho.json';
if ( empty( realpath( $authCredentialsFilename ) ) )
	sleep( 1 );
DATA::$authCredentials = json_decode( file_get_contents( $authCredentialsFilename ), true );





/*
 * -----
 * A generic API request function
 * -----
 */
function getAPIResponse ( $endpoint, $method, $data = [ ] ) {

	$accessToken = DATA::$authCredentials[ 'access_token' ];

	$httpRequest = curl_init();
	curl_setopt( $httpRequest, CURLOPT_URL, $endpoint );
	curl_setopt( $httpRequest, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $httpRequest, CURLOPT_USERAGENT, 'Zo Ho Ho' );
	$headers = [
		'Authorization: Zoho-oauthtoken ' . $accessToken,
		'Cache-Control: no-cache, no-store, must-revalidate'
	];
	if ( ! empty( $data ) ) {
		$headers[ ] = 'Content-Type: application/json';
		curl_setopt( $httpRequest, CURLOPT_POSTFIELDS, json_encode( $data ) );
	}
	curl_setopt( $httpRequest, CURLOPT_HTTPHEADER, $headers );
	curl_setopt( $httpRequest, CURLOPT_CUSTOMREQUEST, $method );
	$response = curl_exec( $httpRequest );
	curl_close( $httpRequest );

	$body = json_decode( $response, true );

	if ( empty( $body ) )
		return [ ];
		// throw new \Exception( 'Response is empty.', 10 );

	// If an error occurred
	if ( ! empty( $body[ 'code' ] ) ) {
		if ( $body[ 'code' ] == 'INVALID_TOKEN' )
			throw new \Exception( 'Access token is invalid.', 5001 );
		if ( $body[ 'code' ] == 'AUTHENTICATION_FAILURE' )
			throw new \Exception( 'Failure in authenticating.', 5002 );
	}

	return $body;

}



/*
 * -----
 * Get recently created customers; from the past hour
 * -----
 */
function getRecentlyCreatedCustomers () {

	$endpoint = DATA::$apiUrl . 'coql';
	$anHourAgoTimestamp = strtotime( '-1 hour' );
	$thenTimestamp = date( 'Y-m-d', $anHourAgoTimestamp )
					. 'T'
					. date( 'H:i:s', $anHourAgoTimestamp )
					. '+05:30';
	$nowTimestamp = date( 'Y-m-d' )
					. 'T'
					. date( 'H:i:s' )
					. '+05:30';
	$query = <<<QUERY
SELECT
	Hidden_UID, Created_Time, Owner
FROM
	Contacts
WHERE
	UID is null
		AND
	Created_Time between '${thenTimestamp}' and '${nowTimestamp}'
LIMIT
	99
QUERY;

	$responseBody = getAPIResponse( $endpoint, 'POST', [
		'select_query' => $query
	] );

	if ( empty( $responseBody ) || empty( $responseBody[ 'data' ] ) )
		return [ ];

	$customers = array_map( function ( $customer ) {
		return [
			'Owner' => $customer[ 'Owner' ] ?? '',
			'_id' => $customer[ 'id' ],
			'created' => $customer[ 'Created_Time' ],
			'uid' => $customer[ 'Hidden_UID' ]
		];
	}, $responseBody[ 'data' ] );

	return $customers;

}



/*
 * -----
 * Get salespeople by the role provided
 * -----
 */
function getSalespeopleByRole ( $role ) {

	$baseURL = DATA::$apiUrl . 'users';
	$queryParameters = '?' . 'type=ActiveConfirmedUsers';
	$endpoint = $baseURL . $queryParameters;

	$responseBody = getAPIResponse( $endpoint, 'GET' );

	if ( empty( $responseBody ) || empty( $responseBody[ 'users' ] ) )
		return [ ];

	// Filter out the salespeople with the given role
	$salespeopleWithGivenRole = array_filter( $responseBody[ 'users' ], function ( $user ) use ( $role ) {
		return $user[ 'role' ][ 'name' ] == $role;
	} );
	// Pull out only certain field(s)
	$salespeopleWithGivenRole = array_values( array_map( function ( $user ) {
		return [
			'_id' => $user[ 'id' ],
			'name' => $user[ 'full_name' ]
		];
	}, $salespeopleWithGivenRole ) );
	// Sort the salespeople by name
	usort( $salespeopleWithGivenRole, function ( $a, $b ) {
		return strcmp( $a[ 'name' ], $b[ 'name' ] );
	} );

	return $salespeopleWithGivenRole;

}



/*
 * -----
 * Get the id of the salesperson who was assigned the most recent customer
 * -----
 */
function getSalespersonId__OfMostRecentCustomer () {

	$baseURL = DATA::$apiUrl . 'settings/variables/Customer_Round_Robin_Current_Assignee';
	$queryParameters = '?' . 'group=General';
	$endpoint = $baseURL . $queryParameters;

	$responseBody = getAPIResponse( $endpoint, 'GET' );
	$salespersonId = $responseBody[ 'variables' ][ 0 ][ 'value' ];

	return $salespersonId;

}



/*
 * -----
 * Updates a customer record with the given id and data
 * -----
 */
function updateCustomers ( $data ) {

	$endpoint = DATA::$apiUrl . 'Contacts';

	$responseBody = getAPIResponse( $endpoint, 'PUT', [
		'data' => $data,
		'trigger' => [ 'approval', 'workflow', 'blueprint' ]
	] );

	// Since this API works for bulk modifications,
	//  	we're just pulling the first element from the response body
	if ( ! isset( $responseBody[ 'data' ] ) )
		throw new \Exception( 'Response from update operation was empty.', 13 );

	// Catch and report any errors or exceptions
	$e = [ ];
	foreach ( $responseBody[ 'data' ] as $index => $response ) {
		if ( strtolower( $response[ 'code' ] ) != 'success' )
			$e[ ] = 'UID ' . $data[ $index ][ 'UID' ] . ': ' . $response[ 'message' ];
	}
	if ( ! empty( $e ) ) {
		$errorMessage = implode( PHP_EOL . '<br>', $e );
		throw new \Exception( $errorMessage, 13 );
	}

	return $status;

}

/*
 * -----
 * Sets global variables on the CRM
 * -----
 */
function setVar ( $name, $value ) {

	$internalAPIName = preg_replace( '/\s+/', '_', $name );

	$endpoint = DATA::$apiUrl . 'settings/variables/' . $internalAPIName;

	$apiResponse = getAPIResponse( $endpoint, 'PUT', [
		'variables' => [
			[
				'value' => $value
			]
		]
	] );

	return $apiResponse;

}
