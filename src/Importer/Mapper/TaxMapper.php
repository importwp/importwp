<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 12/04/2018
 * Time: 16:09
 */

class TaxMapper extends AbstractMapper implements \ImportWP\Importer\MapperInterface {

	public $changed_field_count = 0;
	public $changed_fields = array();
	protected $_template = array();
	protected $_unique = array();
	protected $_required = array( 'slug' );
	protected $_tax_fields = array(
		'term_id',
		'alias_of',
		'description',
		'parent',
		'slug'
	);

	public function exists( \ImportWP\Importer\ParsedData $data ) {

		$fields = $data->getData('default');
		$unique_fields = $this->template->_unique;

		// escape if required fields are not entered
		if ( ! isset( $fields['term'] ) || ! isset( $fields['taxonomy'] ) ) {
			return false;
		}

		$term     = ! empty( $fields['term'] ) ? $fields['term'] : false;
		$taxonomy = ! empty( $fields['taxonomy'] ) ? $fields['taxonomy'] : false;

		$term = get_term_by( array_shift( $unique_fields ), $term, $taxonomy );

		if ( $term && isset( $term->term_id ) ) {
			$this->ID = $term->term_id;
			return true;
		}

		return false;
	}

	public function insert( \ImportWP\Importer\ParsedData $data ) {

		// clear log
		$this->clearLog();

		// check permissions
		$this->checkPermissions('insert');

		$fields = $data->getData('default');

		$this->ID        = false;
		$args          = array();
		$custom_fields = array();

		// escape if required fields are not entered
		if ( ! isset( $fields['term'] ) || ! isset( $fields['taxonomy'] ) ) {
			return false;
		}

		$term     = ! empty( $fields['term'] ) ? $fields['term'] : false;
		$taxonomy = ! empty( $fields['taxonomy'] ) ? $fields['taxonomy'] : false;

		$all_fields = $fields;

		if ( $term && $taxonomy ) {

			unset( $fields['term'] );
			unset( $fields['taxonomy'] );

			foreach ( $fields as $key => $value ) {

				//
				if ( empty( $value ) ) {
					continue;
				}

				if ( in_array( $key, $this->_tax_fields ) ) {
					$args[ $key ] = $value;
				} else {
					$custom_fields[ $key ] = $value;
				}
			}

			$this->ID = wp_insert_term( $term, $taxonomy, $args );

			if(!is_wp_error($this->ID) && intval($this->ID) > 0 && !empty($custom_fields)){
				foreach($custom_fields as $meta_key => $meta_value) {
					$this->update_custom_field( $this->ID, $meta_key, $meta_value );
				}
			}

			$this->add_version_tag();
			$all_fields['ID'] = $this->ID;
			$this->logImport($all_fields, 'insert', 'taxonomy');
		}

		return $this->ID;

	}

	public function update( \ImportWP\Importer\ParsedData $data ) {

		// clear log
		$this->clearLog();

		// check permissions
		$this->checkPermissions('update');

		$fields = $data->getData('default');
		$all_fields = $fields;

		$args          = array();
		$custom_fields = array();

		// escape if required fields are not entered
		if ( ! isset( $fields['term'] ) || ! isset( $fields['taxonomy'] ) ) {
			return false;
		}

		$taxonomy = ! empty( $fields['taxonomy'] ) ? $fields['taxonomy'] : false;

		unset( $fields['term'] );
		unset( $fields['taxonomy'] );

		foreach ( $fields as $key => $value ) {

			//
			if ( empty( $value ) ) {
				continue;
			}

			if ( in_array( $key, $this->_tax_fields ) || $key == 'name' ) {
				$args[ $key ] = $value;
			} else {
				$custom_fields[ $key ] = $value;
			}
		}

		if(!empty($custom_fields)){
			foreach($custom_fields as $meta_key => $meta_value) {
				$this->update_custom_field( $this->ID, $meta_key, $meta_value );
			}
		}

		$result = wp_update_term( $this->ID, $taxonomy, $args );
		if(!is_wp_error($result)){
			$all_fields['ID'] = $this->ID;
			$this->logImport($all_fields, 'update', 'taxonomy');
			$this->update_version_tag();
		}
		return $result;
	}

	public function delete( \ImportWP\Importer\ParsedData $data ) {
		// TODO: Implement delete() method.
	}

	public function update_custom_field( $term, $key, $value, $unique = false ) {

		$term_id = $term['term_id'];

		$old_value = get_term_meta( $term_id, $key, true );

		// check if new value
		if ( $old_value == $value ) {
			return;
		}

		$this->changed_field_count ++;
		$this->changed_fields[] = $key;

		if ( $value && '' == $old_value ) {
			add_term_meta( $term_id, $key, $value, $unique );
		} elseif ( $value && $value != $old_value ) {
			update_term_meta( $term_id, $key, $value );
		} elseif ( '' == $value && $old_value ) {
			delete_term_meta( $term_id, $key, $value );
		}
	}

	function add_version_tag() {

		if ( ! isset( JCI()->importer ) ) {
			return;
		}

		$importer_id = JCI()->importer->get_ID();
		$version     = JCI()->importer->get_version();

		update_term_meta( $this->ID, '_jci_version_' . $importer_id, $version, true );
	}

	/**
	 * Update Import Tracking Tag
	 */
	function update_version_tag() {

		if ( ! isset( JCI()->importer ) ) {
			return;
		}

		$importer_id = JCI()->importer->get_ID();
		$version     = JCI()->importer->get_version();

		$old_version = get_term_meta( $this->ID, '_jci_version_' . $importer_id, true );
		if ( $old_version ) {
			update_term_meta( $this->ID, '_jci_version_' . $importer_id, $version, $old_version );
		} else {
			add_term_meta( $this->ID, '_jci_version_' . $importer_id, $version, true );
		}
	}
}