<?php

/**
 * JCI Importer Class
 *
 * Class to load importers, parsing settings automatically.
 */
class JC_Importer_Core {

	/**
	 * Importer ID
	 * @var integer
	 */
	protected $ID = null;

	/**
	 * File Location
	 * @var string
	 */
	protected $file;

	/**
	 * Permissions array(create => 0, update => 0, delete => 0)
	 * @var array
	 */
	protected $permissions;
	protected $attachments = array();
	protected $import_type; // upload|remote
	protected $template_type; // post|user|table|virtial
	protected $template;
	protected $template_name;
	protected $taxonomies = array();
	protected $taxonomies_permissions = array();
	protected $groups = array();
	protected $start_line = 0;
	protected $row_count = 0;
	protected $record_import_count = 10;
	protected $total_rows = 0;
	protected $last_import_row = 0;
	protected $name = '';
	protected $version = 0;
	protected $object_delete = -1;

	public $addon_settings = array();

	public function __construct( $id = 0 ) {

		if ( intval( $id ) > 0 ) {

			// escape if importer already loaded
			if ( intval( $id ) == $this->ID ) {
				return true;
			}

			$importer = ImporterModel::getImporter( $id );
			if ( ! $importer->have_posts() ) {
				return false;
			}

			$this->ID            = $id;
			$this->name          = get_the_title( $this->ID );
			$this->file          = ImporterModel::getImportSettings( $id, 'import_file' );
			$this->permissions   = ImporterModel::getImportSettings( $id, 'permissions' );
			$this->attachments   = ImporterModel::getImporterMeta( $id, 'attachments' );
			$this->template_type = ImporterModel::getImportSettings( $id, 'template_type' );
			$this->import_type   = ImporterModel::getImportSettings( $id, 'import_type' );
			$this->template_name = ImporterModel::getImportSettings( $id, 'template' );
			$this->template      = get_import_template( $this->template_name );
			$this->start_line    = ImporterModel::getImportSettings( $id, 'start_line' );
			$this->row_count     = ImporterModel::getImportSettings( $id, 'row_count' );
			$this->record_import_count = ImporterModel::getImportSettings( $id, 'record_import_count' );

			// todo: throw error if template returns false, e.g. doesnt exist.
			if(!$this->template){
				return false;
			}

			do_action('jci/importer_template_loaded', $this);

			// load taxonomies
			$taxonomies = ImporterModel::getImporterMeta( $id, 'taxonomies' );
			foreach ( $taxonomies as $group_id => $tax_arr ) {

				if ( ! isset( $tax_arr['tax'] ) ) {
					continue;
				}

				foreach ( $tax_arr['tax'] as $key => $tax ) {

					if ( ! isset( $tax_arr['term'][ $key ] ) ) {
						continue;
					}

					$this->taxonomies[ $group_id ][ $tax ][]           = (string) $tax_arr['term'][ $key ];
					$this->taxonomies_permissions[ $group_id ][ $tax ] = isset( $tax_arr['permissions'][ $key ] ) ? $tax_arr['permissions'][ $key ] : 'create';
				}
			}

			// load template fields
			$fields = ImporterModel::getImporterMeta( $id, 'fields' );
			foreach ( $this->template->_field_groups as $group => $data ) {

				// backwards comp
				$data['group'] = $group;
				$output_fields = array();
				$field_options = array();
				$options_default = array();
				$titles        = array();
				foreach ( $data['map'] as $id => $field_data ) {
					$output_fields[ $field_data['field'] ] = isset( $fields[ $data['group'] ][ $field_data['field'] ] ) ? $fields[ $data['group'] ][ $field_data['field'] ] : ''; // null; //$fields[$field_data['type']][$field_data['field']];
					$titles[ $field_data['field'] ]        = isset( $field_data['title'] ) ? $field_data['title'] : $field_data['field'];

					if(isset($field_data['options'])){
						$field_options[$field_data['field']] = $field_data['options'];	
						if(isset($field_data['options_default'])){
							$options_default[$field_data['field']] = $field_data['options_default'];
						}else{
							$options_default[$field_data['field']] = null;
						}
					}
				}

				$this->groups[ $data['group'] ] = array(
					'type'             => $data['field_type'],
					'fields'           => $output_fields,
					'field_options'    => $field_options,
					'field_options_default' => $options_default, 
					'import_type'      => $data['import_type'],
					'titles'           => $titles,
					'import_type_name' => $data['import_type_name'],
					'taxonomies'       => isset( $data['taxonomies'] ) ? $data['taxonomies'] : 0,
					'attachments'      => isset( $data['attachments'] ) ? $data['attachments'] : 0
				);
			}

			// check import history version
			$ver = get_post_meta( $this->ID, '_import_version', true );
			if ( intval( $ver ) > 0 ) {
				$this->version = intval( $ver );
			} else {
				$this->version = 0;
			}

			// get last imported record for latest version
			$last_row = ImportLog::get_last_row( $this->ID, $this->version );
			if ( intval( $last_row ) > 0 ) {
				$this->last_import_row = intval( $last_row );
			} else {
				$this->last_import_row = 0;
			}


			$delete_object_state = get_post_meta( $this->ID, '_jci_remove_complete_'.$this->version, true );
			if ( $delete_object_state !== false && $delete_object_state != '') {

				if($delete_object_state == 0){
					$this->object_delete =  0;
				}else{
					$this->object_delete =  1;
				}
			} else {
				$this->object_delete =  -1;
			}			
			
			// load parser specific settings
			$this->addon_settings = apply_filters( "jci/load_{$this->template_type}_settings", array(), $this->ID );
		}
	}

