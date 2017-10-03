<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ImportWP CSV Parser
 */
class IWP_CSV_Parser extends IWP_Parser {

	public $name = 'csv';
	protected $_config = array();
	protected $_records = array();
	/**
	 * List of file seek index's
	 *
	 * @var array
	 */
	protected $_seekIndex = array();
	private $default_csv_delimiter = ',';
	private $default_csv_enclosure = '&quot;';

	/**
	 * Setup Actions and filters
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'jci/parse_csv_field', array( $this, 'parse_field' ), 10, 3 );
		add_filter( 'jci/process_csv_map_field', array( $this, 'process_map_field' ), 10, 2 );
		add_filter( 'jci/load_csv_settings', array( $this, 'load_settings' ), 10, 2 );
		add_filter( 'jci/ajax_csv/preview_record', array( $this, 'ajax_preview_record' ), 10, 4 );
		add_filter( 'jci/ajax_csv/record_count', array( $this, 'ajax_record_count' ), 10, 1 );

		add_action( 'jci/save_template', array( $this, 'save_template' ), 10, 2 );
		add_action( 'jci/output_' . $this->get_name() . '_general_settings', array(
			$this,
			'output_general_settings'
		) );
	}

	/**
	 * Display Addong Fields, Replacing regiser_settings
	 * @return void
	 */
	public function output_general_settings( $id ) {

		$csv_delimiter = ImporterModel::getImporterMetaArr( $id, array( '_parser_settings', 'csv_delimiter' ) );
		$csv_enclosure = ImporterModel::getImporterMetaArr( $id, array( '_parser_settings', 'csv_enclosure' ) );
		$csv_enclosure = htmlspecialchars( stripslashes( $csv_enclosure ) );

		if ( empty( $csv_delimiter ) ) {
			$csv_delimiter = $this->default_csv_delimiter;
		}

		if ( empty( $csv_enclosure ) ) {
			$csv_enclosure = $this->default_csv_enclosure;
		}

		echo JCI_FormHelper::text( 'parser_settings[csv_delimiter]', array(
			'label'   => 'Delimiter',
			'default' => $csv_delimiter,
			'class'   => 'jc-importer_csv-delimiter',
			'tooltip' => JCI()->text()->get( 'import.settings.csv_delimiter' )
		) );
		echo JCI_FormHelper::text( 'parser_settings[csv_enclosure]', array(
			'label'   => 'Enclosure',
			'default' => $csv_enclosure,
			'class'   => 'jc-importer_csv-enclosure',
			'tooltip' => JCI()->text()->get( 'import.settings.csv_enclosure' )
		) );
	}

	/**
	 * Load parser settings into the addon array
	 *
	 * @param  array $settings
	 *
	 * @return array
	 */
	public function load_settings( $settings, $id ) {

		$settings['csv_delimiter'] = ImporterModel::getImporterMetaArr( $id, array(
			'_parser_settings',
			'csv_delimiter'
		) );
		$settings['csv_enclosure'] = ImporterModel::getImporterMetaArr( $id, array(
			'_parser_settings',
			'csv_enclosure'
		) );

		return $settings;
	}

	/**
	 * Save XML fields into database
	 *
	 * @param  int $id
	 *
	 * @param $parser_type
	 *
	 * @return void
	 */
	public function save_template( $id, $parser_type ) {

		if ( $parser_type == 'csv' ) {

			$parser_settings = $_POST['jc-importer_parser_settings'];

			$delimiter = $parser_settings['csv_delimiter'];
			$enclosure = $parser_settings['csv_enclosure'];
			$enclosure = addslashes( $enclosure );

			$result = array(
				'csv_delimiter' => $delimiter,
				'csv_enclosure' => $enclosure
			);

			ImporterModel::setImporterMeta( $id, '_parser_settings', $result );
		}
	}

	/**
	 * Parse CSV Field
	 *
	 * @param  string $field
	 * @param $map
	 * @param $row
	 *
	 * @return string
	 *
	 */
	public function parse_field( $field, $map, $row ) {

		$field_parser = new IWP_CSV_Field_Parser( $row );

		return $field_parser->parse_field( $field );
	}

	/**
	 * Load CSV for current record
	 *
	 * @param  int $group_id
	 * @param  integer $row
	 *
	 * @return string
	 */
	public function process_map_field( $group_id, $row ) {

		return isset( $this->_records[ $row - 1 ] ) ? $this->_records[ $row - 1 ] : false;
	}

