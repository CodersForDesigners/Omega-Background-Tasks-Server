<?php

namespace Templating;

/*
 *
 * Renders a Mustache-ish template given a data context and returns the output as a string
 *
 */
function render ( $template, $context = [ ] ) {
	return preg_replace_callback( '/{{([^{}]+)}}/', function ( $matches ) use ( $context ) {
		return $context[ $matches[ 1 ] ];
	} , $template );
}

/*
 *
 * Executes a PHP file given a data context and returns the file's output as a string
 *
 */
function renderFromFile ( $template, $context = [ ] ) {
	extract( $context );
	ob_start();
	require $template;
	return ob_get_clean();
}
