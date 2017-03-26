<?php

class JC_Parser {

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

	public $seek = 0;
	public $seek_record_count = 0;
	protected $name = '';
	public $session = false;

	/**
	 * Parse loaded data
	 *
	 * Parse data into results array
	 * @return array
	 */
	public function parse() {
	}

	/**
	 * Load initial variables
	 *
	 * @param string $filename
	 */
	public function __construct() {
		// add_filter('jci/register_'.$this->get_name().'_addon_settings', array($this, 'register_settings'), 10, 1);
		add_action( 'jci/load_' . $this->get_name() . '_parser_config', array( $this, 'register_config' ), 10, 2 );
	}

	public function get_name() {
		return $this->name;
	}

	/**
	 * Read file data into records array
	 *
	 * @return    boolean
	 */
	public function loadFile( $filename = '' ) {
		
		if(!file_exists($filename)){
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
	public function startLine( $start = -1 ) {
		$this->start = $start - 1;
	}

	/**
	 * Set End Line
	 *
	 * @param  boolean $start
	 *
	 * @return void
	 */
	public function endLine( $end = -1 ) {
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

	/**
	 * Save current file progress to session
	 * @return void
	 */
	public function save_session(){

		if(!$this->session)
			return;

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;

		$data = array(
			'counter' => $this->seek_record_count, 
			'seek' => $this->seek
		);
		$data = serialize($data);

		file_put_contents($jcimporter->get_plugin_dir() . '/app/tmp/session-' . $jcimporter->importer->ID.'-'.$jcimporter->importer->get_version(), $data);
	}

	/**
	 * Load last file progress from session for current import
	 * @return void
	 */
	public function load_session(){

		if(!$this->session)
			return;

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;

		$tmp_session_file = $jcimporter->get_plugin_dir() . '/app/tmp/session-' . $jcimporter->importer->ID.'-'.$jcimporter->importer->get_version();

		if(file_exists($tmp_session_file)){
			$tmp_seek = unserialize(file_get_contents($tmp_session_file));
			$this->seek = intval($tmp_seek['seek']);
			$this->seek_record_count = intval($tmp_seek['counter']);
		}
	}

	/**
	 * Wipe session data for current import
	 * @return void
	 */
	public function clear_session(){

		if(!$this->session)
			return;

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;

		$files = glob($jcimporter->get_plugin_dir() . '/app/tmp/session-'.$jcimporter->importer->ID.'-*'); // get all file names
		foreach($files as $file){ // iterate files
		  if(is_file($file))
		    unlink($file); // delete file
		}
	}

}

class JCI_ParseField {

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

?>