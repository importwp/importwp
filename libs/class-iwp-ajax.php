<?php

class IWP_Ajax {

	private $_config = null;

	private $_curr_row = 0;
	private $_results = array();

	private $error_on_shutdown;

	public function __construct( &$config ) {
		$this->_config = $config;

		add_action( 'wp_ajax_jc_base_node', array( $this, 'admin_ajax_base_node' ) );
		add_action( 'wp_ajax_iwp_process', array( $this, 'process' ) );
		add_action( 'wp_ajax_jc_preview_record', array( $this, 'admin_ajax_preview_record' ) );
		add_action( 'wp_ajax_jc_record_total', array( $this, 'admin_ajax_record_count' ) );

		add_action( 'wp_ajax_jc_node_select', array( $this, 'admin_ajax_node_select' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_ajax_import' ) );

		// preview xml base node
		add_action( 'wp_ajax_jc_preview_xml_base_bode', array( $this, 'admin_ajax_preview_xml_node' ) );
	}

	public function get_last_error(){

		if(false === $this->error_on_shutdown){
			return;
		}

		$last_error = error_get_last();
		wp_send_json_error(array('error' => $last_error));
	}

	private function start_request(){
		$this->error_on_shutdown = true;
		register_shutdown_function(array($this, 'get_last_error'));
	}

	private function end_request($result = null){
		$this->error_on_shutdown = false;
		wp_send_json_success($result);
		die();
	}

	public function enqueue_ajax_import() {

		global $pagenow;

		wp_enqueue_style( 'thickbox' );
		wp_enqueue_script( 'thickbox' );

		$ext     = '.min';
		$version = JCI()->get_version();
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			$version = time();
			$ext     = '';
		}

		if ( isset( $_GET['page'] ) && $_GET['page'] == 'jci-importers' && isset( $_GET['import'] ) && intval( $_GET['import'] ) > 0 ) {

			$post_id = intval( $_GET['import'] );
			wp_enqueue_script( 'tiptip', trailingslashit( JCI()->get_plugin_url() ) . 'resources/js/vendor/jquery-tipTip' . $ext . '.js', array(), '1.3' );
			wp_enqueue_script( 'ajax-importer', trailingslashit( JCI()->get_plugin_url() ) . 'resources/js/importer' . $ext . '.js', array(
				'jquery',
				'tiptip'
			), $version, false );
			wp_localize_script( 'ajax-importer', 'ajax_object', array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'id'                 => $post_id,
				'node_ajax_url'      => admin_url( 'admin-ajax.php?action=jc_node_select&importer_id=' . $post_id ),
				'base_node_ajax_url' => admin_url( 'admin-ajax.php?action=jc_base_node&importer_id=' . $post_id ),
				'record_preview_url' => admin_url( 'admin-ajax.php?action=jc_preview_record&importer_id=' . $post_id ),
			) );

			do_action( 'jci/admin_scripts' );
		}
	}

	/**
	 * Ajax show xml generated from currently chosen nodes
	 * @return void
	 */
	public function admin_ajax_preview_xml_node() {

		$this->start_request();

		$importer_id = intval( $_POST['id'] );
		$base_node   = $_POST['base'];

		$file = IWP_Importer_Settings::getImportSettings( $importer_id, 'import_file' );

		$config_file = JCI()->get_tmp_config_path($importer_id);
		$config = new \ImportWP\Importer\Config\Config($config_file);
		$config->set('file_encoding', apply_filters('iwp/importer/file_encoding', false, $importer_id));

		$xml_file = new \ImportWP\Importer\File\XMLFile($file, $config);
		$xml = new \ImportWP\Importer\Preview\XMLPreview($xml_file, $base_node);

		ob_start();
		require_once $this->_config->get_plugin_dir() . 'resources/views/ajax/xml_node_preview.php';
		$contents = ob_get_clean();

		$this->end_request($contents);
	}