	/**
	 * Parse CSV
	 *
	 * Load CSV File and parse data into results array
	 *
	 * @param null $selected_row
	 * @param int $max_rows
	 *
	 * @return array
	 */
	public function parse( $selected_row = null, $max_rows = 0 ) {

		$info        = $this->get_import_info( $selected_row, $max_rows );
		$this->start = $info['start'];
		$this->end   = $info['end'];

		$groups = JCI()->importer->get_template_groups();

		$fh = fopen( $this->file, 'r' );

		$records = array();

		// read from last seek
		$status = IWP_Status::read_file();
		if ( isset( $status['seek'] ) && intval( $status['seek'] ) > 0 ) {
			fseek( $fh, intval( $status['seek'] ) );
			$counter = intval( $status['last_record'] ) + 1;
			$selected_row --;
		} else {

			// generate seek index
			$seek_index = array( 0 );
			while ( ( $buffer = fgets( $fh, 4096 ) ) !== false ) {
				$seek_index[] = ftell( $fh );
			}

			fseek( $fh, intval( $seek_index[ $this->start - 1 ] ) );
			$counter = $this->start;
		}

		// set enclosure and delimiter
		$delimiter = $this->get_delimiter();
		$enclosure = $this->get_enclosure();

		while ( $line = fgetcsv( $fh, null, $delimiter, $enclosure ) ) {

			// skip if not selected row
			if ( ! is_null( $selected_row ) && $counter < $selected_row ) {

				if ( $selected_row < $counter && $this->end < $counter ) {
					break;
				}

				$counter ++;
				continue;
			}

			if ( $this->end >= 0 && $counter > $this->end ) {
				break;
			}

			// skip if not withing limits
			if ( ( $this->start >= 0 && $counter < $this->start ) ) {
				$counter ++;
				continue;
			}

			$row = array();

			$this->_records[ $counter - 1 ] = $line;

			foreach ( $groups as $group_id => $group ) {
				foreach ( $group['fields'] as $key => $val ) {
					$result                   = apply_filters( 'jci/parse_csv_field', $val, $val, $line );
					$result                   = apply_filters( 'jci/parse_csv_field/' . $key, $result, $val, $line );
					$row[ $group_id ][ $key ] = $result;
				}
			}

			$records[ $counter - 1 ]          = $row;
			$this->_seekIndex[ $counter - 1 ] = ftell( $fh );

			$counter ++;

			// escape early if selected row
			if ( $this->end <= $counter ) {
				break;
			}
		}

		fclose( $fh );

		return $records;
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
		$start = $start_row = JCI()->importer->get_start_line();
		if ( ! is_null( $selected_row ) ) {
			$start = $selected_row;
		}

		$end = $total_rows = JCI()->importer->get_total_rows() + 1;

		// records per import
		$max_rows = JCI()->importer->get_row_count();
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
	 * Get delimiter for currently loaded importer
	 *
	 * @return string
	 */
	protected function get_delimiter() {
		$delimiter = isset( JCI()->importer->addon_settings['csv_delimiter'] ) && ! empty( JCI()->importer->addon_settings['csv_delimiter'] ) ? JCI()->importer->addon_settings['csv_delimiter'] : ',';

		return stripslashes( $delimiter );

	}

	/**
	 * Get enclosure for currently loaded importer
	 * @return string
	 */
	protected function get_enclosure() {
		$enclosure = isset( JCI()->importer->addon_settings['csv_enclosure'] ) && ! empty( JCI()->importer->addon_settings['csv_enclosure'] ) ? JCI()->importer->addon_settings['csv_enclosure'] : '"';

		return stripslashes( $enclosure );
	}

	/**
	 * Ajax preview record method
	 *
	 * @param string $result
	 * @param $row
	 * @param $map
	 * @param $field
	 *
	 * @return mixed|string|void
	 */
	public function ajax_preview_record( $result = '', $row, $map, $field ) {
		return $this->preview_field( $map, $row, $field );
	}

	/**
	 * CSV preview a single record
	 *
	 * @param string $map
	 * @param null $selected_row
	 * @param $field
	 *
	 * @return mixed|string|void
	 */
	public function preview_field( $map = '', $selected_row = null, $field ) {

		$fh      = fopen( $this->file, 'r' );
		$counter = 1;
		$result  = '';

		// set enclosure and delimiter
		$delimiter = $this->get_delimiter();
		$enclosure = $this->get_enclosure();

		while ( $line = fgetcsv( $fh, null, $delimiter, $enclosure ) ) {

			// skip if not selected row
			if ( ! is_null( $selected_row ) && $counter != $selected_row ) {
				$counter ++;
				continue;
			}

			$result = apply_filters( 'jci/parse_csv_field', $map, $map, $line );
			$result = apply_filters( 'jci/parse_csv_field/' . $field, $result, $map, $line );
			break;
		}

		fclose( $fh );

		return $result;
	}

	/**
	 * Ajax record count method
	 *
	 * @param int $result
	 *
	 * @return int
	 */
	public function ajax_record_count( $result = 0 ) {
		return $this->get_total_rows() - 1;
	}

	/**
	 * Get the total of rows matching the Importers settings
	 *
	 * @param  integer $importer_id
	 *
	 * @return integer
	 */
	public function get_total_rows( $importer_id = 0 ) {

		if ( $importer_id > 0 ) {
			$id = $importer_id;
		} else {
			$id = JCI()->importer->get_ID();
		}

		// load settings
		$file = ImporterModel::getImportSettings( $id, 'import_file' );

		// todo: throw error
		if ( ! is_file( $file ) ) {
			return 0;
		}

		$line_count = 0;
		$fh         = fopen( $file, 'r' );

		while ( ! feof( $fh ) ) {
			$line = fgets( $fh );
			$line_count ++;
		}

		// remove empty lines from end of file
		if ( empty( $line ) && $line_count > 0 ) {
			$line_count --;
		}

		fclose( $fh );

		return $line_count;
	}

	/**
	 * Get seek index for current file
	 *
	 * @param $row
	 *
	 * @return mixed|null
	 */
	public function get_seek_index( $row ) {
		return isset( $this->_seekIndex[ $row ] ) ? $this->_seekIndex[ $row ] : null;
	}
}

/**
 * Autoload CSV Parser
 */
add_filter( 'jci/register_parser', 'register_csv_parser', 10, 1 );
function register_csv_parser( $parsers = array() ) {
	$parsers['csv'] = new IWP_CSV_Parser(); //'JC_CSV_Parser';

	return $parsers;
}