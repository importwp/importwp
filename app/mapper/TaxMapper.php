<?php
class JC_TaxMapper {

	protected $_template = array();
	protected $_unique = array();
	protected $_required = array('slug');
	protected $_tax_fields = array(
		'term_id',
		'alias_of',
		'description',
		'parent',
		'slug'
	);

	public $changed_field_count = 0;
	public $changed_fields = array();

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
		return $this->insert_data( $fields );
	}

	function insert_data( $fields = array() ){
		
		$args = array();
		$custom_fields = array();

		// escape if required fields are not entered
		if(!isset($fields['term']) || !isset($fields['taxonomy'])){
			return false;
		}

		$term = !empty($fields['term']) ? $fields['term'] : false;
		$taxonomy = !empty($fields['taxonomy']) ? $fields['taxonomy'] : false;

		if($term && $taxonomy){

			unset($fields['term']);
			unset($fields['taxonomy']);

			foreach($fields as $key => $value){

				//
				if(empty($value))
					continue;

				if(in_array($key, $this->_tax_fields)){
					$args[$key] = $value;
				}else{
					$custom_fields[$key] = $value;
				}
			}

			return wp_insert_term( $term, $taxonomy, $args );
		}

		return false;
	}

	/**
	 * Update Data
	 *
	 * @param  string $group_id name of data group
	 * @param  array $fields list of data and their relevent keys
	 *
	 * @return int id
	 */
	function update( $term_id, $group_id = null, $fields = array() ) {

		$taxonomy = !empty($fields['taxonomy']) ? $fields['taxonomy'] : false;
		return $this->update_data( $term_id, $taxonomy, $fields);
	}

	function update_data($term_id, $taxonomy, $fields = array()){

		return wp_update_term( $term_id, $taxonomy, $fields );
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

		if ( ! isset( $this->_unique[ $group_id ] ) || ! is_array( $this->_unique[ $group_id ] ) ) {
			return false;
		}

		$unique_fields  = $this->_unique[ $group_id ];
		$group_template = $this->_template->_field_groups[ $group_id ];

		// escape if required fields are not entered
		if(!isset($fields['term']) || !isset($fields['taxonomy'])){
			return false;
		}

		$term = !empty($fields['term']) ? $fields['term'] : false;
		$taxonomy = !empty($fields['taxonomy']) ? $fields['taxonomy'] : false;

		return $this->exist_data( $term, $taxonomy, array_shift($unique_fields));
	}

	function exist_data($term = '', $taxonomy = '', $field_type = 'name'){

		// echo "TERM: $term \n TAX: $taxonomy \n FIELD TYPE: $field_type";

		$term = get_term_by( $field_type, $term, $taxonomy );
		
		if($term && isset($term->term_id)){
			return $term->term_id;
		}

		return false;
	}
}

// register post importer
add_filter( 'jci/register_importer', 'register_tax_mapper', 10, 1 );
function register_tax_mapper( $mappers = array() ) {
	$mappers['tax'] = 'JC_TaxMapper';

	return $mappers;
}