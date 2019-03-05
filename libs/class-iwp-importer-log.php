<?php

/**
 * Log Imports
 *
 * Log data imported using ImportWP
 */
class IWP_Importer_Log {

	/**
	 * Config Instance
	 * @var class
	 */
	static $config;

	static function init( &$config ) {
		self::$config = $config;
	}

	/**
	 * Insert Record into log table
	 * @todo : extract version settings to stop duplicate content being stored
	 *
	 * @param  int $import_id
	 * @param  int $row
	 * @param  array $record
	 *
	 * @return void
	 */
	static function insert( $import_id, $row, $record ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $wpdb;

		// todo: replace this fix with a $_GET key to know the real start of the import
		$start_row = JCI()->importer->get_start_line();
		if ( $start_row <= 0 ) {
			$start_row = 1;
		}

		$template = IWP_Importer_Settings::getImportSettings( $import_id );
		$version  = JCI()->importer->get_version();

		$wpdb->query( $wpdb->prepare( "
			INSERT INTO `" . $wpdb->prefix . "importer_log` ( object_id,file, version, row, value, created)
			VALUES(%d, %s, %d, %d, %s, NOW());",
			$import_id, $template['import_file'], $version, $row, serialize( $record ) ) );
	}

	/**
	 * Get Latest Import Log Version
	 *
	 * @param  int $import_id
	 * @param  int $version
	 *
	 * @return int
	 */
	static function get_last_row( $import_id, $version ) {
		global $wpdb;
		$import_id = intval( $import_id );
		$row       = $wpdb->get_row( $wpdb->prepare( "SELECT row FROM `" . $wpdb->prefix . "importer_log` WHERE object_id=%d AND version=%d GROUP BY row ORDER BY row DESC LIMIT 1", $import_id, $version ) );

		if ( ! $row ) {
			return 0;
		}

		return intval( $row->row );
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
		$row       = $wpdb->get_row( $wpdb->prepare( "SELECT version FROM `" . $wpdb->prefix . "importer_log` WHERE object_id=%d GROUP BY version ORDER BY version DESC", $import_id ) );

		if ( ! $row ) {
			return 0;
		}

		return intval( $row->version );
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

		return $wpdb->get_results( "SELECT version, file,  created, COUNT(row) as row_total FROM `" . $wpdb->prefix . "importer_log` WHERE object_id='{$importer_id}' GROUP BY version ORDER BY version DESC", OBJECT );
	}

	static function get_importer_log( $importer_id, $log, $order = 'DESC', $limit = 10, $page = 1 ) {

		global $wpdb;

		$importer_id = intval( $importer_id );
		$log         = intval( $log );

		if ( ! in_array( $order, array( 'DESC', 'ASC' ) ) ) {
			$order = 'DESC';
		}

		$limit_str = '';
		if ( $limit > 0 ) {
			$offset    = ( ( $page - 1 ) * $limit );
			$limit_str = "LIMIT {$offset}, {$limit}";
		}

		return $wpdb->get_results( "SELECT * FROM `" . $wpdb->prefix . "importer_log` WHERE object_id='{$importer_id}' AND version='{$log}' ORDER BY id {$order} {$limit_str}", OBJECT );
	}

	/**
	 * Get total count of importer logo
	 *
	 * @param $importer_id
	 * @param $log
	 *
	 * @return null|string
	 */
	static function get_importer_log_count( $importer_id, $log ) {

		global $wpdb;

		$importer_id = intval( $importer_id );
		$log         = intval( $log );

		return $wpdb->get_var( "SELECT COUNT(*) FROM `" . $wpdb->prefix . "importer_log` WHERE object_id='{$importer_id}' AND version='{$log}'" );
	}

	static function clearLogs() {

		global $wpdb;

		return $wpdb->query( $wpdb->prepare( "DELETE FROM `" . $wpdb->prefix . "importer_log` WHERE created < %s", date( 'Y-m-d H:i:s', strtotime( '- 1 DAY' ) ) ) );
	}

}