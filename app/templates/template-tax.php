<?php

class JC_Tax_Template extends JC_Importer_Template {

	public $_name = 'taxonomy';

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

		add_filter( 'jci/log_taxonomy_columns', array( $this, 'log_taxonomy_columns' ) );
		add_action( 'jci/log_taxonomy_content', array( $this, 'log_taxonomy_content' ), 10, 2 );

		add_action( 'jci/after_template_fields', array( $this, 'field_settings' ) );
		add_action( 'jci/save_template', array( $this, 'save_template' ) );
	}

	public function before_group_save( $data, $group_id ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$id = $jcimporter->importer->ID;

		/**
		 * Clear unenabled fields
		 */
		if ( $this->enable_id == 0 ) {
			unset( $data['term_id'] );
		}
		if ( $this->enable_slug == 0 ) {
			unset( $data['slug'] );
		}
		if ( $this->enable_parent == 0 ) {
			unset( $data['parent'] );
		}
		if ( $this->enable_alias == 0 ) {
			unset( $data['alias_of'] );
		}

		/**
		 * Check to see if post_parent
		 */
		if($this->enable_parent && !empty($data['parent'])){

			$post_parent_type = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'_field_type',
				'parent_type'
			));

			$term_id = 0;

			if($post_parent_type == 'name'){
				
				// name 
				$term = get_term_by( 'name', $data['parent'], $data['taxonomy']);
				if($term){
					$term_id = intval($term->term_id);
				}

			}elseif($post_parent_type == 'slug'){
				
				// slug
				$term = get_term_by( 'slug', $data['parent'], $data['taxonomy']);
				if($term){
					$term_id = intval($term->term_id);
				}

			}elseif($post_parent_type == 'id'){

				// ID
				$term_id = intval($data['parent']);
			}

			// set post parent to int or clear
			$data['parent'] = $term_id;
		}

		// set slug if none is present
		if(empty($data['slug'])){
			$data['slug'] = sanitize_title( $data['term'] );
		}

		return $data;
	}

	public function field_settings( $id ) {

		$template = ImporterModel::getImportSettings( $id, 'template' );
		if ( $template == $this->_name ) {

			$enable_id             = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_id'
				) );
			$enable_slug             = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_slug'
				) );
			$enable_parent    = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_parent'
				) );
			$enable_alias     = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_alias'
				) );

			/**
			 * Field Type: Template Settings
			 */
			$field_types = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'_field_type'
			));
			
			$parent_type = isset($field_types['parent_type']) ? $field_types['parent_type'] : 'id';
			?>
			<div class="jci-group-settings jci-group-section" data-section-id="settings">
				<div id="jci_taxonomy_enable_fields">
					<h4>Fields:</h4>
					<?php
					echo JCI_FormHelper::checkbox( 'template_settings[enable_id]', array(
							'label'   => 'Enable Id Field',
							'checked' => $enable_id
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_slug]', array(
							'label'   => 'Enable Slug Field',
							'checked' => $enable_slug
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_parent]', array(
							'label'   => 'Enable Parent Field',
							'checked' => $enable_parent,
							'after' => JCI_FormHelper::select('parent_type', array('label' => ', Using the Value', 'default' => $parent_type , 'options' => array('id' => 'ID', 'slug' => 'Slug', 'name' => 'Name')))
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_alias]', array(
							'label'   => 'Enable Alias Field',
							'checked' => $enable_alias
						) );
					?>
				</div>
			</div>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {

					// show/hide input fields
					$.fn.jci_enableField('enable_id', 'taxonomy-term_id');
					$.fn.jci_enableField('enable_slug', 'taxonomy-slug');
					$.fn.jci_enableField('enable_parent', 'taxonomy-parent');
					$.fn.jci_enableField('enable_alias', 'taxonomy-alias_of');

					// show select for post_author
					$.fn.jci_enableField('enable_parent', '#jc-importer_parent_type');
				});
			</script>
		<?php
		}
	}

	public function before_template_save( $data, $current_row ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$id = $jcimporter->importer->ID;

		$this->enable_id             = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_id'
			) );
		$this->enable_slug    = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_slug'
			) );
		$this->enable_parent    = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_parent'
			) );
		$this->enable_alias    = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_alias'
			) );
	}

	public function save_template( $id ) {

		$template = ImporterModel::getImportSettings( $id, 'template' );
		if ( $template == $this->_name ) {

			// get template settings
			$enable_id             = isset( $_POST['jc-importer_template_settings']['enable_id'] ) ? $_POST['jc-importer_template_settings']['enable_id'] : 0;
			$enable_slug    = isset( $_POST['jc-importer_template_settings']['enable_slug'] ) ? $_POST['jc-importer_template_settings']['enable_slug'] : 0;
			$enable_parent    = isset( $_POST['jc-importer_template_settings']['enable_parent'] ) ? $_POST['jc-importer_template_settings']['enable_parent'] : 0;
			$enable_alias    = isset( $_POST['jc-importer_template_settings']['enable_alias'] ) ? $_POST['jc-importer_template_settings']['enable_alias'] : 0;

			// update template settings
			ImporterModel::setImporterMeta( $id, array( '_template_settings', 'enable_id' ), $enable_id );
			ImporterModel::setImporterMeta( $id, array( '_template_settings', 'enable_slug' ), $enable_slug );
			ImporterModel::setImporterMeta( $id, array( '_template_settings', 'enable_parent' ), $enable_parent );
			ImporterModel::setImporterMeta( $id, array( '_template_settings', 'enable_alias' ), $enable_alias );
			

			// save field type if parent_type enabled
			$parent_type = $enable_parent ? $_POST['jc-importer_parent_type'] : false;
			ImporterModel::setImporterMeta( $id, array(
				'_template_settings',
				'_field_type',
				'parent_type'
			), $parent_type );
		}
	}

	/**
	 * Register Post Columns
	 *
	 * @param  array $columns
	 *
	 * @return array
	 */
	public function log_taxonomy_columns( $columns ) {

		$columns['term'] 	= 'Term';
		$columns['parent']  = 'Parent';
		$columns['slug'] 	= 'Slug';
		$columns['method']  = 'Method';

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
				
				echo $data['taxonomy']['term'];

				break;
			case 'parent':

				$parent = get_term_by( 'term_id', $data['taxonomy']['parent'], $data['taxonomy']['taxonomy']);
				if($parent){
					echo $parent->name;
				}else{
					echo 'N/A';
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
	$templates['taxonomy'] = 'JC_Tax_Template';

	return $templates;
}