<?php

/**
 * Core Admin Class
 */
class JC_Importer_Admin {

	private $config = null;

	public function __construct( &$config ) {
		$this->config = $config;

		// add_action( 'admin_init', array($this, 'register_settings' ));
		add_action( 'admin_menu', array( $this, 'settings_menu' ) );

		add_action( 'wp_loaded', array( $this, 'process_forms' ) );

		add_action( 'admin_init', array( $this, 'admin_enqueue_styles' ) );

		// ajax import
		add_action( 'wp_ajax_jc_import_row', array( $this, 'admin_ajax_import_row' ) );
		add_action( 'wp_ajax_jc_process_delete' , array( $this , 'admin_ajax_process_delete' ) );

		$this->setup_forms();
	}

	public function admin_enqueue_styles() {
		wp_enqueue_style( 'jc-importer-style', $this->config->plugin_url . '/app/assets/css/style.css' );
	}

	public function settings_menu() {

		add_menu_page( 'jc-importer', 'JC Importer', 'manage_options', 'jci-importers', array(
				$this,
				'admin_imports_view'
			) );
		add_submenu_page( 'jci-importers', 'Importers', 'Importers', 'manage_options', 'jci-importers', array(
				$this,
				'admin_imports_view'
			) );
		add_submenu_page( 'jci-importers', 'Add New', 'Add New', 'manage_options', 'jci-importers&action=add', array(
				$this,
				'admin_imports_view'
			) );
		// add_submenu_page( 'jci-importers', 'Templates', 'Templates', 'manage_options', 'jci-templates', array($this, 'admin_templates_view') );
		// add_submenu_page( 'jci-importers', 'Addons', 'Addons', 'manage_options', 'jci-addons', array($this, 'admin_addons_view') );
		// add_submenu_page( 'jci-importers', 'Settings', 'Settings', 'manage_options', 'jci-settings', array($this, 'admin_settings_view') );
	}

	public function admin_imports_view() {
		require 'view/home.php';
	}

	public function admin_templates_view() {
		require 'view/templates.php';
	}

	public function admin_addons_view() {
		require 'view/addons.php';
	}

	public function admin_settings_view() {
		require 'view/settings.php';
	}

