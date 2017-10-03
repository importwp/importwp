<?php
/**
 * Created by PhpStorm.
 * User: jamescollings
 * Date: 03/10/2017
 * Time: 21:30
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class IWP_XML_Field_Parser extends IWP_Field_Parser {

	var $base_node = '';
	var $xml = '';


	function __construct( $xml ) {
		$this->xml = $xml;
	}

	function parse_field( $field, $base_node ) {

		$this->base_node = $base_node;
		$result          = preg_replace_callback( '/{(.*?)}/', array( $this, 'parse_value' ), $field );
		$result          = preg_replace_callback( '/\[jci::([a-z]+)\(([a-zA-Z0-9_ -]+)\)(\/)?\]/', array(
			$this,
			'parse_func'
		), $result );

		return $result;
	}

	function parse_value( $field ) {

		$xpath_query = $this->base_node . $field[1];
		$jc_results  = array();
		$values      = array();

		$terms = $this->xml->xpath( $xpath_query );

		foreach ( $terms as $t ) {

			if ( strpos( $t, ',' ) !== false ) {
				// csv
				$temp   = explode( ',', str_replace( ', ', ',', $t ) );
				$values = array_merge( $values, $temp );
			} else {
				$values[] = (string) $t;
			}
		}

		$temp = array_merge( $jc_results, $values );

		return implode( ',', $temp );
	}
}