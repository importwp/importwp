<?php
/**
 * Define exception constants
 */
define( 'JCI_WARN', 0 );
define( 'JCI_ERR', 1 );

/**
 * ImportWP Exception Class
 */
class IWP_Exception extends Exception {

	public function __construct( $message, $code = 0 ) {
		parent::__construct( $message, $code );
	}
}

?>