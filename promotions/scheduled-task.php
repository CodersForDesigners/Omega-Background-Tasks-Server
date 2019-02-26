<?php
/*
 *
 * This script promotes things to users who meet specific criteria
 *
 */

ini_set( 'display_errors', 0 );
ini_set( 'error_reporting', E_ALL );

header( 'Access-Control-Allow-Origin: *' );

date_default_timezone_set( 'Asia/Kolkata' );

// continue processing this script even if
// the user closes the tab, or
// hits the ESC key
ignore_user_abort( true );

// do not let this script timeout
set_time_limit( 0 );

// Is the script being run through the CLI?
$isCLI = ( php_sapi_name() == 'cli' );

// Set the header of the response
header( 'Content-Type: application/json' );

require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/crm.php';
require __DIR__ . '/lib/comms.php';
require __DIR__ . '/lib/templating.php';



// Constants
$numberOfUsersToPromoteTo = 199;

// SMS Sender Ids
$templatesDir = __DIR__ . '/../__environment/templates/';
$smsSenderIdsFilename = __DIR__ . '/../__environment/configuration/sms-sender-ids.json';
$senderIds = json_decode( file_get_contents( $smsSenderIdsFilename ), true );

/*
 * Log all the errors thrown at the point when the script is shutting down.
 */
$issues = [ ];
register_shutdown_function( function () {
	global $isCLI;
	global $issues;
	$mailData = [
		'user' => [ 'From Name' => 'Omega Bot', 'email' => 'adi@lazaro.in', 'name' => 'adi', 'additionalEmails' => [ 'adityabhat@lazaro.in' ] ],
		'mail' => [ 'Subject' => '#!ERROR', 'Body' => 'An error occurred while doing something.' ]
	];
	// Mailer\send( $issues );
	if ( empty( $issues ) )
		return;

	foreach ( $issues as $issue ) {
		$errorMessage = date( '[Y/m/d][ga],' ) . $issue . PHP_EOL;
		if ( $isCLI )
			fwrite( 'STDERR', $errorMessage );
		else
			echo $errorMessage;
	}
} );


$numberOfUsersPromotedTo = 0;
$userBatchNumber = 1;
$templateVarMaps = [ ];	// a cache of all the template var maps that will be encountered
do {

	// Get the users
	[ $users, $thereAreMoreUsers ] = CRM\getUsersWhoNeedToBeServedPromotions( $userBatchNumber );

	// Send an SMS to each of the users
	foreach ( $users as $user ) {

		if ( $numberOfUsersPromotedTo >= $numberOfUsersToPromoteTo )
			break;

		$project = $user[ 'project' ];
		$from = $senderIds[ $project ] ?? null;
		// If no sender id for the project exists, move on to the next iteration
		if ( empty( $from ) )
			continue;

		// Pull in relevant user data
		$uid = $user[ 'uid' ];
		$to = $user[ 'phoneNumber' ];
		$thingsToPromote = $user[ 'to promote' ];
		// Flag that keeps track if atleast one "thing" was promoted to the user
		$atleastOneThingWasPromotedToThisUser = false;

		foreach ( $thingsToPromote as $promotion ) {

			$templateName = $promotion . ' ' . $project;
			$templateFilename = $templatesDir . Util\slugify( $templateName ) . '.json';
			// The internal "API" names of our promotional fields don't have spaces
			$promotionField = preg_replace( '/\s/', '', $promotion );

			// If the template var map has never been fetched before, fetch it
			if ( ! isset( $templateVarMaps[ $templateName ] ) ) {
				// If the var map file for the template don't exist, assign it a false value
				if ( ! file_exists( $templateFilename ) )
					$templateVarMaps[ $templateName ] = false;
				else
					// Cache the template var map
					$templateVarMaps[ $templateName ] = json_decode( file_get_contents( $templateFilename ), true );
			}

			// If the template var map is `false`,
			//  	then we assume that the template does not exist, and move on
			if ( ! $templateVarMaps[ $templateName ] )
				continue;

			$templateVarMap = $templateVarMaps[ $templateName ];
			$data = array_map( function ( $index, $value ) use ( $user ) {
				return [ $index => Templating\render( $value, $user ) ];
			}, array_keys( $templateVarMap ), $templateVarMap );
			$data = array_reduce( $data, function ( $acc, $var ) {
				return $acc + $var;
			}, [ ] );

			try {
				$sendSMSStatus = Comms\sendSMS( $to, $from, $templateName, $data );
				sleep( 1 );	// This is so the Zoho API has time to breathe
				$updateUserStatus = CRM\updateUser( $user[ '_id' ], [
					$promotionField => true
				] );
				$logMessage = date( '[Y/m/d][ga],' ) . 'Promoted ' . $promotion . ' to ' . $user[ 'name' ] . ',' . $uid . PHP_EOL;
				echo $logMessage;
				$atleastOneThingWasPromotedToThisUser = true;
			}
			catch ( \Exception $e ) {
				// Log error and capture error on the side
				// 	A mail will be posted when the script exits.
				// Error in sending SMS
				if ( $e->getCode() == 21 ) {
					$errorMessage = 'Sending SMS to User with UID ' . $uid . PHP_EOL . $e->getMessage();
					$issues[ ] = $errorMessage;
				}
				// Error in updating user
				else if ( $e->getCode() == 13 ) {
					$errorMessage = 'Updating User with UID ' . $uid . PHP_EOL . $e->getMessage();
					$issues[ ] = $errorMessage;
				}
			}

		}

		if ( $atleastOneThingWasPromotedToThisUser )
			$numberOfUsersPromotedTo += 1;

	}

	$userBatchNumber += 1;

} while (
	$numberOfUsersPromotedTo < $numberOfUsersToPromoteTo
		and
	$thereAreMoreUsers
);
