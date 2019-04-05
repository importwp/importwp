<?php

/**
 * Core Admin Class
 */
class IWP_Admin {

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
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ) );

		// ajax import all at once with status file
		add_action( 'wp_ajax_jc_import_all', array( $this, 'admin_ajax_import_all_rows' ) );

		$this->setup_forms();
	}

	public function setup_forms() {

		// static validation rules
		// static validation rules
		$this->config->forms = array(
			'CreateImporter' => array(
				'validation' => array(
//					'name' => array(
//						'rule'    => array( 'required' ),
//						'message' => 'This Field is required'
//					),
					'template' => array(
						'rule'    => array( 'required' ),
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

	public function admin_enqueue_styles() {

		$screen = get_current_screen();
		if($screen->id !== 'toplevel_page_jci-importers' && $screen->id !== 'importwp_page_jci-settings'){
			return;
		}

		$ext     = '.min';
		$version = JCI()->get_version();
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			$version = time();
			$ext     = '';
		}

		wp_enqueue_style( 'jc-importer-style', trailingslashit( $this->config->get_plugin_url() ) . 'resources/css/style' . $ext . '.css', array(), $version );

		if(isset($_GET['action'])){
			switch($_GET['action']){
				case 'edit':

					$importer_id = intval($_GET['import']);
					$config_file = JCI()->get_tmp_config_path($importer_id);
					$config = new \ImportWP\Importer\Config\Config( $config_file );

					wp_enqueue_script('importer-edit', trailingslashit( $this->config->get_plugin_url() ) . 'resources/js/edit' . $ext . '.js', array('jquery'), $version);

					$settings = array(
						'processed' => 'no'
					);
					$processed = $config->get('processed');
					if(true === $processed){
						$settings['processed'] = 'yes';
					}
					wp_localize_script('importer-edit', 'iwp_settings', apply_filters('iwp/js/iwp_settings', $settings));
					break;
			}
		}
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
		add_submenu_page( 'jci-importers', 'Settings', 'Settings', 'manage_options', 'jci-settings', array(
			$this,
			'admin_settings_view'
		) );

		if ( ! class_exists( 'ImportWP_Pro' ) ) {
			add_submenu_page( 'jci-importers', 'Go Premium', 'Go Premium', 'manage_options', 'jci-settings&tab=premium', array(
				$this,
				'admin_premium_view'
			) );
		}
	}

	public function admin_imports_view() {
		require JCI()->get_plugin_dir() . 'resources/views/home.php';
	}

	public function admin_tools_view() {
		require JCI()->get_plugin_dir() . 'resources/views/tools.php';
	}

	public function admin_addons_view() {
		require JCI()->get_plugin_dir() . 'resources/views/addons.php';
	}

	public function admin_settings_view() {
		require JCI()->get_plugin_dir() . 'resources/views/settings.php';
	}

	public function admin_premium_view() {
		require JCI()->get_plugin_dir() . 'resources/views/premium.php';
	}

	public function process_forms() {

		IWP_FormBuilder::init( $this->config->forms );
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

		// if remote and fetch do that, else continue
		if ( $importer && $action == 'fetch' && in_array( JCI()->importer->get_import_type(), array(
				'remote',
				'local'
			) ) ) {

			if ( JCI()->importer->get_import_type() == 'remote' ) {
				// fetch remote file
				$remote_settings = IWP_Importer_Settings::getImportSettings( $importer, 'remote' );
				$url             = $remote_settings['remote_url'];
				$dest            = basename( $url );
				$attach          = new IWP_Attachment_CURL();
				$result          = $attach->attach_remote_file( $importer, $url, $dest, array(
					'importer-file' => true,
					'unique'        => true
				) );

				do_action('iwp/importer/file_uploaded', $result, $importer);

			} elseif ( JCI()->importer->get_import_type() == 'local' ) {
				// fetch local file
				$local_settings = IWP_Importer_Settings::getImportSettings( $importer, 'local' );
				$url            = $local_settings['local_url'];
				$dest           = basename( $url );
				$attach         = new IWP_Attachment_Local();
				$result         = $attach->attach_remote_file( $importer, $url, $dest, array(
					'importer-file' => true,
					'unique'        => true
				) );

				do_action('iwp/importer/file_uploaded', $result, $importer);
			}

			// update settings with new file
			IWP_Importer_Settings::setImporterMeta( $importer, array( '_import_settings', 'import_file' ), $result['id'] );

			// reload importer settings
			IWP_Importer_Settings::clearImportSettings();

			// redirect to importer run page
			JCI()->importer->increase_version();
			wp_redirect( 'admin.php?page=jci-importers&import=' . $importer . '&action=logs' );
			exit();
		}

		// if importer has complete status, then increase version
		if($importer && $action === 'start'){
			$status = IWP_Status::read_file();
			if($status['status'] === 'complete'){
				JCI()->importer->increase_version();
				wp_redirect( 'admin.php?page=jci-importers&import=' . $importer . '&action=logs' );
				exit();
			}
		}

		if ( $action == 'trash' && ( $importer || $template ) ) {

			if ( $importer ) {

				wp_delete_post( $importer );
			} elseif ( $template ) {

				wp_delete_post( $template );
			}

			wp_redirect( admin_url( 'admin.php?page=jci-importers&message=2&trash=1' ) );
			exit();
		}


		if ( $action == 'clear-logs' && ! isset( $_GET['result'] ) ) {

			// clear importer logs older than one day	
			IWP_Importer_Log::clearLogs();
			wp_redirect( add_query_arg( array( 'result' => 1 ) ) );
			exit();
		} elseif ( $action == 'update-db' && ! isset( $_GET['result'] ) ) {

			// TODO: Rollback migrations / decrease version number as otherwise this does nothing.
			require_once $jcimporter->get_plugin_dir() . '/libs/class-iwp-migrations.php';
			$migrations = new IWP_Migrations();
			$migrations->migrate();

			wp_redirect( add_query_arg( array( 'result' => 1 ) ) );
			exit();
		} elseif ( $action == 'clear-settings' && ! isset( $_GET['result'] ) ) {

			global $wpdb;
			$wpdb->query( "DELETE FROM " . $wpdb->postmeta . " WHERE meta_key LIKE '_import_settings_%' OR meta_key LIKE '_mapped_fields_%' OR meta_key LIKE '_attachments_%' OR meta_key LIKE '_taxonomies_%' OR meta_key LIKE '_parser_settings_%' OR meta_key LIKE '_template_settings_%' OR meta_key LIKE '_jci_last_row_%' " );
			wp_redirect( add_query_arg( array( 'result' => 1 ) ) );
			exit();
		}
	}

	/**
	 * Create Importer Form
	 * @return void
	 */
	public function process_import_create_from() {

		$errors = array();

		IWP_FormBuilder::process_form( 'CreateImporter' );
		if ( IWP_FormBuilder::is_complete() ) {

			// general importer fields
//			$name     = $_POST['jc-importer_name'];
			$name     = '';
			$template = $_POST['jc-importer_template'];

			$post_id = IWP_Importer_Settings::insertImporter( 0, array( 'name' => $name ) );
			$general = array();

			// @todo: fix upload so no file is uploaded unless it is the correct type, currently file is uploaded then removed.

			$file_error_field = 'import_file';
			$import_type      = $_POST['jc-importer_import_type'];
			switch ( $import_type ) {

				// file upload settings
				case 'upload':

					// upload
					$attach = new IWP_Attachment_Upload();
					$result = $attach->attach_upload( $post_id, $_FILES['jc-importer_import_file'], array( 'importer-file' => true ) );

					$result['attachment'] = $attach;
					break;

				// remote/curl settings
				case 'remote':

					// download
					$src                   = $_POST['jc-importer_remote_url'];
					$dest                  = basename( $src );
					$attach                = new IWP_Attachment_CURL();
					$result                = $attach->attach_remote_file( $post_id, $src, $dest, array( 'importer-file' => true ) );
					$general['remote_url'] = $src;
					$file_error_field      = 'remote_url';

					$result['attachment'] = $attach;
					break;
				case 'local':

					$src                  = wp_normalize_path( $_POST['jc-importer_local_url'] );
					$dest                 = basename( $src );
					$attach               = new IWP_Attachment_Local();
					$result               = $attach->attach_remote_file( $post_id, $src, $dest, array( 'importer-file' => true ) );
					$general['local_url'] = $src;
					$file_error_field     = 'local_url';
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
			if ( ! isset( $result['type'] ) || ! in_array( $result['type'], array( 'xml', 'csv' ) ) ) {
				$errors[] = 'Filetype not supported';
			}

			// escape and remove inserted importer if attach errors have happened
			// allow for hooked datasources to rollback on error, at the moment only
			if ( isset( $result['attachment'] ) && $result['attachment']->has_error() ) {
				$errors[] = $result['attachment']->get_error();
			}

			if ( ! empty( $errors ) ) {
				IWP_FormBuilder::$errors[ $file_error_field ] = array_pop( $errors );
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


				$post_id = IWP_Importer_Settings::insertImporter( $post_id, array(
					'name'     => sprintf( "Import %s from %s on %s ", apply_filters( 'jci/importer/template_name', $template ), $template_type, date( get_site_option( 'date_format' ) ) ),
					'settings' => array(
						'import_type'   => $import_type,
						'template'      => $template,
						'template_type' => $template_type,
						'import_file'   => $result['id'],
						'general'       => $general,
						'permissions'   => $permissions,
						'version' => JCI()->get_version()
					),
				) );

				do_action('iwp/importer/file_uploaded', $result, $post_id);

				wp_redirect( admin_url( 'admin.php?page=jci-importers&import=' . $post_id . '&action=edit&message=0' ) );
				exit();
			}
		}
	}

	/**
	 * Edit Importer Form
	 * @return void
	 */
	public function process_import_edit_from() {

		IWP_FormBuilder::process_form( 'EditImporter' );
		if ( IWP_FormBuilder::is_complete() ) {

			$id = intval( $_POST['jc-importer_import_id'] );

			// uploading a new file
			if ( isset( $_POST['jc-importer_upload_file'] ) && ! empty( $_POST['jc-importer_upload_file'] ) ) {

				$attach = new IWP_Attachment_Upload();
				$result = $attach->attach_upload( $id, $_FILES['jc-importer_import_file'], array( 'importer-file' => true ) );
				IWP_Importer_Settings::setImportFile( $id, $result );

				// increase version number
				// $version = get_post_meta( $id, '_import_version', true );
				// ImporterModel::setImportVersion($id, $version + 1);

				// Delete edit screen config file
				IWP_Importer_Settings::clear_edit_config($id);

				do_action('iwp/importer/file_uploaded', $result, $id);

				wp_redirect( admin_url( 'admin.php?page=jci-importers&import=' . $id . '&action=edit' ) );
				exit();
			}

			// save remote file
			if ( isset( $_POST['jc-importer_remote_url'] ) ) {

				if ( ! empty( $_POST['jc-importer_remote_url'] ) ) {

					// save remote url
					IWP_Importer_Settings::setImporterMeta( $id, array(
						'_import_settings',
						'general',
						'remote_url'
					), $_POST['jc-importer_remote_url'] );
				}
			}

			// save local file
			if ( isset( $_POST['jc-importer_local_url'] ) ) {

				if ( ! empty( $_POST['jc-importer_local_url'] ) ) {

					// save local url
					IWP_Importer_Settings::setImporterMeta( $id, array(
						'_import_settings',
						'general',
						'local_url'
					), wp_normalize_path( $_POST['jc-importer_local_url'] ) );
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
			if ( isset( $_POST['jc-importer_template-unique-field'] ) ) {
				$settings['template_unique_field'] = $_POST['jc-importer_template-unique-field'];
			}

			$settings = apply_filters( 'jci/process_edit_form', $settings );

			$fields      = isset( $_POST['jc-importer_field'] ) ? $_POST['jc-importer_field'] : array();
			$attachments = isset( $_POST['jc-importer_attachment'] ) ? $_POST['jc-importer_attachment'] : array();
			$taxonomies  = isset( $_POST['jc-importer_taxonomies'] ) ? $_POST['jc-importer_taxonomies'] : array();

			// load parser settings

			$template_type   = IWP_Importer_Settings::getImportSettings( $id, 'template_type' );

			// select file to use for import
			$selected_import_id = intval( $_POST['jc-importer_file_select'] );
			if ( $selected_import_id > 0 ) {

				// increase version number
				$version  = get_post_meta( $id, '_import_version', true );
				$last_row = IWP_Importer_Log::get_last_row( $id, $version );
				if ( $last_row > 0 ) {
					IWP_Importer_Settings::setImportVersion( $id, $version + 1 );
				}

				$settings['import_file'] = $selected_import_id;
			}

			$importer_name = isset( $_POST['jc-importer_name'] ) && ! empty( $_POST['jc-importer_name'] ) ? $_POST['jc-importer_name'] : false;

			$result = IWP_Importer_Settings::update( $id, array(
				'name'        => $importer_name,
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
				if ( in_array( $jcimporter->importer->get_import_type(), array( 'remote', 'local' ) ) ) {

					wp_redirect( admin_url( 'admin.php?page=jci-importers&import=' . $result . '&action=fetch' ) );
				} else {
					JCI()->importer->increase_version();
					wp_redirect( admin_url( 'admin.php?page=jci-importers&import=' . $result . '&action=logs' ) );
				}


			} else {
				wp_redirect( admin_url( 'admin.php?page=jci-importers&import=' . $result . '&action=edit&message=1' ) );
			}
			exit();
		}
	}

	public function admin_ajax_import_all_rows() {

		set_time_limit( 0 );

		$importer_id  = intval( $_POST['id'] );
		$request_type = isset( $_POST['request'] ) ? $_POST['request'] == 'run' : 'check';

		JCI()->importer = new IWP_Importer( $importer_id );
		JCI()->importer->run( $request_type );
	}
}

?>