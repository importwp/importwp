<?php

/**
 * Register User Mapper
 *
 * Handles inserting wordpress users
 *
 * @author James Collngs <james@jclabs.co.uk>
 * @version 0.0.1
 */
class JC_UserMapper extends JC_BaseMapper {

	protected $_template = array();
	protected $_unique = array();

	protected $_user_fields = array(
		'ID',
		'user_pass',
		'user_login',
		'user_nicename',
		'user_url',
		'user_email',
		'display_name',
		'nickname',
		'first_name',
		'last_name',
		'description',
		'rich_editing',
		'user_registered',
		'role',
		'jabber',
		'aim',
		'yim'
	);
	protected $_user_required = array( 'user_login' );

	public $changed_field_count = 0;
	public $changed_fields = array();
	public $notify_insert = false;

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

		if ( ! isset( $fields['user_login'] ) || empty( $fields['user_login'] ) ) {
			throw new JCI_Exception( "No username present", JCI_ERR );
		}

		if ( ! isset( $fields['user_pass'] ) || empty( $fields['user_pass'] ) ) {
			throw new JCI_Exception( "No password present", JCI_ERR );
		}

		$result = wp_insert_user( $fields );
		if ( is_wp_error( $result ) ) {
			throw new JCI_Exception( $result->get_error_message(), JCI_ERR );
		}

		$this->add_version_tag( $result );

		do_action( 'jci/after_user_insert', $result, $fields );

		return $result;
	}

	/**
	 * Update Group Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return int id
	 */
	function update( $user_id, $group_id = null, $fields = array() ) {

		$fields['ID'] = $user_id;
		$result       = wp_update_user( $fields );
		if ( is_wp_error( $result ) ) {
			throw new JCI_Exception( $result->get_error_message(), JCI_ERR );
		}

		$this->update_version_tag( $user_id );

		return $user_id;
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

		if ( ! isset( $this->_unique[ $group_id ] ) || ! is_array( $this->_unique[ $group_id ] ) ) {
			return false;
		}

		$unique_fields  = $this->_unique[ $group_id ];
		$group_template = $this->_template->_field_groups[ $group_id ];

		$query_args = array();
		$meta_args  = array();

		$search         = array(); // store search values
		$search_columns = array(); // store search columns

		foreach ( $unique_fields as $field ) {

			if ( in_array( $field, $this->_user_fields ) ) {

				$search_columns[] = $field;
				$search[]         = $fields[ $field ];
			} else {
				$meta_args[] = array(
					'key'     => $field,
					'value'   => $fields[ $field ],
					'compare' => '=',
					'type'    => 'CHAR'
				);
			}
		}

		// create search
		$query_args['search']         = implode( ', ', $search );
		$query_args['search_columns'] = $search_columns;
		$query_args['meta_query']     = $meta_args;

		$query = new WP_User_Query( $query_args );

		if ( $query->total_users == 1 ) {
			return $query->results[0]->ID;
		}

		return false;
	}

	/**
	 * Add Import Tracking Tag
	 *
	 * @param integer $user_id
	 */
	function add_version_tag( $user_id = 0 ) {

		global $jcimporter;
		$importer_id = $jcimporter->importer->get_ID();
		$version     = $jcimporter->importer->get_version();

		add_post_meta( $user_id, '_jci_version_' . $importer_id, $version, true );
	}

	/**
	 * Update Import Tracking Tag
	 *
	 * @param integer $user_id
	 */
	function update_version_tag( $user_id = 0 ) {

		global $jcimporter;
		$importer_id = $jcimporter->importer->get_ID();
		$version     = $jcimporter->importer->get_version();

		$old_version = get_user_meta( $user_id, '_jci_version_' . $importer_id, true );
		if ( $old_version ) {
			update_user_meta( $user_id, '_jci_version_' . $importer_id, $version, $old_version );
		} else {
			add_user_meta( $user_id, '_jci_version_' . $importer_id, $version, true );
		}
	}

	/**
	 * Remove Users who were not in latest import
	 * 
	 * @param  int $importer_id 
	 * @param  int $version     
	 * @param  string $post_type   Not Used
	 * @return void
	 */
	function remove( $importer_id, $version, $post_type ) {

		// get a list of all objects which were not in current update
		$user_query = new WP_User_Query( array (
			'meta_query'     	=> array(
				array(
					'key'     	=> '_jci_version_' . $importer_id,
					'value'   	=> $version,
					'compare'   => '!=',
				),
			),
			'fields'			=> array( 'id' ),
		) );

		// delete list of objects
		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				wp_delete_user( $user->id);
			}
		}

	}

}

// register post importer
add_filter( 'jci/register_importer', 'register_user_mapper', 10, 1 );
function register_user_mapper( $mappers = array() ) {
	$mappers['user'] = 'JC_UserMapper';

	return $mappers;
}