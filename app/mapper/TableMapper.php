<?php

/**
 * Register Table Mapper
 *
 * Handles inserting data custom tables
 *
 * @author James Collngs <james@jclabs.co.uk>
 * @version 0.0.1
 */
class JC_TableMapper extends JC_BaseMapper {

	protected $_template = array();
	protected $_unique = array();

	public $changed_field_count = 0;

	function __construct( $template = array(), $unique = array() ) {
		$this->_template = $template;
		$this->_unique   = $unique;
	}

	/**
	 * Insert Group Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return int id
	 */
	function insert( $group_id = null, $fields = array() ) {

		global $wpdb;
		$table = $this->_template->_field_groups[ $group_id ]['import_type_name'];

		// setup fields
		$query_fields = array();
		for ( $i = 0; $i < count( $fields ); $i ++ ) {
			$query_fields[] = '%s';
		}

		//create array for wpdb prepare
		$values = array();
		$keys   = array();
		foreach ( $fields as $field => $value ) {
			$values[] = $value;
			$keys[]   = $field;
		}

		$data = array_merge( $values );

		$query = "INSERT INTO `" . $table . "` (" . implode( ', ', $keys ) . ") VALUES(" . implode( ', ', $query_fields ) . ")";

		return $wpdb->query( $wpdb->prepare( $query, $data ) );
	}

	/**
	 * Update Group Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return int id
	 */
	function update( $post_id, $group_id = null, $fields = array() ) {

		global $wpdb;
		$table = $this->_template->_field_groups[ $group_id ]['import_type_name'];

		//create array for wpdb prepare
		$values = array();
		$set    = array();
		foreach ( $fields as $field => $value ) {
			$values[] = $value;
			$set[]    = "$field=%s";
		}

		// create where statement
		$primary_keys = $this->_template->_field_groups[ $group_id ]['key'];
		$values[]     = $post_id;
		$where        = "$primary_keys[0]=%s";

		$query = "UPDATE `" . $table . "` SET " . implode( ', ', $set ) . " WHERE " . $where;

		return $wpdb->query( $wpdb->prepare( $query, $values ) );
	}

	/**
	 * Check for Existing Group Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return int id
	 */
	function exists( $group_id = null, $fields = array() ) {

		global $wpdb;

		$table = $this->_template->_field_groups[ $group_id ]['import_type_name'];
		$keys  = $this->_unique[ $group_id ];

		$primary_keys = $this->_template->_field_groups[ $group_id ]['key'];

		if ( empty( $primary_keys ) ) {
			$primary_keys = array( '*' );
		}

		$query_fields = array();
		$values       = array();

		foreach ( $keys as $key => $value ) {
			$query_fields[] = $value . ' = %s';
			$values[]       = $fields[ $value ];
		}

		$query          = "SELECT " . implode( ",", $primary_keys ) . " FROM `" . $table . "` WHERE " . implode( " AND ", $query_fields );
		$prepared_query = $wpdb->prepare( $query, $values );

		return $wpdb->get_var( $prepared_query );
	}

}

// register post importer
add_filter( 'jci/register_importer', 'register_table_mapper', 10, 1 );
function register_table_mapper( $mappers = array() ) {
	$mappers['table'] = 'JC_TableMapper';

	return $mappers;
}