	public function __get( $key ) {

		$allowed_keys = array(
			'ID',
			'groups',
			'permissions',
			'taxonomies',
			'taxonomies_permissions',
			'import_type',
			'template_type',
			'file',
			'attachments',
			'addon_settings'
		);
		if ( in_array( $key, $allowed_keys ) ) {
			return $this->$key;
		}

		return null;
	}

	/**
	 * Run Data Imports
	 *
	 * @param  integer $import_id
	 * @param  integer $row Specific Row
	 *
	 * @return array Response
	 */
	public function run_import( $row = null, $session = false ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$importer_id = $jcimporter->importer->get_ID();
		$jci_file          = $jcimporter->importer->file;
		$jci_template      = $jcimporter->importer->template;
		$jci_template_type = $jcimporter->importer->template_type;
		$parser            = $jcimporter->parsers[ $this->template_type ];

		// use session if row > 0
		if($session){
			$parser->session = true;
		}

		// clear session if first record is being imported
		if($row == $jcimporter->importer->get_start_line()){
			$parser->clear_session();
		}

		do_action('jci/before_import');

		$mapper = new JC_BaseMapper();
		if( $parser->loadFile( $jci_file ) ){

			if ( $row ) {

				// ajax importer one record at a time

				$results = $parser->parse( intval( $row ) );

				// escape if row doesn't exist
				if ( ! $results ) {

					// check to see if last row
					$mapper->complete_check( intval( $row ) );

					// track row as import is skipped
					ImportLog::insert( $importer_id, $row, array() );
					// $mapper->track_import( $importer_id, $row, $jcimporter->importer->get_version() );

					return false;
				}

				$result = $mapper->process( $jci_template, $results, $row );
			} else {

				// import all at one, may take a while to complete
				
				$results = $parser->parse();
				$result  = $mapper->process( $jci_template, $results );
			}

			// check result
			if ( count( $results ) == count( $result ) ) {
				return $result;
			}

		}else{
			return array(
				array(
					'_jci_status' => 'E',
					'_jci_msg' => 'File could not be found: '.$this->file
				)
			);
		}

		return false;
	}

	public function get_ID() {
		return $this->ID;
	}

	public function get_parser() {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;

		return $jcimporter->parsers[ $this->template_type ];
	}

	public function get_permissions() {
		return $this->permissions;
	}

	public function get_template_name() {
		return $this->template_name;
	}

	/**
	 * Get Template Parser Type (XML/CSV)
	 *
	 * @return string
	 */
	public function get_template_type() {
		return $this->template_type;
	}

	public function get_import_type() {
		return $this->import_type;
	}

	public function get_template_groups() {

		return apply_filters( 'jci/importer/get_groups', $this->groups );
	}

	public function get_template() {
		return $this->template;
	}

	public function get_start_line() {
		return $this->start_line;
	}

	public function get_row_count() {
		return $this->row_count;
	}

	public function get_record_import_count(){
		return $this->record_import_count;
	}

	public function get_total_rows() {

		$parser = $this->get_parser();

		return $parser->get_total_rows();
	}

	public function get_file() {
		return $this->file;
	}

	public function get_file_src(){
		return $this->file;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_taxonomies() {
		return $this->taxonomies;
	}

	public function get_taxonomies_permissions() {
		return $this->taxonomies_permissions;
	}

	public function get_attachments() {
		return $this->attachments;
	}

	public function get_version() {
		return $this->version;
	}

	public function set_version( $version ) {
		$this->version = intval( $version );
	}

	public function get_last_import_row(){
		return $this->last_import_row;
	}

	public function get_object_delete(){
		return $this->object_delete;
	}

	/**
	 * Get Add-on Setting
	 *
	 * @param string $key Setting Key
	 * @param string $default Default
	 *
	 * @return string
	 */
	public function get_addon_setting($key, $default = ''){
		if(isset($this->addon_settings[$key])){
			return $this->addon_settings[$key];
		}

		return $default;
	}
}

?>