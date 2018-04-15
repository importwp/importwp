<?php

/**
 * Abstract Template class
 *
 * All templates extend this class, initializing core template hooks
 * @since v0.0.1
 */
class JC_Importer_Template {

	public $_name = '';
	public $_field_groups = array();

	/**
	 * List of identifier fields
	 * @var array $_unique
	 */
	public $_unique;

	public function __construct() {

		// before record gets imported
		if ( method_exists( $this, 'before_template_save' ) ) {
			add_action( 'jci/before_' . $this->get_name() . '_row_save', array(
				$this,
				'before_template_save'
			), 10, 2 );
		}

		// before group save
		if ( method_exists( $this, 'before_group_save' ) ) {
			add_filter( 'jci/before_' . $this->get_name() . '_group_save', array( $this, 'before_group_save' ), 10, 2 );
		}

		// after group save
		if ( method_exists( $this, 'after_group_save' ) ) {
			add_filter( 'jci/after_' . $this->get_name() . '_group_save', array( $this, 'after_group_save' ), 10, 2 );
		}

		// after record has been imported
		if ( method_exists( $this, 'after_template_save' ) ) {
			add_action( 'jci/after_' . $this->get_name() . '_row_save', array( $this, 'after_template_save' ), 10, 2 );
		}

	}

	public function get_name() {
		return $this->_name;
	}

	public function get_groups() {
		return apply_filters( 'jci/template/get_groups', $this->_field_groups );
	}

	/**
	 * Add in identifier fields to be parsed with data, allowing to be referenced from other fields
	 *
	 * @param array $groups Field Groups
	 *
	 * @return array
	 */
	public function add_reference_fields( $groups = array() ) {

		foreach ( $this->_field_groups as $k => $group ) {
			if ( isset( $groups[ $k ] ) ) {
				if ( isset( $group['identifiers'] ) && ! empty( $group['identifiers'] ) ) {
					foreach ( $group['identifiers'] as $field => $map ) {
						$groups[ $k ]['fields'][ '_jci_ref_' . $field ] = $map;
					}
				}
			}
		}

		return $groups;
	}

	/**
	 * Find existing post/page/custom by reference field
	 *
	 * @param string $field Reference Field Name
	 * @param string $value Value to check against reference
	 * @param string $group_id Template group Id
	 *
	 * @return bool
	 */
	protected function get_post_by_cf( $field, $value, $group_id ) {

		if ( ! isset( $this->_field_groups[ $group_id ] ) ) {
			return false;
		}

		$post_type = $this->_field_groups[ $group_id ]['import_type_name'];

		$query = new WP_Query( array(
			'post_type'      => $post_type,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_jci_ref_' . $field,
					'value' => $value
				)
			)
		) );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return false;
	}
}