<?php

/**
 * JCI Importer Class
 *
 * Class to load importers, parsing settings automatically.
 */
class JC_Importer_Core {

	public $addon_settings = array();
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
	protected $attachments = array(); // upload|remote
	protected $import_type; // post|user|table|virtual
	protected $template_type;
	/**
	 * @var JC_Importer_Template
	 */
	protected $template;
	protected $template_name;
	protected $taxonomies = array();
	protected $taxonomies_permissions = array();
	protected $groups = array();
	protected $start_line = 0;
	protected $row_count = 0;
	protected $record_import_count = 10;
	protected $total_rows = - 1;
	protected $last_import_row = 0;
	protected $name = '';
	protected $version = 0;
	protected $object_delete = - 1;

	public function __construct( $id = 0 ) {

		if ( intval( $id ) > 0 ) {

			// escape if importer already loaded
			if ( intval( $id ) == $this->ID ) {
				return true;
			}

			// clear ImporterModel Cache
			ImporterModel::clearImportSettings();

			$importer = ImporterModel::getImporter( $id );
			if ( ! $importer->have_posts() ) {
				return false;
			}

			$this->ID                  = $id;
			$this->name                = get_the_title( $this->ID );
			$this->file                = ImporterModel::getImportSettings( $id, 'import_file' );
			$this->permissions         = ImporterModel::getImportSettings( $id, 'permissions' );
			$this->attachments         = ImporterModel::getImporterMeta( $id, 'attachments' );
			$this->template_type       = ImporterModel::getImportSettings( $id, 'template_type' );
			$this->import_type         = ImporterModel::getImportSettings( $id, 'import_type' );
			$this->template_name       = ImporterModel::getImportSettings( $id, 'template' );
			$this->template            = get_import_template( $this->template_name );
			$this->start_line          = ImporterModel::getImportSettings( $id, 'start_line' );
			$this->row_count           = ImporterModel::getImportSettings( $id, 'row_count' );
			$this->record_import_count = ImporterModel::getImportSettings( $id, 'record_import_count' );

			// todo: throw error if template returns false, e.g. doesnt exist.
			if ( ! $this->template ) {
				return false;
			}

			do_action( 'jci/importer_template_loaded', $this );

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
				$data['group']   = $group;
				$output_fields   = array();
				$field_options   = array();
				$options_default = array();
				$titles          = array();
				$tooltips        = array();
				foreach ( $data['map'] as $id => $field_data ) {
					$output_fields[ $field_data['field'] ] = isset( $fields[ $data['group'] ][ $field_data['field'] ] ) ? $fields[ $data['group'] ][ $field_data['field'] ] : ''; // null; //$fields[$field_data['type']][$field_data['field']];
					$titles[ $field_data['field'] ]        = isset( $field_data['title'] ) ? $field_data['title'] : $field_data['field'];
					$tooltips[ $field_data['field'] ]      = isset( $field_data['tooltip'] ) ? $field_data['tooltip'] : sprintf( JCI()->text()->get( sprintf( 'template.default.%s', $field_data['field'] ) ), $data['import_type_name'] );

					if ( isset( $field_data['options'] ) ) {
						$field_options[ $field_data['field'] ] = $field_data['options'];
						if ( isset( $field_data['options_default'] ) ) {
							$options_default[ $field_data['field'] ] = $field_data['options_default'];
						} else {
							$options_default[ $field_data['field'] ] = null;
						}
					}
				}

				$this->groups[ $data['group'] ] = array(
					'type'                  => $data['field_type'],
					'fields'                => $output_fields,
					'field_options'         => $field_options,
					'field_options_default' => $options_default,
					'import_type'           => $data['import_type'],
					'titles'                => $titles,
					'tooltips'              => $tooltips,
					'import_type_name'      => $data['import_type_name'],
					'taxonomies'            => isset( $data['taxonomies'] ) ? $data['taxonomies'] : 0,
					'attachments'           => isset( $data['attachments'] ) ? $data['attachments'] : 0
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


			$delete_object_state = get_post_meta( $this->ID, '_jci_remove_complete_' . $this->version, true );
			if ( $delete_object_state !== false && $delete_object_state != '' ) {

				if ( $delete_object_state == 0 ) {
					$this->object_delete = 0;
				} else {
					$this->object_delete = 1;
				}
			} else {
				$this->object_delete = - 1;
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
	 * Run importer
	 *
	 * Main run importer function, Read and write status file while importing.
	 *
	 * TODO: on first run increase version number, at the moment not all status files line up with the correct version
	 *
	 * @param string $request_type
	 *
	 * @return mixed|void
	 */
	public function run( $request_type ) {

		// set current importer to this
		JCI()->importer = $this;

		IWP_Debug::timer( "Start", "core" );

		// Allow for other requests to run at the same time
		if ( session_status() == PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		register_shutdown_function( array( $this, 'on_server_timeout' ) );

		$lock   = IWP_Status::get_lock( $this->get_ID(), $this->get_version() );
		$status = IWP_Status::read_file( $this->get_ID(), $this->get_version() );

		if ( ! $lock ) {
			// we have write lock so we must have to import
			if ( $status ) {
				if ( 'error' === $status['status'] ) {
					return $this->do_error( $status );
				} else {
					return $this->do_success( $status );
				}
			}

			return $this->do_error( [ 'message' => 'No Status file found.' ] );
		}

		if(defined('IWP_DEBUG') && IWP_DEBUG === true){
			IWP_Debug::$_debug = true;
		}

		if ( $status ) {

			switch ( $status['status'] ) {
				case 'timeout':
				case 'running':
					$status['status'] = 'timeout';
					break;
				case 'started':
					$this->get_total_rows(true, true);
					break;
				default:
					if ( 'error' === $status['status'] ) {
						return $this->do_error( $status );
					} else {
						return $this->do_success( $status );
					}
					break;
			}
		}

		$start_row     = $this->get_start_line();
		$total_records = $this->get_total_rows();

		$max_records = $this->get_row_count();
        if ($max_records > $total_records) {
            $max_records = $total_records;
        }

		if ( $max_records > 0 ) {
			$total_records = $start_row + $max_records - 1;
		}
		$per_row = $this->get_record_import_count();

		if ( isset( $status['status'] ) && $status['status'] == 'timeout' ) {
			$start_row        = intval( $status['last_record'] ) + 1;
			$status['status'] = 'running';
			IWP_Status::write_file( $status, $this->get_ID(), $this->get_version() );
		}

		$rows = ceil( ( $total_records - ( $start_row - 1 ) ) / $per_row );

		IWP_Debug::timer( "Calculated Start / End Points", "core" );
		$this->_running = true;

		// Import Records
		for ( $i = 0; $i < $rows; $i ++ ) {
			IWP_Debug::timer( "Importing Chunk", "core" );
			$start = $start_row + ( $i * $per_row );
			$this->run_import( $start, false, $per_row );
			IWP_Debug::timer( "Imported Chunk", "core" );

			// we show timeout if more records need to be imported.
			if ( $i < $rows - 1 ) {
				$this->_running = false;

				return $this->on_server_timeout( true );
			}
		}

		$status           = IWP_Status::read_file( $this->get_ID(), $this->get_version() );
		$status['status'] = 'deleting';
		IWP_Status::write_file( $status, $this->get_ID(), $this->get_version() );

		IWP_Debug::timer( "Deleting Files", "core" );

		// TODO: Delete Records
		$this->on_import_complete();

		IWP_Debug::timer( "Deleted Files", "core" );


		$this->_running = false;

		$status           = IWP_Status::read_file( $this->get_ID(), $this->get_version() );
		$status['status'] = 'complete';
		IWP_Status::write_file( $status, $this->get_ID(), $this->get_version() );
		IWP_Debug::timer( "Complete", "core" );

		// display timer log
		IWP_Debug::timer_log( $this->get_version() . '-' . $this->get_last_import_row() );

		return $this->do_success( $status );
	}

	/**
	 * Remove objects which arn't being import
	 *
	 * @param  int $importer_id
	 *
	 * @return void
	 */
	public function on_import_complete( ) {

		$permissions = JCI()->importer->get_permissions();

		if ( $permissions['delete'] == 0 ) {
			return;
		}

		$groups   = JCI()->importer->get_template_groups();
		$version  = JCI()->importer->get_version();
		$template = JCI()->importer->get_template();


		// update status file to say deleting
		$status           = IWP_Status::read_file( JCI()->importer->get_ID(), JCI()->importer->get_version() );
		$status['status'] = 'deleting';
		$status['delete'] = isset($status['delete']) ? intval($status['delete']) : 0;
		IWP_Status::write_file( $status, JCI()->importer->get_ID(), JCI()->importer->get_version() );

		// load mapper
		if($this->template_name == 'user') {
			$mapper = new UserMapper( $this->template, $permissions );
		}elseif($this->template_name == 'taxonomy'){
			$mapper = new TaxMapper( $this->template, $permissions );
		}else{
			$mapper = new PostMapper( $this->template, $permissions );
		}

		// loop through all groups
		foreach ( $groups as $group => $args ) {

			// if mapper has remove function
			if ( method_exists( $mapper, 'remove_all_objects' ) ) {
				$mapper->remove_all_objects( JCI()->importer->get_ID(), $version );
			}
		}
	}

	public function get_ID() {
		return $this->ID;
	}

	public function get_version() {
		return $this->version;
	}

	public function set_version( $version ) {
		$this->version = intval( $version );
	}

	public function do_error( $data ) {
		if ( wp_doing_ajax() ) {
			wp_send_json_error( $data );
		} else {
			return $data;
		}
	}

	public function do_success( $data ) {
		if ( wp_doing_ajax() ) {
			wp_send_json_success( $data );
		} else {
			return $data;
		}
	}

	public function get_start_line() {
		return $this->start_line;
	}

	/**
	 * Get file record count
	 * @return int
	 */
	public function get_total_rows($skip_cache = false, $is_live = false) {
		$meta = get_post_meta( $this->get_ID(), sprintf( '_total_rows_%d', $this->get_version() ), true );
		if ( $skip_cache === true){ // || $this->total_rows === - 1 ) {

			$config_file = JCI()->get_tmp_config_path($this->get_ID());
			if(true === $is_live){
				$config_file = JCI()->get_tmp_dir() . DIRECTORY_SEPARATOR . sprintf('config-%d-%d.json', $this->get_ID(), $this->get_version());
			}

			$config = new \ImportWP\Importer\Config\Config($config_file);
			if($this->get_template_type() === 'csv'){
				$file = new \ImportWP\Importer\File\CSVFile($this->get_file(), $config);
				$this->total_rows = $file->getRecordCount();
			}else{
				$base = $this->addon_settings['import_base'];
				$file = new \ImportWP\Importer\File\XMLFile($this->get_file(), $config);
				$file->setRecordPath($base);
				$this->total_rows = $file->getRecordCount();
			}
			update_post_meta( $this->get_ID(), sprintf( '_total_rows_%d', $this->get_version() ), $this->total_rows );
		} else {
			$this->total_rows = intval( $meta );
		}
		return $this->total_rows;
	}

	public function get_row_count() {
		return $this->row_count;
	}

	public function get_record_import_count() {
		return $this->record_import_count;
	}

	public function on_record_complete(\ImportWP\Importer $importer){
		$status = IWP_Status::read_file();
		$counter = isset($status['counter']) ? intval($status['counter']) : 0;
		$error = isset($status['error']) ? intval($status['error']) : 0;
		$counter++;

		// write to status file
		IWP_Status::write_file( array(
			'status'      => 'running',
			'message'     => '',
			'counter'     => $counter,
			'last_record' => $importer->getRecordEnd(),
			'start'       => JCI()->importer->start_line,
			'end'         => JCI()->importer->total_rows,
			'error'      => $error,
			'time'        => time()
		) );
	}

	public function on_before_mapper(\ImportWP\Importer $importer, \ImportWP\Importer\ParsedData $data){
		$recordIndex = $importer->getParser()->getRecordIndex();

		$template_field_group = $this->template->get_template_group_id();
		$data = apply_filters( 'iwp/before_mapper_process', $data );

		// Call row save before group save
		do_action( 'jci/before_' . $this->template->get_name() . '_row_save', $data->getData(), $recordIndex );
		$updated_data = apply_filters( 'jci/before_' . $this->template->get_name() . '_group_save', $data->getData(),
			$template_field_group );

		// Update ParsedData with new values
		$data->replace($updated_data);
	}

	public function on_record_exception(\ImportWP\Importer $importer, $error_msg){
		$recordIndex = $importer->getParser()->getRecordIndex();
		ImportLog::insert(JCI()->importer->get_ID(), $recordIndex, array(
			'_jci_status' => 'E',
			'_jci_msg' => $error_msg
		));

		$status = IWP_Status::read_file();
		if($status === null){
			$status = array(
				'status'      => 'running',
				'message'     => '',
				'counter'     => 1,
				'last_record' => $importer->getRecordEnd(),
				'start'       => JCI()->importer->start_line,
				'end'         => JCI()->importer->total_rows,
				'error'       => 0,
				'time'        => time()
			);
		}
		$status['error']++;

		// write to status file
		IWP_Status::write_file( $status );
	}

	public function on_record_imported(\ImportWP\Importer $importer, \ImportWP\Importer\ParsedData $data){
		if($data === null){
			return;
		}

		$template_field_group = $this->template->get_template_group_id();
		$recordIndex          = $importer->getParser()->getRecordIndex();

		// Call row save after group save
		do_action( 'iwp_after_row_save', $this->template, $data, $importer );

		$output = array('_jci_status' => 'S');

		ImportLog::insert(JCI()->importer->get_ID(), $recordIndex, array_merge($output, $data->getLog()));
	}

	public function run_import( $row = null, $session = false, $per_row = 1 ) {

		IWP_Debug::timer('run_import::start');

		\ImportWP\Importer\EventHandler::instance()->listen('importer.record_complete', array($this, 'on_record_complete'));
		\ImportWP\Importer\EventHandler::instance()->listen('importer.before_mapper', array($this, 'on_before_mapper'));
		\ImportWP\Importer\EventHandler::instance()->listen('importer.record_exception', array($this, 'on_record_exception'));
		\ImportWP\Importer\EventHandler::instance()->listen('importer.record_imported', array($this, 'on_record_imported'));

		IWP_Debug::timer('run_import::register_listeners');

		do_action( 'jci/before_import' );

		IWP_Debug::timer('run_import::before_import');

		$groups = $this->get_template_groups();

		$config_file = JCI()->get_tmp_dir() . DIRECTORY_SEPARATOR . sprintf('config-%d-%d.json', $this->get_ID(), $this->get_version());
		$config_setup = !file_exists($config_file) ? true : false;
		$config = new \ImportWP\Importer\Config\Config($config_file);

		if($config_setup === true || !$config->get('fields')) {

			// TEMPLATE FIELDS
			$template_field_group = $this->template->get_template_group_id();

			$import_data = [
				[
					'fields' => $groups[ $template_field_group ]['fields']
				]
			];
			// END TEMPLATE FIELDS

			// TAX
			foreach ( $this->taxonomies as $group_id => $taxonomies ) {
				if ( ! empty( $taxonomies ) ) {
					array_push( $import_data, [
						'id'     => 'taxonomies',
						'fields' => $taxonomies
					] );
				}
			}
			// END TAX

			// ATTACHMENTS
			foreach ( $this->attachments as $group_id => $attachments ) {
				if ( ! empty( $attachments ) ) {
					array_push( $import_data, [
						'id'     => 'attachments',
						'fields' => $attachments
					] );
				}
			}
			// END ATTACHMENTS

			// CUSTOM FIELDS
			if ( isset( $groups[ $template_field_group ]['custom_fields'] ) ) {
				array_push( $import_data, [
					'id'     => 'custom_fields',
					'fields' => $groups[ $template_field_group ]['custom_fields']
				] );
			}
			// END CUSTOM FIELDS

			$config->set('data', $import_data);
		}

		// Get Start and End
		$info        = $this->get_import_info( $row, $per_row );
		$start = $info['start']-1;
		$end   = $info['end']-1;

		// load parser
		if($this->template_type === 'xml'){

			$file = new \ImportWP\Importer\File\XMLFile($this->file, $config);
			$base_node = $this->addon_settings['import_base'];
			$file->setRecordPath($base_node);
			$parser = new \ImportWP\Importer\Parser\XMLParser($file);

		}else{

			$csv_delimiter = ImporterModel::getImporterMetaArr( $this->ID, array(
				'_parser_settings',
				'csv_delimiter'
			) );
			$csv_enclosure = ImporterModel::getImporterMetaArr( $this->ID, array(
				'_parser_settings',
				'csv_enclosure'
			) );
			$csv_enclosure = stripslashes( $csv_enclosure );

			if ( empty( $csv_delimiter ) ) {
				$csv_delimiter = ',';
			}

			if ( empty( $csv_enclosure ) ) {
				$csv_enclosure = '"';
			}

			$file = new \ImportWP\Importer\File\CSVFile($this->file, $config);
			$file->setDelimiter($csv_delimiter);
			$file->setEnclosure($csv_enclosure);
			$parser = new \ImportWP\Importer\Parser\CSVParser($file);

		}

		$permissions = JCI()->importer->get_permissions();

		// load mapper
		if($this->template_name == 'user') {
			$mapper = new UserMapper( $this->template, $permissions );
		}elseif($this->template_name == 'taxonomy'){
			$mapper = new TaxMapper( $this->template, $permissions );
		}else{
			$mapper = new PostMapper( $this->template, $permissions );
		}

		$importer = new \ImportWP\Importer($config);
		$importer->from($start);
		$importer->to($end);
		$importer->parser($parser);
		$importer->mapper($mapper);
		$importer->import();

		do_action( 'jci/after_import' );

		\ImportWP\Importer\EventHandler::instance()->unlisten('importer.record_complete', array($this, 'on_record_complete'));
		\ImportWP\Importer\EventHandler::instance()->unlisten('importer.before_mapper', array($this, 'on_before_mapper'));
		\ImportWP\Importer\EventHandler::instance()->unlisten('importer.record_exception', array($this, 'on_record_exception'));
		\ImportWP\Importer\EventHandler::instance()->unlisten('importer.record_imported', array($this, 'on_record_imported'));

		unset($importer);
		unset($mapper);
		unset($parser);
		unset($file);
		unset($config);
	}

	/**
	 * Get importer start and end rows for current importer
	 *
	 * @param null $selected_row
	 * @param int $max_rows_limit
	 *
	 * @return array
	 */
	public function get_import_info( $selected_row = null, $max_rows_limit = 0 ) {

		// Calculate start row
		$start = $start_row = $this->get_start_line();
		if ( ! is_null( $selected_row ) ) {
			$start = $selected_row;
		}

		$end = $total_rows = $this->get_total_rows() + 1;

		// records per import
		$max_rows = $this->get_row_count();
		if ( $max_rows > 0 && $end > $start_row + $max_rows ) {
			$end = $start_row + $max_rows;
		}

		if ( $max_rows_limit > 0 && $start + $max_rows_limit < $end ) {
			$end = $start + $max_rows_limit;
		}

		$info = array(
			'start' => $start,
			'end'   => $end
		);

		return $info;
	}

	/**
	 * Triggered when server timeout occurs and the importer is still running
	 */
	public function on_server_timeout( $force = false ) {

		$error = error_get_last();
		if ( $error && $error['type'] === E_ERROR ) {
			$status_arr = IWP_Status::read_file( $this->get_ID(), $this->get_version() );
			$status_arr['status'] = 'error';
			$status_arr['message'] = $error['message'];
			IWP_Status::write_file( $status_arr );

			return $this->do_error( $status_arr );
		}

		// output timer log to file
		if ( ! $this->_running && false === $force ) {
			return;
		}

		IWP_Debug::timer_log( $this->get_ID() . '-' . $this->get_version() . '-' . $this->get_last_import_row() );

		$status           = IWP_Status::read_file( $this->get_ID(), $this->get_version() );
		$status['status'] = 'timeout';
		IWP_Status::write_file( $status, $this->get_ID(), $this->get_version() );

		return $this->do_success( $status );
	}

	public function get_last_import_row() {
		return $this->last_import_row;
	}

	public function increase_version() {
		$this->version ++;
		update_post_meta( $this->get_ID(), '_import_version', $this->get_version() );
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

	public function get_file() {
		return $this->file;
	}

	public function get_file_src() {
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

	public function get_object_delete() {
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
	public function get_addon_setting( $key, $default = '' ) {
		if ( isset( $this->addon_settings[ $key ] ) ) {
			return $this->addon_settings[ $key ];
		}

		return $default;
	}

	public function logError($error_msg){

	}
}
