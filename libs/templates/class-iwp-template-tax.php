<?php

class IWP_Template_Tax extends IWP_Template {

	public $_name = 'taxonomy';

	public $_unique = array( 'name' );

	protected $_group = 'taxonomy';

	protected $_import_type = 'tax';

	protected $_import_type_name = '';

	protected $_settings = array(
		'unique' => array( 'name' ),
		'key' => array(),
		'taxonomies' => 1,
	);

	public function __construct() {

		add_filter( 'jci/log_taxonomy_columns', array( $this, 'log_taxonomy_columns' ) );
		add_action( 'jci/log_taxonomy_content', array( $this, 'log_taxonomy_content' ), 10, 2 );

		parent::__construct();
	}

	public function register_fields() {

		$this->register_basic_field('ID', 'term_id');
		$this->register_basic_field('Taxonomy', 'taxonomy');
		$this->register_basic_field('Term', 'term');
		$this->register_basic_field('Slug', 'slug');
		$this->register_basic_field('Parent', 'parent', array(
			'after' => array( $this, 'after_parent')
		));
		$this->register_basic_field('Description', 'description');
		$this->register_basic_field('Alias Of', 'alias_of');

		$this->register_section('Settings', 'settings');
		$this->register_enable_toggle('Enable Id Field', 'enable_id', 'settings', array(
			'term_id',
		));
		$this->register_enable_toggle('Enable Slug Field', 'enable_slug', 'settings', array(
			'slug',
		));
		$this->register_enable_toggle('Enable Parent Field', 'enable_parent', 'settings', array(
			'parent',
		));
		$this->register_enable_toggle('Enable Alias Field', 'enable_alias', 'settings', array(
			'alias_of',
		));
	}

	public function before_group_save( $data, $group_id ) {

		$enable_parent =  $this->get_field_value('enable_parent');

		/**
		 * Check to see if post_parent
		 */
		if ( $enable_parent === '1' && ! empty( $data['parent'] ) ) {

		    $parent_field_type = $this->get_field_value('parent_field_type');
			$term_id = 0;

			if ( $parent_field_type === 'name' ) {

				// name 
				$term = get_term_by( 'name', $data['parent'], $data['taxonomy'] );
				if ( $term ) {
					$term_id = intval( $term->term_id );
				}

			} elseif ( $parent_field_type === 'slug' ) {

				// slug
				$term = get_term_by( 'slug', $data['parent'], $data['taxonomy'] );
				if ( $term ) {
					$term_id = intval( $term->term_id );
				}

			} elseif ( $parent_field_type === 'id' ) {

				// ID
				$term_id = intval( $data['parent'] );
			}

			// set post parent to int or clear
			$data['parent'] = $term_id;
		}

		// set slug if none is present
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['term'] );
		}

		return $data;
	}

	public function after_parent($field_id){
		?>
        <div class="iwp__sub-fields">
			<?php
			echo IWP_FormBuilder::select( 'field[' . $this->get_group() . '][' . $field_id . '_field_type]', array(
				'label' => 'Type',
				'options' => array(
					'id'   => 'ID',
					'slug' => 'Slug',
					'name' => 'Name'
				),
				'default' => $this->get_field_value($field_id.'_field_type', 'id')
			));
			?>
        </div>
		<?php
    }

	/**
	 * Register Post Columns
	 *
	 * @param  array $columns
	 *
	 * @return array
	 */
	public function log_taxonomy_columns( $columns ) {

		$columns['term']   = 'Term';
		$columns['parent'] = 'Parent';
		$columns['slug']   = 'Slug';
		$columns['method'] = 'Method';

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
	public function log_taxonomy_content( $column, $data ) {

		switch ( $column ) {
			case 'term':

				echo $data[$this->get_group()]['term'];

				break;
			case 'parent':

				$parent = get_term_by( 'term_id', $data[$this->get_group()]['parent'], $data[$this->get_group()][$this->get_group()] );
				if ( $parent ) {
					echo $parent->name;
				} else {
					echo 'N/A';
				}

				break;
			case 'slug':

				echo $data[$this->get_group()]['slug'];

				break;
			case 'method':

				if ( $data[$this->get_group()]['_jci_type'] == 'I' ) {
					echo 'Inserted';
				} elseif ( $data[$this->get_group()]['_jci_type'] == 'U' ) {
					echo 'Updated';
				}

				break;
		}
	}
}

add_filter( 'jci/register_template', 'register_tax_template', 10, 1 );
function register_tax_template( $templates = array() ) {
	$templates['taxonomy'] = 'IWP_Template_Tax';

	return $templates;
}