	/**
	 * Ajax Show node select modal
	 * @return void
	 */
	public function admin_ajax_node_select() {

		// get url values
		$post_id = intval( $_GET['importer_id'] );
		$type    = isset( $_GET['type'] ) ? $_GET['type'] : '';

		$config_file = JCI()->get_tmp_config_path($post_id);
		$config = new \ImportWP\Importer\Config\Config($config_file);
		$config->set('file_encoding', apply_filters('iwp/importer/file_encoding', false, $post_id));

		switch ( $type ) {
			case 'xml':

				$base_node = isset( $_GET['base'] ) ? $_GET['base'] : '';
				$file      = IWP_Importer_Settings::getImportSettings( $post_id, 'import_file' );

				$xml_file = new \ImportWP\Importer\File\XMLFile( $file, $config );
				$xml = new \ImportWP\Importer\Preview\XMLPreview( $xml_file, $base_node );

				if(empty($base_node)){
					echo "<div class=\"error_msg warn error below-h2\"><p>No Record Base specified, Make sure you have set the xml record base path first!</p></div>";
				}

				require_once $this->_config->get_plugin_dir() . 'resources/views/ajax/xml_node_select.php';
				break;
			case 'csv':
				$settings = IWP_Importer_Settings::getImportSettings( $post_id );
				$file     = IWP_Importer_Settings::getImportSettings( $post_id, 'import_file' );
				// $file = $settings['import_file'];
				$fh      = fopen( $file, 'r' );
				$counter = 0;
				$records = array();

				$csv_delimiter = IWP_Importer_Settings::getImporterMetaArr( $post_id, array(
					'_parser_settings',
					'csv_delimiter'
				) );
				$csv_enclosure = IWP_Importer_Settings::getImporterMetaArr( $post_id, array(
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

				$csv_file = new \ImportWP\Importer\File\CSVFile($file, $config);
				$csv_file->setDelimiter($csv_delimiter);
				$csv_file->setEnclosure($csv_enclosure);
				$parser = new \ImportWP\Importer\Parser\CSVParser($csv_file);

				$limit = $csv_file->getRecordCount();
				if($limit > 5){
					$limit = 5;
				}

				while($csv_file->hasNextRecord() && $counter < $limit){

					$line = $parser->getRecord($counter)->record();
					$records[] = $line;
					$counter ++;
				}

				require_once $this->_config->get_plugin_dir() . 'resources/views/ajax/csv_node_select.php';
				break;
		}
		die();
	}

	/**
	 * Fetch an array of all possible nodes in the current xml document
	 *
	 * Load the importer xml file, and scan through the file to fetch a
	 * list of all possible xml xpath nodes
	 *
	 * @return array document xml nodes xpath ['/posts'. '/posts/post']
	 */
	public function admin_ajax_base_node() {

		$post_id           = intval($_GET['importer_id']);
		if($post_id <= 0){
			http_response_code(404);
			die();
		}

		$base_node         = isset( $_GET['base'] ) ? $_GET['base'] : '';
		$current_base_node = isset( $_GET['current'] ) ? $_GET['current'] : 'choose-one';
		$nodes             = array(); // array of nodes

		// 
		$file = IWP_Importer_Settings::getImportSettings( $post_id, 'import_file' );

		$config_file = JCI()->get_tmp_config_path($post_id);
		$config = new \ImportWP\Importer\Config\Config($config_file);
		$config->set('file_encoding', apply_filters('iwp/importer/file_encoding', false, $post_id));

		$xml_file = new \ImportWP\Importer\File\XMLFile( $file, $config );
		$xml = new \ImportWP\Importer\Preview\XMLPreview( $xml_file, $base_node );
		$nodes = $xml_file->get_node_list();

		if ( ! empty( $base_node ) ) {

			$temp = array();

			foreach ( $nodes as $node ) {

				if ( strpos( $node, $base_node ) === 0 ) {
					$node_temp = substr( $node, strlen( $base_node ) );
					if ( ! empty( $node_temp ) ) {
						$temp[] = $node_temp;
					}
				}
			}

			$nodes = $temp;
		}

		require_once $this->_config->get_plugin_dir() . 'resources/views/ajax/base_node_select.php';
		die();
	}

	/**
	 * return mapped data for chosen import record
	 * @return void
	 */
	public function admin_ajax_preview_record() {

		$importer_id = intval($_POST['id']);
		$map         = $_POST['map'];
		$row         = isset( $_POST['row'] ) && intval( $_POST['row'] ) > 0 ? intval( $_POST['row'] ) : 1;

		// setup importer
		JCI()->importer    = new IWP_Importer( $importer_id );
		$result            = array();

		$config_file = JCI()->get_tmp_config_path($importer_id);
		$config = new \ImportWP\Importer\Config\Config( $config_file );
		$config->set('file_encoding', apply_filters('iwp/importer/file_encoding', false, $importer_id));

		if($config->get('processed') === true) {

			try {
				if ( JCI()->importer->get_template_type() === 'csv' ) {

					$file = new \ImportWP\Importer\File\CSVFile( JCI()->importer->get_file(), $config );

					$csv_delimiter = IWP_Importer_Settings::getImporterMetaArr( JCI()->importer->get_ID(), array(
						'_parser_settings',
						'csv_delimiter'
					) );
					$csv_enclosure = IWP_Importer_Settings::getImporterMetaArr( JCI()->importer->get_ID(), array(
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

					$file->setDelimiter( $csv_delimiter );
					$file->setEnclosure( $csv_enclosure );

					$parser = new \ImportWP\Importer\Parser\CSVParser( $file );

				} else {
					$base = isset( $_POST['general_base'] ) ? $_POST['general_base'] : '';

					if(empty($base)){
						return wp_send_json_error("No record base set");
					}

					$file = new \ImportWP\Importer\File\XMLFile( JCI()->importer->get_file(), $config );
					$file->setRecordPath( $base );
					$parser = new \ImportWP\Importer\Parser\XMLParser( $file );
				}

				$record = $parser->getRecord( $row - 1 );

				if ( is_array( $map ) ) {


					$group = [];

					// process list of data maps
					foreach ( $map as $map_row ) {

						if(!isset($map_row['map']) || !isset($map_row['field'])){
							continue;
						}

						$map_val   = stripslashes( $map_row['map'] );
						$map_field = $map_row['field'];

						if ( $map_val == "" ) {
							continue;
						}

						$group[ $map_field ] = $map_val;
					}

					$results = $record->queryGroup( [ 'fields' => $group ] );
					if ( ! empty( $results ) ) {
						foreach ( $map as $map_row ) {

							if(!isset($map_row['map']) || !isset($map_row['field'])){
								continue;
							}

							$map_val   = stripslashes( $map_row['map'] );
							$map_field = $map_row['field'];

							if ( $map_val == "" ) {
								continue;
							}

							$result[] = array( $map_val, $results[ $map_field ] );
						}
					}

				} else {
					$map_field = isset( $_POST['field'] ) ? $_POST['field'] : '';
					$map_val   = stripslashes( $map );


					$results = $record->queryGroup( [ 'fields' => [ $map_field => $map_val ] ] );

					$result[] = array( $map_val, $results[ $map_field ] );
				}
			} catch ( Exception $e ) {
				wp_send_json_error($e->getMessage());
			}
		}else{
			http_response_code(500);
		}

		wp_send_json_success($result);
	}

	/**
	 * Get Record Count for chosen importer
	 * @return void
	 */
	public function admin_ajax_record_count() {
		$importer_id = intval($_POST['id']);
		JCI()->importer = new IWP_Importer( $importer_id );

		if(JCI()->importer->get_template_type() === 'xml'){
			$base = isset($_POST['general_base']) ? $_POST['general_base'] : '';
			JCI()->importer->addon_settings['import_base'] = $base;
		}

		$result = JCI()->importer->get_total_rows(true);

		echo json_encode( $result );
		die();
	}

	public function process(){

		set_time_limit(0);

		// enable error handler
		$this->start_request();

		$importer_id = intval($_POST['id']);
		JCI()->importer = new IWP_Importer( $importer_id );

		$config_file = JCI()->get_tmp_config_path($importer_id);
		$config = new \ImportWP\Importer\Config\Config( $config_file );
		$config->set('file_encoding', apply_filters('iwp/importer/file_encoding', false, $importer_id));
		if( $config->get('processed') !== true ) {

			$file_path = JCI()->importer->get_file();
			if(JCI()->importer->get_template_type() === 'xml'){

				// generate node list
				$file = new \ImportWP\Importer\File\XMLFile( $file_path, $config );
				$file->get_node_list();

				// generate indices for current basepath
				$base = JCI()->importer->addon_settings['import_base'];
				$file = new \ImportWP\Importer\File\XMLFile( $file_path, $config );
				$file->setRecordPath($base);
				$file->getRecordCount();

				$config->set('processed', true);

			}else{
				$file = new \ImportWP\Importer\File\CSVFile( JCI()->importer->get_file(), $config );

				$csv_delimiter = IWP_Importer_Settings::getImporterMetaArr( JCI()->importer->get_ID(), array(
					'_parser_settings',
					'csv_delimiter'
				) );
				$csv_enclosure = IWP_Importer_Settings::getImporterMetaArr( JCI()->importer->get_ID(), array(
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

				$file->setDelimiter( $csv_delimiter );
				$file->setEnclosure( $csv_enclosure );

				$file->getRecordCount();
				$config->set('processed', true);
			}
		}

		// output and disable error handler
		$this->end_request();
	}
}

?>