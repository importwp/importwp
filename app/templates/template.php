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
	public $_unique = array();

	public function __construct() {

		add_action( 'jci/before_' . $this->get_name() . '_row_save', array(
			$this,
			'alter_template_unique_fields'
		), 1, 0 );

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
			'cache_results' => false,
			'update_post_meta_cache' => false,
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

	public function get_template_group_id(){

		reset( $this->_field_groups );
		return key( $this->_field_groups );
	}

	final public function alter_template_unique_fields(){

		// Allow user to overwrite unique field without using filter.
		$unique_field = JCI()->importer->get_template_unique_field();
		if(!empty($unique_field)){
			$this->_unique = explode(',', $unique_field);
		}

		$this->_unique = apply_filters('iwp/template_unique_fields', $this->_unique);
		$this->_unique = apply_filters(sprintf('iwp/template_%s_unique_fields', $this->get_name()), $this->_unique);
	}
}