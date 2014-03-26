<?php
/**
 * Define exception constants
 */
define('JCI_WARN', 0);
define('JCI_ERR', 1);

/**
 * JC Importer Exception Class
 */
class JCI_Exception extends Exception{

	public function __construct($message, $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
    }
}
?>