<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ImportWP CSV Parser
 */
class IWP_CSV_Parser{

	private $default_csv_delimiter = ',';
	private $default_csv_enclosure = '&quot;';

	/**
	 * Setup Actions and filters
	 */
	public function __construct() {

		add_filter( 'jci/parse_csv_field', array( $this, 'parse_field' ), 10, 3 );
		add_filter( 'jci/process_csv_map_field', array( $this, 'process_map_field' ), 10, 2 );
		add_filter( 'jci/load_csv_settings', array( $this, 'load_settings' ), 10, 2 );

		add_action( 'jci/save_template', array( $this, 'save_template' ), 10, 2 );
		add_action( 'jci/output_csv_general_settings', array(
			$this,
			'output_general_settings'
		) );
	}

	/**
	 * Display Addon Fields, Replacing register_settings
	 * @return void
	 */
	public function output_general_settings( $id ) {

		$csv_delimiter = IWP_Importer_Settings::getImporterMetaArr( $id, array( '_parser_settings', 'csv_delimiter' ) );
		$csv_enclosure = IWP_Importer_Settings::getImporterMetaArr( $id, array( '_parser_settings', 'csv_enclosure' ) );
		$csv_enclosure = htmlspecialchars( stripslashes( $csv_enclosure ) );

		if ( empty( $csv_delimiter ) ) {
			$csv_delimiter = $this->default_csv_delimiter;
		}

		if ( empty( $csv_enclosure ) ) {
			$csv_enclosure = $this->default_csv_enclosure;
		}

		echo IWP_FormBuilder::text( 'parser_settings[csv_delimiter]', array(
			'label'   => 'Delimiter',
			'default' => $csv_delimiter,
			'class'   => 'jc-importer_csv-delimiter',
			'tooltip' => JCI()->text()->get( 'import.settings.csv_delimiter' )
		) );
		echo IWP_FormBuilder::text( 'parser_settings[csv_enclosure]', array(
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
	 * @param int $id
	 *
	 * @return array
	 */
	public function load_settings( $settings, $id ) {

		$settings['csv_delimiter'] = IWP_Importer_Settings::getImporterMetaArr( $id, array(
			'_parser_settings',
			'csv_delimiter'
		) );
		$settings['csv_enclosure'] = IWP_Importer_Settings::getImporterMetaArr( $id, array(
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

			$old_settings = IWP_Importer_Settings::getImporterMetaArr($id, '_parser_settings');

			$parser_settings = $_POST['jc-importer_parser_settings'];

			$delimiter = $parser_settings['csv_delimiter'];
			$enclosure = $parser_settings['csv_enclosure'];

			if($old_settings['csv_delimiter'] !== $delimiter || $old_settings['csv_enclosure'] !== $enclosure){
				IWP_Importer_Settings::clear_edit_config($id);
			}

			$result = array(
				'csv_delimiter' => $delimiter,
				'csv_enclosure' => addslashes( $enclosure )
			);

			IWP_Importer_Settings::setImporterMeta( $id, '_parser_settings', $result );
		}
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
}

/**
 * Autoload CSV Parser
 */
add_action('jci/before_init', 'register_csv_parser', 10, 0);
function register_csv_parser() {
	new IWP_CSV_Parser();
}