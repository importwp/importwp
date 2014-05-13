<?php

class JC_Importer_Ajax {

	private $_config = null;

	private $_curr_row = 0;
	private $_results = array();

	public function __construct( &$config ) {
		$this->_config = $config;

		add_action( 'wp_ajax_jc_import_file', array( $this, 'admin_ajax_import_file' ) );
		add_action( 'wp_ajax_jc_base_node', array( $this, 'admin_ajax_base_node' ) );
		add_action( 'wp_ajax_jc_preview_record', array( $this, 'admin_ajax_preview_record' ) );
		add_action( 'wp_ajax_jc_record_total', array( $this, 'admin_ajax_record_count' ) );

		add_action( 'wp_ajax_jc_node_select', array( $this, 'admin_ajax_node_select' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_ajax_import' ) );
	}

	public function enqueue_ajax_import() {

		global $pagenow;

		wp_enqueue_style( 'thickbox' );
		wp_enqueue_script( 'thickbox' );

		if ( isset( $_GET['page'] ) && $_GET['page'] == 'jci-importers' && isset( $_GET['import'] ) && intval( $_GET['import'] ) > 0 ) {

			$post_id = intval( $_GET['import'] );
			wp_enqueue_script( 'ajax-importer', plugins_url( '/assets/js/importer.js', __FILE__ ), array( 'jquery' ), 0.2, false );
			wp_localize_script( 'ajax-importer', 'ajax_object', array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'id'                 => $post_id,
				'node_ajax_url'      => admin_url( 'admin-ajax.php?action=jc_node_select&importer_id=' . $post_id ),
				'base_node_ajax_url' => admin_url( 'admin-ajax.php?action=jc_base_node&importer_id=' . $post_id ),
				'record_preview_url' => admin_url( 'admin-ajax.php?action=jc_preview_record&importer_id=' . $post_id ),
			) );
		}
	}

	/**
	 * Ajax Show node select modal
	 * @return void
	 */
	public function admin_ajax_node_select() {

		// get url values
		// todo: put this into parser class
		$post_id = intval( $_GET['importer_id'] );
		$type    = isset( $_GET['type'] ) ? $_GET['type'] : '';

		switch ( $type ) {
			case 'xml':
				$base_node = isset( $_GET['base'] ) ? $_GET['base'] : '';
				require_once $this->_config->plugin_dir . 'app/view/ajax/xml_node_select.php';
				break;
			case 'csv':
				$settings = ImporterModel::getImportSettings( $post_id );
				$file     = ImporterModel::getImportSettings( $post_id, 'import_file' );
				// $file = $settings['import_file'];
				$fh      = fopen( $file, 'r' );
				$counter = 0;
				$records = array();

				$csv_delimiter = ImporterModel::getImporterMetaArr( $post_id, array(
					'_parser_settings',
					'csv_delimiter'
				) );
				$csv_enclosure = ImporterModel::getImporterMetaArr( $post_id, array(
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

				while ( $line = fgetcsv( $fh, null, $csv_delimiter, $csv_enclosure ) ) {

					if ( $counter > 5 ) {
						break;
					}

					$records[] = $line;
					$counter ++;
				}
				fclose( $fh );
				require_once $this->_config->plugin_dir . 'app/view/ajax/csv_node_select.php';
				break;
		}
		die();
	}

	public function admin_ajax_base_node() {

		$post_id   = $_GET['importer_id'];
		$base_node = isset( $_GET['base'] ) ? $_GET['base'] : '';
		$nodes     = array(); // array of nodes

		// get xml segment
		$file = ImporterModel::getImportSettings( $post_id, 'import_file' );

		$xml = simplexml_load_file( $file );

		if ( $xml === false ) {
			echo 'Error: unable to load ' . $file;
			die();
		}

		$temp = array( 0 => array( $xml->getName() ) );
		$this->nodeIterator( $xml );

		if ( ! empty( $this->_results ) ) {
			foreach ( $this->_results as $node ) {

				if ( empty( $base_node ) || strpos( '/' . $node, $base_node ) === 0 ) {
					if ( ! empty( $base_node ) ) {
						$node = substr( $node, strlen( $base_node ) );
					}

					$nodes[] = $node;
				}
			}
		}

		require_once $this->_config->plugin_dir . 'app/view/ajax/base_node_select.php';
		die();
	}

	public function nodeIterator( $xml, $temp = array(), $depth = 0 ) {

		$children = $xml->children();
		if ( count( $children ) > 0 ) {

			if ( $depth > $this->_curr_row ) {

				$this->_curr_row = $depth;
				$temp[]          = $xml->getName();
			} elseif ( $depth == $this->_curr_row ) {

				$this->_curr_row            = $depth;
				$temp[ count( $temp ) - 1 ] = $xml->getName();
			} elseif ( $depth < $this->_curr_row ) {

				$this->_curr_row = count( $temp ) - 1;
			}

			$xpath = implode( '/', $temp );
			if ( ! in_array( $xpath, $this->_results ) ) {
				$this->_results[] = $xpath;
			}

			foreach ( $children as $child ) {
				$this->nodeIterator( $child, $temp, $depth + 1 );
			}
		}

		return false;
	}

	public function admin_ajax_import_file() {

		// grab post
		$post_id = $_POST['id'];
		$base    = $_POST['base'];

		// get xml segment
		$file = ImporterModel::getImportSettings( $post_id, 'import_file' );

		$xml     = simplexml_load_file( $file );
		$results = $xml->xpath( $base );

		echo $results[0]->asXml();
		die();
	}

	/**
	 * return mapped data for chosen import record
	 * @return void
	 */
	public function admin_ajax_preview_record(){

		$importer_id = $_POST['id'];
		$map = $_POST['map'];
		$row = isset($_POST['row']) && intval($_POST['row']) > 0 ? intval($_POST['row']) : 1;

		// setup importer
		global $jcimporter;
		$jcimporter->importer = new JC_Importer_Core($importer_id);
		$jci_file          = $jcimporter->importer->file;
		$jci_template_type = $jcimporter->importer->template_type;
		$parser            = $jcimporter->parsers[$jci_template_type ];
		$result 		   = array();

		// load file into importer
		$parser->loadFile( $jci_file );

		if(is_array($map)){

			// process list of data maps
			foreach($map as $val){
				
				if($val == "")
					continue;

				$result[] = array($val, apply_filters( 'jci/ajax_'. $jci_template_type .'/preview_record', '', $row, $val ));
			}
		}else{

			// process single data mao
			$result[] = array($map, apply_filters( 'jci/ajax_'. $jci_template_type .'/preview_record', '', $row, $map ));
		}

		echo json_encode($result);
		die();
	}

	/**
	 * Get Record Count for chosen importer
	 * @return void
	 */
	public function admin_ajax_record_count(){
		$importer_id = $_POST['id'];

		// setup importer
		global $jcimporter;
		$jcimporter->importer = new JC_Importer_Core($importer_id);
		$jci_file          = $jcimporter->importer->file;
		$jci_template_type = $jcimporter->importer->template_type;
		$parser            = $jcimporter->parsers[$jci_template_type ];
		$parser->loadFile( $jci_file );
		$result = apply_filters( 'jci/ajax_'. $jci_template_type .'/record_count', 0);

		echo json_encode($result);
		die();
	}
}

?>