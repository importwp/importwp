<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 12/04/2018
 * Time: 16:09
 */

class IWP_Mapper_Tax extends IWP_Mapper implements \ImportWP\Importer\MapperInterface {

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

		$term     = ! empty( $fields['term'] ) ? $fields['term'] : false;
		$slug     = ! empty( $fields['slug'] ) ? $fields['slug'] : false;
		$term_id     = ! empty( $fields['term_id'] ) ? $fields['term_id'] : false;
		$taxonomy = ! empty( $fields['taxonomy'] ) ? $fields['taxonomy'] : false;

		// escape if required fields are not entered
		if(false === $taxonomy || ( false === $term && false === $slug && false === $term_id)){
			return false;
		}

		if(!empty($term_id)){
			$term = get_term_by('id' , $term_id, $taxonomy );
		}else if(!empty($slug)){
			$term = get_term_by('slug' , $slug, $taxonomy );
		}else if(!empty($term)){
			$term = get_term_by('name' , $term, $taxonomy );
		}

		if ( $term && isset( $term->term_id ) ) {
			$this->ID = $term->term_id;
			return true;
		}

		return false;
	}

	public function insert( \ImportWP\Importer\ParsedData $data ) {

		$this->method = 'insert';

		// clear log
		$this->clearLog();

		// check permissions
		$fields = $data->getData('default');
		$fields = $this->checkPermissions('insert', $fields);
		$fields = $this->applyFieldFilters($fields, 'tax');

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

			// returns array('term_id' => #, 'taxonomy_id' => #)
			$insert = wp_insert_term( $term, $taxonomy, $args );
			if(is_wp_error($insert)){
				return $insert;
			}

			$this->ID = $insert['term_id'];

			if(!is_wp_error($this->ID) && intval($this->ID) > 0 && !empty($custom_fields)){
				foreach($custom_fields as $meta_key => $meta_value) {
					$this->update_custom_field( $this->ID, $meta_key, $meta_value );
				}
			}

			$this->update_version_tag();
			$all_fields['ID'] = $fields['ID'] = $this->ID;
			$this->logImport($all_fields, 'insert', 'taxonomy');
			$data->update( $fields );
		}

		return $this->ID;

	}

	public function update( \ImportWP\Importer\ParsedData $data ) {

		$this->method = 'update';

		// clear log
		$this->clearLog();

		// check permissions
		$fields = $data->getData('default');
		$fields = $this->checkPermissions('update', $fields);
		$fields = $this->applyFieldFilters($fields, 'tax');

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

		// returns array('term_id' => #, 'taxonomy_id' => #)
		$result = wp_update_term( $this->ID, $taxonomy, $args );
		if(!is_wp_error($result)){
			$all_fields['ID'] = $fields['ID'] = $this->ID;
			$this->logImport($all_fields, 'update', 'taxonomy');
			$this->update_version_tag();
			$data->update( $fields );
		}
		return $this->ID;
	}

	public function delete( \ImportWP\Importer\ParsedData $data ) {

		$this->method = 'delete';

		// TODO: Implement delete() method.
	}

	public function get_custom_field($id, $key, $single = true){
		return get_term_meta( $id, $key, true );
	}

	public function update_custom_field( $term, $key, $value, $unique = false ) {

		$term_id = intval($term) > 0 ? intval($term) : $term['term_id'];

		$old_value = get_term_meta( $term_id, $key, true );

		// check if new value
		if ( $old_value == $value ) {
			return;
		}

		$data = $this->checkPermissions($this->method, array($key => $value));
		if(!isset($data[$key])){
			return;
		}

		// set to new value in-case it has been changed
		$value = $data[$key];

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

	/**
	 * Remove all users from the current tracked import
	 *
	 * @param  int $importer_id
	 * @param  int $version
	 * @param  string $post_type Not Used
	 *
	 * @return void
	 */
	function remove_all_objects( $importer_id, $version ) {
		$tax_query = get_terms(array(
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key' => '_jci_version_' . $importer_id,
					'value' => $version,
					'compare' => '!='
				)
			)
		));

		$status           = IWP_Status::read_file( $importer_id, $version );
		$status['delete'] = isset($status['delete']) ? intval($status['delete']) : 0;

		if(!empty($tax_query)){
			foreach($tax_query as $term){
				$res = wp_delete_term($term->term_id, $term->taxonomy);

				$status['delete']++;
				IWP_Status::write_file( $status, $importer_id, $version );
			}
		}
	}
}