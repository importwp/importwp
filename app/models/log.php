<?php

/**
 * Log Imports
 *
 * Log data imported using JC Importer
 */
class ImportLog {

	/**
	 * Config Instance
	 * @var class
	 */
	static $config;

	/**
	 * Create an instance of config
	 *
	 * @param  class $config
	 *
	 * @return void
	 */
	static function init( &$config ) {
		self::$config = $config;
	}

	/**
	 * Insert Record into log table
	 *
	 * @param  int $import_id
	 * @param  int $row
	 * @param  array $record
	 *
	 * @return void
	 */
	static function insert( $import_id, $row, $record ) {

		global $wpdb, $jcimporter;

		// todo: replace this fix with a $_GET key to know the real start of the import
		$start_row = $jcimporter->importer->get_start_line();
		if ( $start_row <= 0 ) {
			$start_row = 1;
		}

		$template = ImporterModel::getImportSettings( $import_id );
		$importer = ImporterModel::getImporter( $import_id );
		$version = $jcimporter->importer->get_version();

		// if ( $start_row == $row ) {
		// 	// increase next row
		// 	$version = self::get_current_version( $import_id );
		// 	$version ++;
		// } else {
		// 	$version = self::get_current_version( $import_id );
		// }


		$wpdb->query( "
			INSERT INTO `" . $wpdb->prefix . "importer_log` (importer_name, object_id, template,type,file, version, row, src, value, created)
			VALUES('" . $importer->post->post_name . "', '" . $import_id . "', '" . $template['template'] . "', '" . $template['template_type'] . "', '" . $template['import_file'] . "', '" . $version . "', '" . $row . "', '', '" . mysql_real_escape_string( serialize( $record ) ) . "', NOW());" );
	}

	/**
	 * Get Latest Import Log Version
	 *
	 * @param  int $import_id
	 *
	 * @return int
	 */
	static function get_current_version( $import_id ) {
		global $wpdb;
		$import_id = intval( $import_id );
		$row       = $wpdb->get_row( "SELECT version FROM `" . $wpdb->prefix . "importer_log` WHERE object_id='" . $import_id . "' GROUP BY version ORDER BY version DESC" );

		if ( ! $row ) {
			return 0;
		}

		return $row->version;
	}

	/**
	 * Create Log Table
	 * @return void
	 */
	static function scaffold() {

		global $wpdb;

		$wpdb->show_errors();
		$charset_collate = "";

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "importer_log` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `importer_name` varchar(255) DEFAULT NULL,
			  `object_id` int(11) DEFAULT NULL,
			  `template` varchar(255) DEFAULT NULL,
			  `type` varchar(255) DEFAULT NULL,
			  `file` varchar(255) DEFAULT NULL,
			  `version` int(11) DEFAULT NULL,
			  `row` int(11) DEFAULT NULL,
			  `src` text,
			  `value` text,
			  `created` datetime DEFAULT NULL,
			  PRIMARY KEY (`id`)
			) $charset_collate; ";

		dbDelta( $sql );
	}

	/**
	 * Get Importer Logs
	 *
	 * @param int $importer_id
	 *
	 * @return array
	 */
	static function get_importer_logs( $importer_id = null ) {

		global $wpdb;
		$importer_id = intval( $importer_id );

		return $wpdb->get_results( "SELECT version, type, file,  created, MAX(row) as row_total FROM `wp_importer_log` WHERE object_id='{$importer_id}' GROUP BY version ORDER BY version DESC", OBJECT );
	}

	static function get_importer_log( $importer_id, $log ) {

		global $wpdb;

		$importer_id = intval( $importer_id );
		$log         = intval( $log );

		return $wpdb->get_results( "SELECT * FROM `wp_importer_log` WHERE object_id='{$importer_id}' AND version='{$log}' ORDER BY version DESC", OBJECT );
	}

}