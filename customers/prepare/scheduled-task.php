<?php
/*
 *
 * This script searches for newly created customers and preps them for the PSAs
 *
 */

ini_set( 'display_errors', 0 );
ini_set( 'error_reporting', E_ALL );

date_default_timezone_set( 'Asia/Kolkata' );

// Do not let this script timeout
set_time_limit( 0 );

// Is the script being run through the CLI?
$isCLI = ( php_sapi_name() == 'cli' );

if ( ! $isCLI ) {
	// Continue processing this script even if the user closes the tab, or
	//  	hits the ESC key
	ignore_user_abort( true );
	// Allow this script to triggered from another origin
	header( 'Access-Control-Allow-Origin: *' );
	// Remove / modify certain headers of the response
	header_remove( 'X-Powered-By' );
	header( 'Content-Type: application/json' );	// JSON format
}

// Log the start of the script
// echo date( '[Y/m/d][g:ia],' ) . 'Started' . PHP_EOL;

require __DIR__ . '/lib/mailer.php';
require __DIR__ . '/lib/crm.php';





/*
 *
 * Log all the errors thrown at the point when the script is shutting down.
 *
 */
$issues = [ ];
register_shutdown_function( function () {
	global $isCLI;
	global $issues;

	if ( empty( $issues ) )
		return;

	Mailer\log( '#ERROR: Preparing Customers', implode( '<br>', $issues ) );

	foreach ( $issues as $issue ) {
		$errorMessage = date( '[Y/m/d][g:ia],' ) . $issue . PHP_EOL;
		if ( $isCLI )
			fwrite( STDERR, $errorMessage );
		else
			echo $errorMessage;
	}
} );





// Get the newly created customers
$customers = CRM\getRecentlyCreatedCustomers();
if ( empty( $customers ) )
	exit;

// Get all the salespeople that customers are going to be assigned to
$salespeople = CRM\getSalespeopleByRole( 'PSA' );
	// Yank out the salespeoples' `id`s to a simple array
$salespeopleIds = array_column( $salespeople, '_id' );
// Get the `id` of the last salesperson that a customer was assigned to
$salespersonId__OfMostRecentCustomer = CRM\getSalespersonId__OfMostRecentCustomer();

/*
 * Prepare a bulk customer update request body.
 * 	We're going to update all the new customers in one go
 */
// Iterate through all the customers and get them assigned, one-by-one
$requestBody = [ ];
$currentAssigneeId = $salespeopleIds[ array_search(
	$salespersonId__OfMostRecentCustomer,
	$salespeopleIds
) ];
$ourUserIds = [
	'3261944000000158021',	// Omega Bot
		'3744182000000197013',	// Omega Bot (sandbox)
	'3261944000006440019',	// Omega Archive Bot
		'3744182000000258001'	// Omega Archive Bot (sandbox)
];
foreach ( $customers as $customer ) {
	$requestBodyForThisCustomer = [
		'id' => $customer[ '_id' ],
		'UID' => $customer[ 'uid' ]
	];
	if ( in_array( $customer[ 'Owner' ][ 'id' ], $ourUserIds ) ) {
		$currentAssigneeIndex = ( array_search( $currentAssigneeId, $salespeopleIds ) + 1 )
								% count( $salespeople );
		$currentAssigneeId = $salespeopleIds[ $currentAssigneeIndex ];
		$requestBodyForThisCustomer[ 'Owner' ] = $currentAssigneeId;
	}
	$requestBody[ ] = $requestBodyForThisCustomer;
}
// Update the customers
try {
	CRM\updateCustomers( $requestBody );
} catch ( \Exception $e ) {
	$issues[ ] = $e->getMessage();
}

// Finally, store the `id` of the salesperson that was last assigned a customer
CRM\setVar( 'Customer Round Robin Current Assignee', $currentAssigneeId );

echo date( '[Y/m/d][g:ia],' ) . 'Prepped ' . count( $customers ) . ' customer(s)' . PHP_EOL;
