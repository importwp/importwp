<?php

/**
 * Register Virtual Mapper
 *
 * Handles import when extending without inserting data
 *
 * @author James Collngs <james@jclabs.co.uk>
 * @version 0.0.1
 */
class JC_VirtualMapper {

	protected $_template = array();
	protected $_unique = array();

	public $changed_fields = 0;
	public $changed_field_count = 0;

	function __construct( $template = array(), $unique = array() ) {
		$this->_template = $template;
		$this->_unique   = $unique;
	}

	/**
	 * Insert Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return int id
	 */
	function insert( $group_id = null, $fields = array() ) {
		return array();
	}

	/**
	 * Update Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return int id
	 */
	function update( $user_id, $group_id = null, $fields = array() ) {

		return array();
	}

	/**
	 * Check for Existing Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return int id
	 */
	function exists( $group_id = null, $fields = array() ) {

		return true;
	}
}

// register virtual importer
add_filter( 'jci/register_importer', 'register_virtual_mapper', 10, 1 );
function register_virtual_mapper( $mappers = array() ) {
	$mappers['virtual'] = 'JC_VirtualMapper';

	return $mappers;
}