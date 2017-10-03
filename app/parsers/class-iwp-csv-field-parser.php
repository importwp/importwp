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

class IWP_CSV_Field_Parser extends IWP_Field_Parser {

	var $row = '';

	function __construct( $row ) {
		$this->row = $row;
	}

	function parse_value( $field ) {
		$col = $this->parse_field( intval( $field[1] ) );

		return isset( $this->row[ $col ] ) ? $this->row[ $col ] : $field[0];
	}

	function parse_field( $field ) {
		$result = preg_replace_callback( '/{(.*?)}/', array( $this, 'parse_value' ), $field );
		$result = preg_replace_callback( '/\[jci::([a-z]+)\(([a-zA-Z0-9_ -]+)\)(\/)?\]/', array(
			$this,
			'parse_func'
		), $result );

		return $result;
	}
}