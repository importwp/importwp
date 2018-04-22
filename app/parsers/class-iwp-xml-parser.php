<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ImportWP XML Parser
 */
class IWP_XML_Parser {

	/**
	 * Setup Actions and filters
	 */
	public function __construct() {
		add_filter( 'jci/load_xml_settings', array( $this, 'load_settings' ), 10, 2 );

		add_action( 'jci/save_template', array( $this, 'save_template' ), 10, 2 );
		add_action( 'jci/output_xml_general_settings', array(
			$this,
			'output_general_settings'
		) );
		add_action( 'jci/output_xml_group_settings', array(
			$this,
			'output_group_settings'
		), 10, 2 );
	}

	/**
	 * Display Addon General Fields, Replacing register_settings
	 *
	 * @param int $id Importer id
	 *
	 * @return void
	 */
	public function output_general_settings( $id ) {

		$import_base = ImporterModel::getImporterMetaArr( $id, array( '_parser_settings', 'import_base' ) );
		echo JCI_FormHelper::text( 'parser_settings[import_base]', array(
			'label'   => 'Record Base',
			'default' => $import_base,
			'after'   => ' <a href="#" class="base-node-select base button button-small button-iwp">Select</a>',
			'class'   => 'jc-importer_general-base',
			'tooltip' => JCI()->text()->get( 'import.settings.xml_base_node'  )
		) );
	}

	/**
	 * Display Addon Group Fields, Replacing register_settings
	 * @return void
	 */
	public function output_group_settings( $id, $group ) {

		$import_base = ImporterModel::getImporterMetaArr( $id, array( '_parser_settings', 'group_base', $group ) );

		$importer_version = ImporterModel::getImporterMetaArr($id, array('_import_settings', 'version'));
		if(version_compare($importer_version, '0.6.0', '>=') || empty($import_base)){
			echo JCI_FormHelper::hidden("parser_settings[group][{$group}][base]", array(
				'default' => $import_base,
				'class'   => 'jc-importer_general-group',
			));
		}else {
			echo JCI_FormHelper::text( "parser_settings[group][{$group}][base]", array(
				'label'   => 'Record Base',
				'default' => $import_base,
				'after'   => ' <a href="#" class="base-node-select group button button-small button-iwp">Select</a>',
				'class'   => 'jc-importer_general-group',
				'tooltip' => JCI()->text()->get( 'import.settings.xml_base_node' )
			) );
		}
	}

	/**
	 * Load parser settings into the addon array
	 *
	 * @param  array $settings
	 *
	 * @return void
	 */
	public function load_settings( $settings, $id ) {

		$settings['import_base'] = ImporterModel::getImporterMetaArr( $id, array( '_parser_settings', 'import_base' ) );
		$settings['group_base']  = ImporterModel::getImporterMetaArr( $id, array( '_parser_settings', 'group_base' ) );

		return $settings;
	}

	/**
	 * Save XML fields into database
	 *
	 * @param  int $id
	 *
	 * @return void
	 */
	public function save_template( $id, $parser_type ) {

		if ( $parser_type == 'xml' ) {
			$parser_settings = $_POST['jc-importer_parser_settings'];

			$base        = $parser_settings['import_base'];
			$temp_groups = $parser_settings['group'];

			$groups = array();
			foreach ( $temp_groups as $group_id => $group ) {
				$groups[ $group_id ] = $group['base'];
			}

			$result = array(
				'import_base' => $base,
				'group_base'  => $groups
			);

			ImporterModel::setImporterMeta( $id, '_parser_settings', $result );
		}
	}
}

/**
 * Autoload XML Parser
 */
add_action('jci/before_init', 'register_xml_parser', 10, 0);
function register_xml_parser( ) {
	new IWP_XML_Parser();
}