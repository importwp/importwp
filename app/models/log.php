<?php

/**
 * Log Imports
 *
 * Log data imported using ImportWP
 */
class ImportLog {

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
		global $wpdb, $jcimporter;

		// todo: replace this fix with a $_GET key to know the real start of the import
		$start_row = $jcimporter->importer->get_start_line();
		if ( $start_row <= 0 ) {
			$start_row = 1;
		}

		$template = ImporterModel::getImportSettings( $import_id );
		$importer = ImporterModel::getImporter( $import_id );
		$version = $jcimporter->importer->get_version();

		// copy version settings (previously stored in post meta) to first row only
		if( $row == 1 || $row == $start_row ){
			$import_settings = get_post_meta( $import_id, '_import_settings', true );
			$mapped_fields = get_post_meta( $import_id, '_mapped_fields', true );
			$attachments = get_post_meta( $import_id, '_attachments', true );
			$taxonomies = get_post_meta( $import_id, '_taxonomies', true );
			$parser_settings = get_post_meta( $import_id, '_parser_settings', true );
			$template_settings = get_post_meta( $import_id, '_template_settings', true );
		}else{
			$import_settings = '';
			$mapped_fields = '';
			$attachments = '';
			$taxonomies = '';
			$parser_settings = '';
			$template_settings = '';
		}

		$wpdb->query( $wpdb->prepare("
			INSERT INTO `" . $wpdb->prefix . "importer_log` (importer_name, object_id, template,type,file, version, row, src, value, created, import_settings, mapped_fields, attachments, taxonomies, parser_settings, template_settings)
			VALUES(%s, %d, %s, %s, %s, %d, %d, '', %s, NOW(), %s, %s, %s, %s, %s, %s);" ,
		$importer->post->post_name, $import_id, $template['template'], $template['template_type'], $template['import_file'], $version, $row, serialize( $record ), serialize( $import_settings ), serialize( $mapped_fields), serialize( $attachments ), serialize( $taxonomies ), serialize( $parser_settings ), serialize( $template_settings ) ) );
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
		$row       = $wpdb->get_row( $wpdb->prepare("SELECT row FROM `" . $wpdb->prefix . "importer_log` WHERE object_id=%d AND version=%d GROUP BY row ORDER BY row DESC", $import_id, $version ));

		if ( ! $row ) {
			return 0;
		}

		return intval($row->row);
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
		$row       = $wpdb->get_row( $wpdb->prepare("SELECT version FROM `" . $wpdb->prefix . "importer_log` WHERE object_id=%d GROUP BY version ORDER BY version DESC", $import_id ));

		if ( ! $row ) {
			return 0;
		}

		return intval($row->version);
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

		return $wpdb->get_results( "SELECT version, type, file,  created, COUNT(row) as row_total FROM `" . $wpdb->prefix . "importer_log` WHERE object_id='{$importer_id}' GROUP BY version ORDER BY version DESC", OBJECT );
	}

	static function get_importer_log( $importer_id, $log, $order = 'DESC' ) {

		global $wpdb;

		$importer_id = intval( $importer_id );
		$log         = intval( $log );

		if(!in_array($order, array('DESC', 'ASC'))){
			$order = 'DESC';
		}

		return $wpdb->get_results( "SELECT * FROM `" . $wpdb->prefix . "importer_log` WHERE object_id='{$importer_id}' AND version='{$log}' ORDER BY id $order", OBJECT );
	}

	static function clearLogs(){

		global $wpdb;
		return $wpdb->query($wpdb->prepare("DELETE FROM `" . $wpdb->prefix . "importer_log` WHERE created < %s", date('Y-m-d H:i:s', strtotime('- 1 DAY'))));
	}

}