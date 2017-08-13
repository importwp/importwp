<?php

/**
 * Core Admin Class
 */
class JC_Importer_Admin {

	/**
	 * @var JC_Importer
	 */
	private $config;

	/**
	 * @var bool Is Importer Running
	 */
	private $_running = false;

	public function __construct( &$config ) {
		$this->config = $config;

		// add_action( 'admin_init', array($this, 'register_settings' ));
		add_action( 'admin_menu', array( $this, 'settings_menu' ) );
		add_action( 'wp_loaded', array( $this, 'process_forms' ) );
		add_action( 'admin_init', array( $this, 'admin_enqueue_styles' ) );

		// ajax import
		add_action( 'wp_ajax_jc_import_row', array( $this, 'admin_ajax_import_row' ) );
		add_action( 'wp_ajax_jc_process_delete' , array( $this , 'admin_ajax_process_delete' ) );

		// ajax import all at once with status file
		add_action( 'wp_ajax_jc_import_all', array( $this, 'admin_ajax_import_all_rows' ) );

		$this->setup_forms();
	}

	public function admin_enqueue_styles() {

		$ext = '.min';
		$version = JCI()->get_version();
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			$version = time();
			$ext = '';
		}

		wp_enqueue_style( 'jc-importer-style', trailingslashit($this->config->get_plugin_url()) . 'app/assets/css/style'.$ext.'.css', array(), $version );
	}

	public function settings_menu() {

		add_menu_page( 'jc-importer', 'ImportWP', 'manage_options', 'jci-importers', array(
			$this,
			'admin_imports_view'
		), 'dashicons-upload' );
		add_submenu_page( 'jci-importers', 'Importers', 'Importers', 'manage_options', 'jci-importers', array(
			$this,
			'admin_imports_view'
		) );
//		add_submenu_page( 'jci-importers', 'Addons', 'Addons', 'manage_options', 'jci-addons', array($this, 'admin_addons_view') );
		add_submenu_page( 'jci-importers', 'Add Importer', 'Add Importer', 'manage_options', 'jci-importers&action=add', array(
			$this,
			'admin_imports_view'
		) );
		add_submenu_page( 'jci-importers', 'Settings', 'Settings', 'manage_options', 'jci-settings', array($this, 'admin_settings_view') );

		if(!class_exists('ImportWP_Pro')){
			add_submenu_page( 'jci-importers', 'Go Premium', 'Go Premium', 'manage_options', 'jci-settings&tab=premium', array($this, 'admin_premium_view') );
		}
	}

	public function admin_imports_view() {
		require 'view/home.php';
	}

	public function admin_tools_view(){
		require 'view/tools.php';
	}

	public function admin_addons_view(){
		require 'view/addons.php';
	}

	public function admin_settings_view() {
		require 'view/settings.php';
	}

	public function admin_premium_view(){
		require 'view/premium.php';
	}

	public function setup_forms() {

		// static validation rules
		$this->config->forms = array(
			'CreateImporter' => array(
				'validation' => array(
//					'name' => array(
//						'rule'    => array( 'required' ),
//						'message' => 'This Field is required'
//					),
					'template' => array(
						'rule' => array('required'),
						'message' => 'Please make sure you have selected a template'
					)
				)
			),
			'EditImporter'   => array()
		);

		// dynamic validation rules
		if ( isset( $_POST['jc-importer_form_action'] ) && $_POST['jc-importer_form_action'] == 'CreateImporter' ) {

			// set extra validation rules based on the select
			switch ( $_POST['jc-importer_import_type'] ) {

				// file upload settings
				case 'upload':

					$this->config->forms['CreateImporter']['validation']['import_file'] = array(
						'rule'    => array( 'required' ),
						'message' => 'Please select the file you want to import from',
						'type'    => 'file'
					);
					break;

				// remote/curl settings
				case 'remote':

					$this->config->forms['CreateImporter']['validation']['remote_url'] = array(
						'rule'    => array( 'required' ),
						'message' => 'Please enter the url of the file you want to import from'
					);
					break;

			}

			$this->config->forms = apply_filters( 'jci/setup_forms', $this->config->forms, $_POST['jc-importer_import_type'] );
		}
	}

	public function process_forms() {

		JCI_FormHelper::init( $this->config->forms );
		if ( isset( $_POST['jc-importer_form_action'] ) ) {

			switch ( $_POST['jc-importer_form_action'] ) {
				case 'CreateImporter':
					$this->process_import_create_from();
					break;
				case 'EditImporter':
					$this->process_import_edit_from();
					break;
			}

		}

		// trash importers
		$action   = isset( $_GET['action'] ) && ! empty( $_GET['action'] ) ? $_GET['action'] : 'index';
		$importer = isset( $_GET['import'] ) && intval( $_GET['import'] ) > 0 ? intval( $_GET['import'] ) : false;
		$template = isset( $_GET['template'] ) && intval( $_GET['template'] ) > 0 ? intval( $_GET['template'] ) : false;

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;

		// if remote and fetch do that, else continue
		if ( $importer && $action == 'fetch' && in_array($jcimporter->importer->get_import_type(), array('remote', 'local')) ) {

			if($jcimporter->importer->get_import_type() == 'remote'){
				// fetch remote file
				$remote_settings = ImporterModel::getImportSettings( $importer, 'remote' );
				$url             = $remote_settings['remote_url'];
				$dest            = basename( $url );
				$attach          = new JCI_CURL_Attachments();
				$result          = $attach->attach_remote_file( $importer, $url, $dest, array('importer-file' => true, 'unique' => true) );
			}elseif($jcimporter->importer->get_import_type() == 'local'){
				// fetch local file
				$local_settings = ImporterModel::getImportSettings( $importer, 'local' );
				$url             = $local_settings['local_url'];
				$dest            = basename( $url );
				$attach = new JCI_Local_Attachments();
				$result = $attach->attach_remote_file($importer, $url, $dest, array('importer-file' => true, 'unique' => true));
			}

			// todo: save import frequency, setup cron

			// update settings with new file
			ImporterModel::setImporterMeta( $importer, array( '_import_settings', 'import_file' ), $result['id'] );

			// reload importer settings
			ImporterModel::clearImportSettings();

			// redirect to importer run page
			wp_redirect( 'admin.php?page=jci-importers&import=' . $importer . '&action=logs' );
			exit();
		}

		if ( $action == 'trash' && ( $importer || $template ) ) {

			if ( $importer ) {

				wp_delete_post( $importer );
			} elseif ( $template ) {

				wp_delete_post( $template );
			}

			wp_redirect( admin_url('admin.php?page=jci-importers&message=2&trash=1' ));
			exit();
		}

		
		if($action == 'clear-logs' && !isset($_GET['result'])){

			// clear importer logs older than one day	
			ImportLog::clearLogs();
			wp_redirect( add_query_arg( array( 'result' => 1) ) );
			exit();
		}elseif($action == 'update-db' && !isset($_GET['result'])){

			require_once  $jcimporter->get_plugin_dir() . '/app/models/schema.php';
			$schema = new JCI_DB_Schema($jcimporter );
			$schema->db_migration();

			wp_redirect( add_query_arg( array( 'result' => 1) ) );
			exit();
		}elseif($action == 'clear-settings' && !isset($_GET['result'])){

			global $wpdb;
			$wpdb->query("DELETE FROM " . $wpdb->postmeta . " WHERE meta_key LIKE '_import_settings_%' OR meta_key LIKE '_mapped_fields_%' OR meta_key LIKE '_attachments_%' OR meta_key LIKE '_taxonomies_%' OR meta_key LIKE '_parser_settings_%' OR meta_key LIKE '_template_settings_%' OR meta_key LIKE '_jci_last_row_%' ");
			wp_redirect( add_query_arg( array( 'result' => 1) ) );
			exit();
		}
	}

	/**
	 * Create Importer Form
	 * @return void
	 */
	public function process_import_create_from() {

		$errors = array();

		JCI_FormHelper::process_form( 'CreateImporter' );
		if ( JCI_FormHelper::is_complete() ) {

			// general importer fields
//			$name     = $_POST['jc-importer_name'];
			$name = '';
			$template = $_POST['jc-importer_template'];

			$post_id = ImporterModel::insertImporter( 0, array( 'name' => $name ) );
			$general = array();

			// @todo: fix upload so no file is uploaded unless it is the correct type, currently file is uploaded then removed.

			$file_error_field = 'import_file';
			$import_type = $_POST['jc-importer_import_type'];
			switch ( $import_type ) {

				// file upload settings
				case 'upload':

					// upload
					$attach = new JCI_Upload_Attachments();
					$result = $attach->attach_upload( $post_id, $_FILES['jc-importer_import_file'], array('importer-file' => true) );

					// todo: replace the result
					$result['attachment'] = $attach;
					break;

				// remote/curl settings
				case 'remote':

					// download
					$src                   = $_POST['jc-importer_remote_url'];
					$dest                  = basename( $src );
					$attach                = new JCI_CURL_Attachments();
					$result                = $attach->attach_remote_file( $post_id, $src, $dest, array('importer-file' => true) );
					$general['remote_url'] = $src;
					$file_error_field = 'remote_url';

					// todo: replace the result
					$result['attachment'] = $attach;
					break;
				case 'local':

					$src = wp_normalize_path($_POST['jc-importer_local_url']);
					$dest = basename( $src );
					$attach = new JCI_Local_Attachments();
					$result = $attach->attach_remote_file($post_id, $src, $dest, array('importer-file' => true));
					$general['local_url'] = $src;
					$file_error_field = 'local_url';
					$result['attachment'] = $attach;

					break;

				// no attachment
				default:
					$result = false;
					break;
			}

			$general = apply_filters( 'jci/process_create_form', $general, $import_type, $post_id );
			$result  = apply_filters( 'jci/process_create_file', $result, $import_type, $post_id );

			// restrict file attached filetype
			if ( !isset($result['type']) || ! in_array( $result['type'], array( 'xml', 'csv' ) ) ) {
				// todo delete import file
				$errors[] = 'Filetype not supported';
			}

			// escape and remove inserted importer if attach errors have happened
			// todo: allow for hooked datasources to rollback on error, at the moment only
			if ( isset( $result['attachment'] ) && $result['attachment']->has_error() ) {
				$errors[] = $result['attachment']->get_error();
			}

			if ( ! empty( $errors ) ) {
				JCI_FormHelper::$errors[$file_error_field] = array_pop($errors);
				wp_delete_post( $post_id, true );

				return;
			}

			// process results
			if ( $result && is_array( $result ) ) {

				// catch and setup permissions
				$permissions = array(
					'create' => 1,
					'update' => 1,
					'delete' => 1
				);

				$template_type = $result['type'];


				$post_id = ImporterModel::insertImporter( $post_id, array(
					'name'     => sprintf("Import %s from %s on %s ", apply_filters('jci/importer/template_name',$template), $template_type, date(get_site_option('date_format'))),
					'settings' => array(
						'import_type'   => $import_type,
						'template'      => $template,
						'template_type' => $template_type,
						'import_file'   => $result['id'],
						'general'       => $general,
						'permissions' => $permissions
					),
				) );

				wp_redirect( admin_url('admin.php?page=jci-importers&import=' . $post_id . '&action=edit&message=0' ));
				exit();
			}
		}
	}

	/**
	 * Edit Importer Form
	 * @return void
	 */
	public function process_import_edit_from() {

		JCI_FormHelper::process_form( 'EditImporter' );
		if ( JCI_FormHelper::is_complete() ) {

			$id = intval( $_POST['jc-importer_import_id'] );

			// uploading a new file
			if ( isset( $_POST['jc-importer_upload_file'] ) && ! empty( $_POST['jc-importer_upload_file'] ) ) {

				$attach = new JCI_Upload_Attachments();
				$result = $attach->attach_upload( $id, $_FILES['jc-importer_import_file'], array('importer-file' => true) );
				ImporterModel::setImportFile( $id, $result );

				// increase version number
				// $version = get_post_meta( $id, '_import_version', true );
				// ImporterModel::setImportVersion($id, $version + 1);

				wp_redirect(admin_url('admin.php?page=jci-importers&import=' . $id . '&action=edit'));
				exit();
			}

			// save remote file
			if(isset($_POST['jc-importer_remote_url'])){

				if(!empty($_POST['jc-importer_remote_url'])){

					// save remote url
					ImporterModel::setImporterMeta($id, array('_import_settings', 'general', 'remote_url'), $_POST['jc-importer_remote_url']);
				}
			}

			// save local file
			if(isset($_POST['jc-importer_local_url'])){

				if(!empty($_POST['jc-importer_local_url'])){

					// save local url
					ImporterModel::setImporterMeta($id, array('_import_settings', 'general', 'local_url'), wp_normalize_path($_POST['jc-importer_local_url']));
				}
			}

			if ( isset( $_POST['jc-importer_permissions'] ) ) {
				$settings['permissions'] = $_POST['jc-importer_permissions'];
			}
			if ( isset( $_POST['jc-importer_start-line'] ) ) {
				$settings['start_line'] = $_POST['jc-importer_start-line'];
			}
			if ( isset( $_POST['jc-importer_row-count'] ) ) {
				$settings['row_count'] = $_POST['jc-importer_row-count'];
			}
			if ( isset( $_POST['jc-importer_record-import-count'] ) ) {
				$settings['record_import_count'] = $_POST['jc-importer_record-import-count'];
			}

			$settings = apply_filters( 'jci/process_edit_form', $settings );
			
			$fields      = isset( $_POST['jc-importer_field'] ) ? $_POST['jc-importer_field'] : array();
			$attachments = isset( $_POST['jc-importer_attachment'] ) ? $_POST['jc-importer_attachment'] : array();
			$taxonomies  = isset( $_POST['jc-importer_taxonomies'] ) ? $_POST['jc-importer_taxonomies'] : array();

			// load parser settings

			$template_type   = ImporterModel::getImportSettings( $id, 'template_type' );
			$this->_parser   = load_import_parser( $id );
			$parser_settings = apply_filters( 'jci/register_' . $template_type . '_addon_settings', array(
					'general' => array(),
					'group'   => array()
				) );

			// select file to use for import
			$selected_import_id = intval( $_POST['jc-importer_file_select'] );
			// $attachment_check   = new WP_Query( array(
			// 		'post_type'   => 'jc-import-files',
			// 		'post_parent' => $id,
			// 		'post_status' => 'any',
			// 		'p'           => $selected_import_id
			// 	) );
			if ( /*$attachment_check->post_count == 1*/ $selected_import_id > 0 ) {

				// increase version number
				$version = get_post_meta( $id, '_import_version', true );
				$last_row = ImportLog::get_last_row( $id, $version );
				if($last_row > 0){
					ImporterModel::setImportVersion($id, $version + 1);
				}	

				$settings['import_file'] = $selected_import_id;
			}

			$result = ImporterModel::update( $id, array(
				'fields'      => $fields,
				'attachments' => $attachments,
				'taxonomies'  => $taxonomies,
				'settings'    => $settings
			) );

			do_action( 'jci/save_template', $id, $template_type );

			if ( isset( $_POST['jc-importer_btn-continue'] ) ) {

				/**
				 * @global JC_Importer $jcimporter
				 */
				global $jcimporter;
				if(in_array($jcimporter->importer->get_import_type(), array('remote', 'local'))){

					wp_redirect( admin_url('admin.php?page=jci-importers&import=' . $result . '&action=fetch' ));
				}else{

					wp_redirect( admin_url('admin.php?page=jci-importers&import=' . $result . '&action=logs' ));
				}

				
			} else {
				wp_redirect( admin_url('admin.php?page=jci-importers&import=' . $result . '&action=edit&message=1' ));
			}
			exit();
		}
	}

	public function admin_ajax_import_all_rows(){

		// Allow for other requests to run at the same time
		if(session_status() == PHP_SESSION_ACTIVE){
			session_write_close();
		}

		set_time_limit(30);
		register_shutdown_function(array($this, 'on_server_timeout'));

		// get and load importer
		$importer_id = intval( $_POST['id'] );
		$request_type = isset($_POST['request']) ? $_POST['request'] == 'run' : 'check';

		JCI()->importer = new JC_Importer_Core( $importer_id);

		// ---
		// if we are no
		if($request_type != 'run'){
			$status = IWP_Status::read_file();
			if($status) {
				echo json_encode($status, true);
			}
			die();
		}
		// ---

		$status = IWP_Status::read_file();
		if($status){

			switch($status['status']){
				case 'timeout':
					// we are resuming a timeout
					break;
				default:
					echo json_encode($status, true);
					die();
					break;
			}
		}else{
			$status = array('status' => 'started');
			IWP_Status::write_file($status, null, JCI()->importer->get_version() + 1);
		}

		$start_row = JCI()->importer->get_start_line();
		$total_records = JCI()->importer->get_total_rows();

		$max_records = JCI()->importer->get_row_count();
		if($max_records > 0){
			$total_records = $start_row + $max_records -1;
		}
		$per_row = JCI()->importer->get_record_import_count();

		if(isset($status['status']) && $status['status'] == 'timeout'){
			$start_row = intval($status['last_record']) + 2;
			$status['status'] = 'running';
			IWP_Status::write_file($status);
		}

		$rows = ceil(( $total_records - ($start_row-1) ) / $per_row);
		$this->_running = true;

		// Import Records
		for($i = 0; $i < $rows; $i++){
			$start = $start_row + ($i * $per_row);
			JCI()->importer->run_import($start, false, $per_row);
		}

		$status = IWP_Status::read_file();
		$status['status'] = 'deleting';
		IWP_Status::write_file($status);

		// TODO: Delete Records
		$mapper = new JC_BaseMapper();
		$mapper->on_import_complete($importer_id, false);


		$this->_running = false;

//		$status = IWP_Status::read_file();
		$status['status'] = 'complete';
		IWP_Status::write_file($status);
		echo json_encode($status, true);
		die();

	}

	/**
	 * Triggered when server timeout occurs and the importer is still running
	 */
	public function on_server_timeout(){

		if(!$this->_running){
			return;
		}

		$status = IWP_Status::read_file();
		$status['status'] = 'timeout';
		IWP_Status::write_file($status);
	}

	/**
	 * Process Import Ajax
	 * @return HTML
	 */
	public function admin_ajax_import_row() {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;

		set_time_limit(0);

		$current_row         = intval( $_POST['row'] );
		$importer_id = intval( $_POST['id'] );
		$records = intval($_POST['records']);
		$output = array();

		if($records == 0){
			$records = 1;
		}

		$jcimporter->importer = new JC_Importer_Core( $importer_id );

		// fetch import limit
		$start_record = $jcimporter->importer->get_start_line();
		$last_record = 0;
		$max_records = $jcimporter->importer->get_row_count();

		$total_records = $jcimporter->importer->get_total_rows();

		$res = $jcimporter->importer->run_import( $current_row, true, $records );
		$counter = 0;
		foreach($res as $data_arr){
			$row = $current_row + $counter;
			$data = array($data_arr);
			ob_start();
			require $jcimporter->get_plugin_dir() . 'app/view/imports/log/log_table_record.php';
			$output[] = ob_get_clean();
			$counter++;
		}


//		for($i = 0; $i < $records; $i++){
//
//			$row = $current_row + $i;
//
//			// escape if max record has been met
//			if($max_records > 0){
//				$last_record = $start_record + $max_records;
//				if($row >= $last_record){
//					break;
//				}
//			}
//
//			// stop bulk import passing limit
//			if($row > $total_records){
//				break;
//			}
//
//			$data = $jcimporter->importer->run_import( $row, true, 1 );
//			ob_start();
//			require $jcimporter->get_plugin_dir() . 'app/view/imports/log/log_table_record.php';
//			$output[] = ob_get_clean();
//		}

		// reverse array to follow existing import record order
		$output = array_reverse($output);
		foreach($output as $x){
			echo $x;
		}
		die();
	}

	/**
	 * Process Ajax Deletion of missing records from tracked import
	 * @return json
	 */
	public function admin_ajax_process_delete(){

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$importer_id = intval( $_POST['id'] );
		$delete = isset($_POST['delete']) && $_POST['delete'] == 1 ? true : false;

		$mapper = new JC_BaseMapper();
		$jcimporter->importer = new JC_Importer_Core( $importer_id );
		
		

		if(!$delete){

			// return info about objects to delete
			$objects = apply_filters( 'jci/import_removal_check', array(), $importer_id );
			echo json_encode(array(
				'status' => 'S',
				'response' => array(
					'total' => count($objects)
				),
				'msg' => ''
			));

		}else{

			$out = $mapper->remove_single_object( $importer_id );
			echo json_encode(array(
				'status' => 'S',
				'response' => $out,
				'msg' => ''
			));
		}
		
		die();
	}
}

?>