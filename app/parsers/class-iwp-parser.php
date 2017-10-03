<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class IWP_Parser {

	/**
	 * Store loaded string data
	 * @var string
	 */
	protected $data = '';
	/**
	 * Store loaded file name
	 *
	 * @var string
	 */
	protected $file = '';
	/**
	 * Store parsed data
	 *
	 * @var array
	 */
	protected $records = array();
	/**
	 * Set start and end lines
	 *
	 * @var int
	 */
	protected $start = - 1, $end = - 1;
	protected $name = '';

	/**
	 * Load initial variables
	 */
	public function __construct() {
		add_action( 'jci/load_' . $this->get_name() . '_parser_config', array( $this, 'register_config' ), 10, 2 );
	}

	public function get_name() {
		return $this->name;
	}

	/**
	 * Parse loaded data
	 *
	 * Parse data into results array
	 * @return array
	 */
	public function parse() {
	}

	/**
	 * Read file data into records array
	 *
	 * @return    boolean
	 */
	public function loadFile( $filename = '' ) {

		if ( ! file_exists( $filename ) ) {
			return false;
		}

		$this->file = $filename;

		return true;
	}

	/**
	 * Read string into records data
	 *
	 * @param  string $string
	 *
	 * @return void
	 */
	public function loadString( $string = '' ) {
		$this->data = $string;
	}

	/**
	 * Set Start Line
	 *
	 * @param  boolean $end
	 *
	 * @return void
	 */
	public function startLine( $start = - 1 ) {
		$this->start = $start - 1;
	}

	/**
	 * Set End Line
	 *
	 * @param  boolean $start
	 *
	 * @return void
	 */
	public function endLine( $end = - 1 ) {
		$this->end = $end - 1;
	}

	/**
	 * Parse Node traversal String
	 *
	 * Parse Node traversal string from the format customers/email to array(customers, email)
	 * returning false on fail
	 *
	 * @param  string $nodes
	 *
	 * @return array
	 */
	public function processNodes( $nodes ) {

		$nodes = (string) $nodes;

		// if empty
		if ( $nodes == '' ) {
			return false;
		}

		// if no /
		if ( is_string( $nodes ) && strpos( $nodes, '/' ) === false ) {
			return array( $nodes );
		}

		// explode / and remove empty's
		$nodes  = explode( '/', $nodes );
		$output = array();
		foreach ( $nodes as $key => $node ) {

			if ( ! empty( $node ) ) {
				$output[] = $node;
			}
		}

		// check for empty notes after parsing
		if ( empty( $output ) ) {
			return false;
		}

		return $output;
	}

	public function register_settings( $settings = array() ) {
		return $settings;
	}

	public function register_config( $general, $fields ) {
	}
}