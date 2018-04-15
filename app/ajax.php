<?php

class JC_Importer_Ajax {

	private $_config = null;

	private $_curr_row = 0;
	private $_results = array();

	public function __construct( &$config ) {
		$this->_config = $config;

		add_action( 'wp_ajax_jc_base_node', array( $this, 'admin_ajax_base_node' ) );
		add_action( 'wp_ajax_jc_preview_record', array( $this, 'admin_ajax_preview_record' ) );
		add_action( 'wp_ajax_jc_record_total', array( $this, 'admin_ajax_record_count' ) );

		add_action( 'wp_ajax_jc_node_select', array( $this, 'admin_ajax_node_select' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_ajax_import' ) );

		// preview xml base node
		add_action( 'wp_ajax_jc_preview_xml_base_bode', array( $this, 'admin_ajax_preview_xml_node' ) );
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
			wp_enqueue_script( 'tiptip', trailingslashit( JCI()->get_plugin_url() ) . 'app/assets/js/jquery-tipTip' . $ext . '.js', array(), '1.3' );
			wp_enqueue_script( 'ajax-importer', trailingslashit( JCI()->get_plugin_url() ) . 'app/assets/js/importer' . $ext . '.js', array(
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

		$importer_id = intval( $_POST['id'] );
		$base_node   = $_POST['base'];

		$file = ImporterModel::getImportSettings( $importer_id, 'import_file' );

		$config_file = tempnam(sys_get_temp_dir(), 'config');
		$config = new \ImportWP\Importer\Config\Config($config_file);
		$xml_file = new \ImportWP\Importer\File\XMLFile($file);
		$xml = new \ImportWP\Importer\Preview\XMLPreview($xml_file, $base_node);

		require_once $this->_config->get_plugin_dir() . 'app/view/ajax/xml_node_preview.php';
		die();
	}

	/**
	 * Ajax Show node select modal
	 * @return void
	 */
	public function admin_ajax_node_select() {

		// get url values
		$post_id = intval( $_GET['importer_id'] );
		$type    = isset( $_GET['type'] ) ? $_GET['type'] : '';

		switch ( $type ) {
			case 'xml':

				$base_node = isset( $_GET['base'] ) ? $_GET['base'] : '';
				$file      = ImporterModel::getImportSettings( $post_id, 'import_file' );

				$config_file = tempnam(sys_get_temp_dir(), 'config');
				$config = new \ImportWP\Importer\Config\Config($config_file);
				$xml_file = new \ImportWP\Importer\File\XMLFile( $file, $config );
				$xml = new \ImportWP\Importer\Preview\XMLPreview( $xml_file, $base_node );

				require_once $this->_config->get_plugin_dir() . 'app/view/ajax/xml_node_select.php';
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

				$config_file = tempnam(sys_get_temp_dir(), 'config');
				$config = new \ImportWP\Importer\Config\Config($config_file);
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

				require_once $this->_config->get_plugin_dir() . 'app/view/ajax/csv_node_select.php';
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

		$post_id           = $_GET['importer_id'];
		$base_node         = isset( $_GET['base'] ) ? $_GET['base'] : '';
		$current_base_node = isset( $_GET['current'] ) ? $_GET['current'] : 'choose-one';
		$nodes             = array(); // array of nodes

		// 
		$file = ImporterModel::getImportSettings( $post_id, 'import_file' );

		$config_file = tempnam(sys_get_temp_dir(), 'config');
		$config = new \ImportWP\Importer\Config\Config($config_file);
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

		require_once $this->_config->get_plugin_dir() . 'app/view/ajax/base_node_select.php';
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

	/**
	 * return mapped data for chosen import record
	 * @return void
	 */
	public function admin_ajax_preview_record() {

		$importer_id = $_POST['id'];
		$map         = $_POST['map'];
		$row         = isset( $_POST['row'] ) && intval( $_POST['row'] ) > 0 ? intval( $_POST['row'] ) : 1;

		// setup importer
		JCI()->importer    = new JC_Importer_Core( $importer_id );
		$result            = array();

		$config_file = tempnam(sys_get_temp_dir(), 'config');
		$config = new \ImportWP\Importer\Config\Config($config_file);

		if(JCI()->importer->get_template_type() === 'csv'){

			$file = new \ImportWP\Importer\File\CSVFile(JCI()->importer->get_file());
			$parser = new \ImportWP\Importer\Parser\CSVParser($file);

		}else{
			$base = isset($_POST['general_base']) ? $_POST['general_base'] : '';
			$file = new \ImportWP\Importer\File\XMLFile(JCI()->importer->get_file());
			$file->setRecordPath($base);
			$parser = new \ImportWP\Importer\Parser\XMLParser($file);
		}

		try {
			$record = $parser->getRecord( $row - 1 );

			if(is_array($map)){


				$group = [];

				// process list of data maps
				foreach ( $map as $map_row ) {

					$map_val   = $map_row['map'];
					$map_field = $map_row['field'];

					if ( $map_val == "" ) {
						continue;
					}

					$group[$map_field] = $map_val;
				}

				$results = $record->queryGroup( [ 'fields' => $group]);
				if(!empty($results)) {
					foreach ( $map as $map_row ) {

						$map_val   = $map_row['map'];
						$map_field = $map_row['field'];

						if ( $map_val == "" ) {
							continue;
						}

						$result[] = array( $map_val, $results[ $map_field ] );
					}
				}

			}else{
				$map_field    = isset( $_POST['field'] ) ? $_POST['field'] : '';
				$map_val = $map;


				$results = $record->queryGroup( [ 'fields' => [ $map_field => $map_val]]);

				$result[] = array($map_val, $results[$map_field]);
			}

		}catch(Exception $e){

		}

		echo json_encode( $result );
		die();
	}

	/**
	 * Get Record Count for chosen importer
	 * @return void
	 */
	public function admin_ajax_record_count() {
		$importer_id = $_POST['id'];

		// setup importer
		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$jcimporter->importer = new JC_Importer_Core( $importer_id );
		$jci_file             = $jcimporter->importer->file;
		$jci_template_type    = $jcimporter->importer->template_type;
		$parser               = $jcimporter->parsers[ $jci_template_type ];
		$parser->loadFile( $jci_file );


		$result = apply_filters( 'jci/ajax_' . $jci_template_type . '/record_count', 0 );

		echo json_encode( $result );
		die();
	}
}

?>