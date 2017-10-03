<?php
/**
 * Created by PhpStorm.
 * User: jamescollings
 * Date: 03/10/2017
 * Time: 21:37
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class IWP_Field_Parser {

	function parse_func( $field ) {

		switch ( $field[1] ) {
			case 'strtolower':
				return strtolower( $field[2] );
				break;
			case 'strtoupper':
				return strtoupper( $field[2] );
		}

		return '';
	}
}