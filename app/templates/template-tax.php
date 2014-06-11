<?php

class JC_Tax_Template extends JC_Importer_Template {

	public $_name = 'Taxonomy';

	public $_field_groups = array(

		'taxonomy' => array(
			'import_type'      => 'tax',
			'import_type_name' => '',
			'field_type'       => 'single',
			'group'            => 'taxonomy', // for backwards compatability
			'key'              => array(),
			'unique'           => array('name'),
			'relationship'     => array(),
			'taxonomies' => 1,
			'map'              => array(
				array(
					'title' => 'ID',
					'field' => 'term_id'
				),
				array(
					'title' => 'Taxonomy',
					'field' => 'taxonomy'
				),
				array(
					'title' => 'Term',
					'field' => 'term'
				),
				array(
					'title' => 'Slug',
					'field' => 'slug'
				),
				// int of parent term
				array(
					'title' => 'Parent',
					'field' => 'parent'
				),
				array(
					'title' => 'Description',
					'field' => 'description'
				),
				// expected is the slug that the term will be an alias of.
				array(
					'title' => 'Alias Of',
					'field' => 'alias_of'
				),
			)
		)
	);

	public function __construct() {
		parent::__construct();

		add_filter( 'jci/log_tax_columns', array( $this, 'log_tax_columns' ) );
		add_action( 'jci/log_tax_content', array( $this, 'log_tax_content' ), 10, 2 );
	}

	public function before_group_save( $data, $group_id ) {

		// set slug if none is present
		if(empty($data['slug'])){
			$data['slug'] = sanitize_title( $data['term'] );
		}

		// change parent from name to id
		if(!empty($data['parent'])){
			$result = get_term_by( 'name', $data['parent'], 'category');
			$data['parent'] = intval($result->term_id);
		}

		return $data;
	}

	/**
	 * Register Post Columns
	 *
	 * @param  array $columns
	 *
	 * @return array
	 */
	public function log_tax_columns( $columns ) {

		$columns['term']        = 'Term';
		$columns['parent']  = 'Parent';
		$columns['slug'] = 'Slug';
		$columns['method']      = 'Method';

		return $columns;
	}

	/**
	 * Output column data
	 *
	 * @param  array $column
	 * @param  array $data
	 *
	 * @return void
	 */
	public function log_tax_content( $column, $data ) {

		switch ( $column ) {
			case 'term':
				
				echo $data['taxonomy']['term'];

				break;
			case 'parent':
				
				$parent = get_term_by( 'term_id', $data['taxonomy']['parent'], 'category');
				if($parent){
					echo $parent->name;
				}
				
				break;
			case 'slug':

				echo $data['taxonomy']['slug'];

				break;
			case 'method':

				if ( $data['taxonomy']['_jci_type'] == 'I' ) {
					echo 'Inserted';
				} elseif ( $data['taxonomy']['_jci_type'] == 'U' ) {
					echo 'Updated';
				}
				
				break;
		}
	}
}

add_filter( 'jci/register_template', 'register_tax_template', 10, 1 );
function register_tax_template( $templates = array() ) {
	$templates['tax'] = 'JC_Tax_Template';

	return $templates;
}