	public function setup_forms() {

		// static validation rules
		$this->config->forms = array(
			'CreateImporter' => array(
				'validation' => array(
					'name' => array(
						'rule'    => array( 'required' ),
						'message' => 'This Field is required'
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
						'message' => 'This Field is required',
						'type'    => 'file'
					);
					break;

				// remote/curl settings
				case 'remote':

					$this->config->forms['CreateImporter']['validation']['remote_url'] = array(
						'rule'    => array( 'required' ),
						'message' => 'This Field is required'
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

		global $jcimporter;

		// if remote and fetch do that, else continue
		if ( $importer && $action == 'fetch' && $jcimporter->importer->get_import_type() == 'remote' ) {

			// fetch remote file
			$remote_settings = ImporterModel::getImportSettings( $importer, 'remote' );
			$url             = $remote_settings['remote_url'];
			$dest            = basename( $url );
			$attach          = new JC_CURL_Attachments();
			$result          = $attach->attach_remote_file( $importer, $url, $dest );

			// update settings with new file
			ImporterModel::setImporterMeta( $importer, array( '_import_settings', 'import_file' ), $result['id'] );

			// reload importer settings
			ImporterModel::clearImportSettings();

			// run import
			$jcimporter->importer = new JC_Importer_Core( $importer );
			if ( $import_result = $jcimporter->importer->run_import() ) {

				// import successful
				do_action( 'jci/after_import_run', $import_result );
			}

			wp_redirect( 'admin.php?page=jci-importers&import=' . $importer . '&action=edit' );
			exit();
		}

		if ( $action == 'trash' && ( $importer || $template ) ) {

			if ( $importer ) {

				wp_delete_post( $importer );
			} elseif ( $template ) {

				wp_delete_post( $template );
			}

			wp_redirect( '/wp-admin/admin.php?page=jci-importers&message=1&trash=1' );
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
			$name     = $_POST['jc-importer_name'];
			$template = $_POST['jc-importer_template'];

			$post_id = ImporterModel::insertImporter( 0, array( 'name' => $name ) );
			$general = array();

			// @todo: fix upload so no file is uploaded unless it is the correct type, currently file is uploaded then removed.

			$import_type = $_POST['jc-importer_import_type'];
			switch ( $import_type ) {

				// file upload settings
				case 'upload':

					// upload
					$attach = new JC_Upload_Attachments();
					$result = $attach->attach_upload( $post_id, $_FILES['jc-importer_import_file'] );

					// todo: replace the result
					$result['attachment'] = $attach;
					break;

				// remote/curl settings
				case 'remote':

					// download
					$src                   = $_POST['jc-importer_remote_url'];
					$dest                  = basename( $src );
					$attach                = new JC_CURL_Attachments();
					$result                = $attach->attach_remote_file( $post_id, $src, $dest );
					$general['remote_url'] = $src;

					// todo: replace the result
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
			if ( ! in_array( $result['type'], array( 'xml', 'csv' ) ) ) {
				@wp_delete_attachment( $result['id'], true );
				$errors[] = 'Filetype not supported';
			}

			// escape and remove inserted importer if attach errors have happened
			// todo: allow for hooked datasources to rollback on error, at the moment only
			if ( isset( $result['attachment'] ) && $result['attachment']->has_error() ) {
				$errors[] = $result['attachment']->get_error();
			}

			if ( ! empty( $errors ) ) {
				JCI_FormHelper::$errors['import_file'] = array_pop( $errors );
				wp_delete_post( $post_id, true );

				return;
			}

			// process results
			if ( $result && is_array( $result ) ) {

				$post_id = ImporterModel::insertImporter( $post_id, array(
					'name'     => $name,
					'settings' => array(
						'import_type'   => $import_type,
						'template'      => $template,
						'template_type' => $result['type'],
						'import_file'   => $result['id'],
						'general'       => $general
					),
				) );

				wp_redirect( '/wp-admin/admin.php?page=jci-importers&import=' . $post_id . '&action=edit&message=0' );
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

				$attach = new JC_Upload_Attachments();
				$result = $attach->attach_upload( $id, $_FILES['jc-importer_import_file'] );
				ImporterModel::setImportFile( $id, $result );

				wp_redirect( '/wp-admin/admin.php?page=jci-importers&import=' . $id . '&action=edit' );
				exit();
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

			$settings = apply_filters( 'jci/process_edit_form', $settings );

			// $settings = array(
			//     'start_line' => $_POST['jc-importer_start-line'],
			//     'row_count' => $_POST['jc-importer_row-count'],
			//     'permissions' => $_POST['jc-importer_permissions'],
			// );

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
			$attachment_check   = new WP_Query( array(
					'post_type'   => 'attachment',
					'post_parent' => $id,
					'post_status' => 'any',
					'p'           => $selected_import_id
				) );
			if ( $attachment_check->post_count == 1 ) {
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
				wp_redirect( '/wp-admin/admin.php?page=jci-importers&import=' . $result . '&action=logs' );
			} else {
				wp_redirect( '/wp-admin/admin.php?page=jci-importers&import=' . $result . '&action=edit' );
			}
			exit();
		}
	}

	/**
	 * Process Import Ajax
	 * @return HTML
	 */
	public function admin_ajax_import_row() {

		global $jcimporter;

		$row         = intval( $_POST['row'] );
		$importer_id = intval( $_POST['id'] );

		$jcimporter->importer = new JC_Importer_Core( $importer_id );
		$data                 = $jcimporter->importer->run_import( $row );

		require_once $jcimporter->plugin_dir . 'app/view/imports/log/log_table_record.php';

		die();
	}

	/**
	 * Process Ajax Deletion of missing records from tracked import
	 * @return json
	 */
	public function admin_ajax_process_delete(){

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