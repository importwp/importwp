<?php
/**
 * Load WordPress test environment
 */

$path = '../../../../tests/phpunit/includes/bootstrap.php';

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( "jc-importer/jc-importer.php" ),
);

// load test function so tests_add_filter() is available
require_once( '../../../../tests/phpunit/includes/functions.php' );

function install_jci_db() {
    require_once 'app/models/schema.php';
    $schema = new JCI_DB_Schema( $GLOBALS['jcimporter'] );
	$schema->install();
}
tests_add_filter( 'setup_theme', 'install_jci_db' );

require_once __DIR__ . '/factory.php';

if ( file_exists( $path ) ) {
	require_once $path;
} else {
	exit( "Couldn't find path to wordpress-tests/bootstrap.php\n" );
}