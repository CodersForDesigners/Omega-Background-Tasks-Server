<?php

namespace Util;

ini_set( "display_errors", 0 );
ini_set( "error_reporting", E_ALL );

// Set the timezone
date_default_timezone_set( 'Asia/Kolkata' );
// Do not let this script timeout
set_time_limit( 0 );





function slugify ( $string ) {

	$charsToRemove = <<<EOT
/[\s$*_+~.()&'"!\-:@\\\]+/
EOT;
	$hyphensOnEnds = '/^-|-$/';

	$sluggedString = preg_replace( [ $charsToRemove, $hyphensOnEnds ], [ '-', '' ], $string );
	return strtolower( $sluggedString );

}
