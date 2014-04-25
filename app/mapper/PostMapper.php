<?php

/**
 * Register Post Mapper
 *
 * Handles inserting data into post and postmeta tables
 *
 * @author James Collngs <james@jclabs.co.uk>
 * @version 0.0.1
 */
class JC_PostMapper {

	/**
	 * Reserved Field Names for post table
	 * @var array
	 */
	protected $_post_fields = array(
		'ID',
		'menu_order',
		'comment_status',
		'ping_status',
		'pinged',
		'post_author',
		'post_category',
		'post_content',
		'post_date',
		'post_date_gmt',
		'post_excerpt',
		'post_name',
		'post_parent',
		'post_password',
		'post_status',
		'post_title',
		'post_type',
		'tags_input',
		'to_ping',
		'tax_input'
	);
	protected $_query_vars = array(
		'post_name' => 'name',
		'ID'        => 'p'
	);

	protected $_template = array();
	protected $_unique = array();

	public $changed_field_count = 0;
	public $changed_fields = array();

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

		return $this->insert_data( $fields, $this->getGroupPostStatus( $group_id ), $this->getGroupPostType( $group_id ) );
	}

	/**
	 * Insert Fields
	 *
	 * @param  array $fields
	 * @param  string $post_status
	 * @param  string $post_type
	 *
	 * @return integer
	 */
	function insert_data( $fields = array(), $post_status = '', $post_type = '' ) {

		$post = array();
		$meta = array();

		$this->changed_field_count = count( $fields );
		$this->changed_fields      = array_keys( $fields );

		$this->sortFields( $fields, $post, $meta );

		// create post type
		$post['post_type'] = $post_type;

		// legacy to set post status in template
		if ( ! isset( $post['post_status'] ) ) {
			$post['post_status'] = $post_status;
		}

		$post_id = wp_insert_post( $post, true );

		// create post meta
		if ( $post_id && ! empty( $meta ) ) {
			foreach ( $meta as $key => $value ) {

				if ( $value != '' ) {
					add_post_meta( $post_id, $key, $value );
				}
			}
		}

		$this->add_version_tag( $post_id );

		return $post_id;
	}

	/**
	 * Update Group Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return integer id
	 */
	function update( $post_id, $group_id = null, $fields = array() ) {

		return $this->update_data( $post_id, $fields, $this->getGroupPostType( $group_id ) );
	}

	/**
	 * Update Fields
	 *
	 * @param  integer $post_id
	 * @param  array $fields
	 * @param  string $post_type
	 *
	 * @return integer
	 */
	function update_data( $post_id, $fields = array(), $post_type = '' ) {

		$post                      = array();
		$meta                      = array();
		$this->changed_field_count = 0;
		$this->changed_fields      = array();

		$this->sortFields( $fields, $post, $meta );

		if ( ! $post_id ) {
			return false;
		}

		// update post type
		if ( ! empty( $post ) ) {

			// check to see if fields need updating
			$query = new WP_Query( array(
				'post_type' => $post_type,
				'p'         => $post_id
			) );
			if ( $query->post_count == 1 ) {
				$old_post = $query->post;

				foreach ( $post as $k => $p ) {
					if ( $p == $old_post->$k ) {
						unset( $post[ $k ] );
					} else {
						$this->changed_field_count ++;
						$this->changed_fields[] = $k;
					}
				}
			}

			if ( ! empty( $post ) ) {
				// update remaining
				$post['ID'] = $post_id;
				wp_update_post( $post );
			}
		}

		// update post meta
		if ( ! empty( $meta ) ) {

			foreach ( $meta as $key => $value ) {

				$old_value = get_post_meta( $post_id, $key, true );

				// check if new value
				if ( $old_value == $value ) {
					continue;
				}

				$this->changed_field_count ++;
				$this->changed_fields[] = $key;

				if ( $value && '' == $old_value ) {
					add_post_meta( $post_id, $key, $value );
				} elseif ( $value && $value != $old_value ) {
					update_post_meta( $post_id, $key, $value );
				} elseif ( '' == $value && $old_value ) {
					delete_post_meta( $post_id, $key, $value );
				}
			}
		}

		$this->update_version_tag( $post_id );

		return $post_id;
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

		return $this->exists_data( $fields, $this->_unique[ $group_id ], $this->getGroupPostType( $group_id ), $this->getGroupPostStatus( $group_id ) );
	}

	/**
	 * Check for existing data
	 *
	 * @param  array $fields
	 * @param  array $unique_fields
	 * @param  string $post_type
	 * @param  string $post_status
	 *
	 * @return boolean
	 */
	function exists_data( $fields = array(), $unique_fields, $post_type, $post_status ) {

		if ( ! isset( $unique_fields ) || ! is_array( $unique_fields ) ) {
			return false;
		}

		$meta_args  = array();
		$query_args = array(
			'post_type'   => $post_type,
			'post_status' => $post_status
		);

		foreach ( $unique_fields as $field ) {

			if ( in_array( $field, $this->_post_fields ) ) {

				if ( array_key_exists( $field, $this->_query_vars ) ) {
					$query_args[ $this->_query_vars[ $field ] ] = $fields[ $field ];
				} else {
					$query_args[ $field ] = $fields[ $field ];
				}

			} else {
				$meta_args[] = array(
					'key'   => $field,
					'value' => $fields[ $field ]
				);
			}
		}

		if ( ! empty( $meta_args ) ) {
			$query_args['meta_query'] = $meta_args;
		}

		$query = new WP_Query( $query_args );

		if ( $query->post_count == 1 ) {
			return $query->post->ID;
		}

		return false;
	}

	/**
	 * Sort fields into post and meta array
	 *
	 * @param  array $fields list of fields
	 * @param  array $post post_data pointer array
	 * @param  array $meta post_meta pointer array
	 *
	 * @return void
	 */
	function sortFields( $fields = array(), &$post = array(), &$meta = array() ) {

		foreach ( $fields as $id => $value ) {

			if ( in_array( $id, $this->_post_fields ) ) {

				// post field
				$post[ $id ] = $value;
			} else {

				// meta field
				$meta[ $id ] = $value;
			}
		}
	}

	/**
	 * Get Group post_status from template
	 *
	 * @param  string $group_id
	 *
	 * @return string status
	 * @todo Check field maps for post_status
	 */
	function getGroupPostStatus( $group_id = '' ) {
		return isset( $this->_template->_field_groups[ $group_id ]['post_status'] ) ? $this->_template->_field_groups[ $group_id ]['post_status'] : null;
	}

	/**
	 * Get Group post_type from template
	 *
	 * @param  string $group_id
	 *
	 * @return string post_type
	 */
	function getGroupPostType( $group_id = '' ) {
		return $this->_template->_field_groups[ $group_id ]['import_type_name'];
	}

	/**
	 * Add Import Tracking Tag
	 *
	 * @param integer $post_id
	 */
	function add_version_tag( $post_id = 0 ) {

		global $jcimporter;
		$importer_id = $jcimporter->importer->get_ID();
		$version     = $jcimporter->importer->get_version();

		add_post_meta( $post_id, '_jci_version_' . $importer_id, $version, true );
	}

	/**
	 * Update Import Tracking Tag
	 *
	 * @param integer $post_id
	 */
	function update_version_tag( $post_id = 0 ) {

		global $jcimporter;
		$importer_id = $jcimporter->importer->get_ID();
		$version     = $jcimporter->importer->get_version();

		$old_version = get_post_meta( $post_id, '_jci_version_' . $importer_id, true );
		if ( $old_version ) {
			update_post_meta( $post_id, '_jci_version_' . $importer_id, $version, $old_version );
		} else {
			add_post_meta( $post_id, '_jci_version_' . $importer_id, $version, true );
		}
	}

	function remove( $importer_id, $version, $post_type ) {

		// get a list of all objects which were not in current update
		$q = new WP_Query( array(
			'post_type'  => $post_type,
			'meta_query' => array(
				array(
					'key'     => '_jci_version_' . $importer_id,
					'value'   => $version,
					'compare' => '!='
				)
			),
			'fields'     => 'ids'
		) );

		// delete list of objects
		if ( $q->have_posts() ) {
			$ids = $q->posts;
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		}

	}

}

// register post importer
add_filter( 'jci/register_importer', 'register_post_mapper', 10, 1 );
function register_post_mapper( $mappers = array() ) {
	$mappers['post'] = 'JC_PostMapper';

	return $mappers;
}