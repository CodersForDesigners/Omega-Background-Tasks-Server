#!/usr/bin/php
<?php

ini_set( "display_errors", 'stderr' );
ini_set( "error_reporting", E_ALL );

// Set the timezone
date_default_timezone_set( 'Asia/Kolkata' );
// Do not let this script timeout
set_time_limit( 0 );





function generateAuthTokens ( $urlParameters ) {

	$urlParameterString = http_build_query( $urlParameters, '', '&', PHP_QUERY_RFC3986 );
	$url = 'https://accounts.zoho.com/oauth/v2/token?' . $urlParameterString;

	$httpRequest = curl_init();
	curl_setopt( $httpRequest, CURLOPT_URL, $url );
	curl_setopt( $httpRequest, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $httpRequest, CURLOPT_USERAGENT, 'Zo Ho Ho' );
	curl_setopt( $httpRequest, CURLOPT_CUSTOMREQUEST, 'POST' );

	$response = curl_exec( $httpRequest );
	curl_close( $httpRequest );

	return json_decode( $response, true );

}


// function getAuthTokensFromGrantToken ( $grantToken ) {

// 	$urlParameters = [
// 		'grant_type' => 'authorization_code',
// 		'client_id' => '1000.XHYX53L6GUT664831A7Z07UD7GDWKU',
// 		'client_secret' => 'cb6a0bff1fdaf089f1ef31fad0090e111aedecea41',
// 		'redirect_uri' => 'http://kl.lazaro.in/omega/oauthcallback',
// 		'code' => $grantToken,
// 	];

// 	return getAuthTokens( $urlParameters );

// }

function sendMail ( $envelope ) {

	$subject = $envelope[ 'subject' ] ?? '';
	$body = $envelope[ 'body' ] ?? '';

	$mailDataFilename = tempnam( sys_get_temp_dir(), 'err-refresh-token' );
	$mailData = [
		'user' => [ 'From Name' => 'Omega Bot', 'email' => 'adi@lazaro.in', 'name' => 'adi', 'additionalEmails' => [ 'mark@lazaro.in' ] ],
		'mail' => [ 'Subject' => $subject, 'Body' => $body ]
	];
	file_put_contents( $mailDataFilename, json_encode( $mailData ) );
	exec( 'php \'' . __DIR__ . '/../mail-send/index.php\' -i \'' . $mailDataFilename . '\'' );
	unlink( $mailDataFilename );

}

function sendErrorMail ( $body ) {

	// sendMail( [
	// 	'subject' => '#!ERROR',
	// 	'body' => 'An error occurred while refreshing the Zoho tokens.<br><br>' . $body
	// ] );

}


function main () {

	// Get the current auth credentials
	$authCredentialsFilename = __DIR__ . '/../../__environment/configuration/zoho.json';
	try {
		$authCredentials = json_decode( file_get_contents( $authCredentialsFilename ), true );
	} catch ( Exception $e ) {
		sendErrorMail( $e->getMessage() );
		return;
	}

	// Get the refreshed tokens
		// A. Build the request body
	$urlParameters = array_intersect_key( $authCredentials, array_flip( [ 'client_id', 'client_secret', 'refresh_token' ] ) );
	$urlParameters[ 'grant_type' ] = 'refresh_token';
		// B. Get them tokens
	$tokenRefreshAttempts = 0;
	$freshTokens = null;
	$warningSent = false;
	while ( empty( $freshTokens ) ) {
		$tokenRefreshAttempts += 1;
		try {
			$freshTokens = generateAuthTokens( $urlParameters );
		} catch ( Exception $e ) {
			if ( $tokenRefreshAttempts == 5 ) {
				sendErrorMail( $e->getMessage() );
				$warningSent = true;
			}
		}
	}
	if ( $warningSent )
		sendMail( [ 'Subject' => 'No need to panic. The tokens were refreshed.' ] );

	// Copy over the new values back
	$authCredentials[ 'access_token' ] = $freshTokens[ 'access_token' ];
	// $authCredentials[ 'refresh_token' ] = $freshTokens[ 'refresh_token' ];
	$authCredentials[ 'expires_at' ] = time()
									+ ( $freshTokens[ 'expires_in' ] / 1000 )
									- ( 5 * 60 );

	// Write the credentials to a new file
	$new__AuthCredentialsFilename = __DIR__ . '/../../__environment/configuration/zoho-' . date( 'Ymd.His' ) . '.json';
	$authFileUpdated = file_put_contents( $new__AuthCredentialsFilename, json_encode( $authCredentials, JSON_PRETTY_PRINT ) );
	if ( ! $authFileUpdated )
		return sendErrorMail( 'Tokens were refreshed but the new auth file could not be created.' );

	// Point the auth file symbolic link to the new file
	$previous__AuthCredentialsFilename = realpath( $authCredentialsFilename );
	unlink( $authCredentialsFilename );
	symlink( $new__AuthCredentialsFilename, $authCredentialsFilename );
	unlink( $previous__AuthCredentialsFilename );

}

main();
