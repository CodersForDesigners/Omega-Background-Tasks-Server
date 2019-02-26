<?php

namespace CRM;

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
	public static $apiUrl = 'https://www.zohoapis.com/crm/v2/';
	// public static $apiUrl = 'https://sandbox.zohoapis.com/crm/v2/';
	public static $authCredentials;
}

/*
 *
 * Get the auth credentials
 *
 */
$authCredentialsFilename = __DIR__ . '/../../__environment/configuration/zoho.json';
if ( empty( realpath( $authCredentialsFilename ) ) )
	sleep( 1 );
DATA::$authCredentials = json_decode( file_get_contents( $authCredentialsFilename ), true );





/*
 * -----
 * Generic wrapper for communicating with Zoho's API
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
			throw new \Exception( 'Access token is invalid.', 11 );
		if ( $body[ 'code' ] == 'AUTHENTICATION_FAILURE' )
			throw new \Exception( 'Failure in authenticating.', 12 );
	}

	return $body;

}



/*
 * -----
 * Returns all the promotional fields from the Lead module on Zoho
 * -----
 */
function getPromotionalFields () {

	$recordType = 'settings/fields?module=Leads';

	$endpoint = DATA::$apiUrl . $recordType . $queryParameters;

	$responseBody = getAPIResponse( $endpoint, 'GET' );

	$fields = array_values( array_filter( $responseBody[ 'fields' ], function ( $field ) {
		return strpos( $field[ 'field_label' ], 'SMS' ) === 0;
	} ) );
	// $fieldNames = array_map( function ( $field ) {
	// 	return $field[ 'field_label' ];
	// }, $fields );

	return $fields;

}



/*
 * -----
 * Returns all the users who we need to promote to
 * -----
 */
function getUsersWhoNeedToBeServedPromotions ( $page = 1 ) {

	$recordType = 'Leads/search';
	$queryParameters = '?'
						. 'page=' . $page
						. '&' . 'criteria='
						. '(SMS1:equals:false)'
							. 'and'
						. '((Lead_Source:equals:Digital)'
							. 'or'
						. '(Lead_Source:equals:' . urlencode( 'Channel Partner' ) . ')'
							. 'or'
						. '(Lead_Source:equals:' . urlencode( 'Walk-in at Site' ) . ')'
							. 'or'
						. '(Lead_Source:equals:Phone))';

	$endpoint = DATA::$apiUrl . $recordType . $queryParameters;

	$responseBody = getAPIResponse( $endpoint, 'GET' );

	if ( empty( $responseBody[ 'data' ] ) )
		return [ ];

	$userRecords = array_filter( $responseBody[ 'data' ], function ( $user ) {
		return ! empty( $user[ 'Project' ] );
	} );

	$users = [ ];
	foreach ( $userRecords as $userRecord ) {
		$user = [
			'_id' => $userRecord[ 'id' ],
			'uid' => $userRecord[ 'UID' ],
			'uidEncoded' => base64_encode( $userRecord[ 'UID' ] ),
			'name' => empty( $userRecord[ 'First_Name' ] ) ? $userRecord[ 'Last_Name' ] : $userRecord[ 'First_Name' ],
			'phoneNumber' => $userRecord[ 'Phone' ],
			'status' => $userRecord[ 'Lead_Status' ],
			'source' => $userRecord[ 'Lead_Source' ],
			'project' => $userRecord[ 'Project' ][ 0 ],
			'to promote' => [ ]
		];
		foreach ( $userRecord as $key => $value ) {
			if ( strpos( $key, 'SMS' ) === 0 && $value == false ) {
				// $formattedKey = preg_replace( [ '/^Promoted_/', '/_/' ], [ '', ' ' ], $key );
				$formattedKey = preg_replace( '/SMS/', 'SMS ', $key );
				$user[ 'to promote' ][ ] = $formattedKey;
			}
		}
		$users[ ] = $user;
	}

	// Are there more users that match the criteria but haven't been fetched?
	$thereAreMoreUsers = $responseBody[ 'info' ][ 'more_records' ];

	return [ $users, $thereAreMoreUsers ];

}



/*
 * -----
 * Updates a user with the given id
 * -----
 */
function updateUser ( $id, $data ) {

	$recordType = 'Leads';

	$endpoint = DATA::$apiUrl . $recordType;

	$data[ 'id' ] = $id;
	$responseBody = getAPIResponse( $endpoint, 'PUT', [ 'data' => [ $data ] ] );

	// Since this API works for bulk modifications,
	//  	we're just pulling the first element from the response body
	if ( ! isset( $responseBody[ 'data' ] ) )
		throw new \Exception( 'Response from update operation was empty.', 13 );

	$status = $responseBody[ 'data' ][ 0 ];
	if ( ! empty( $status[ 'code' ] ) ) {
		if ( $status[ 'code' ] != 'SUCCESS' ) {
			$errorMessage = $status[ 'code' ] . ' : ' . $status[ 'message' ];
			throw new \Exception( $errorMessage, 13 );
		}
	}

	return $status;

}
