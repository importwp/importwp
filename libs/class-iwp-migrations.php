<?php
class IWP_Migrations{

	private $_version = 0;
	private $_migrations = array();

	public function __construct() {

		$this->_migrations[] = array($this, 'migration_01');
		$this->_migrations[] = array($this, 'migration_02');
		$this->_migrations[] = array($this, 'migration_03');

		$this->_version = count($this->_migrations);
	}

	public function install(){

		//run through schema migrations only
		$this->migrate(false);
	}

	public function uninstall(){
		global $wpdb;
		$wpdb->query("DROP TABLE IF EXISTS `" . $wpdb->prefix . "importer_log`;");
		$wpdb->query("DROP TABLE IF EXISTS `" . $wpdb->prefix . "importer_files`;");
		delete_site_option('iwp_db_version');
		delete_site_option('jci_db_version');
	}

	public function migrate($migrate_data = true){

		$verion_key = 'iwp_db_version';
		$version = intval( get_site_option( 'iwp_db_version', get_site_option( 'jci_db_version', 0 ) ) );

		if($version < count($this->_migrations)){

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			for($i = 0; $i < count($this->_migrations); $i++){

				$migration_version = $i+1;
				if($version < $migration_version){

					set_time_limit(0);

					// Run migration
					call_user_func($this->_migrations[$i], $migrate_data);

					// Flag as migrated
					update_site_option($verion_key, $migration_version);
				}
			}
		}
	}

	public function get_charset(){

		global $wpdb;
		$charset_collate = "";

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}
		return $charset_collate;
	}

	public function migration_01($migrate_data = true){

		global $wpdb;
		$charset_collate = $this->get_charset();

		$sql = "CREATE TABLE `" . $wpdb->prefix . "importer_log` (
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
					  `import_settings` TEXT NULL,
					  `mapped_fields` TEXT NULL,
					  `attachments` TEXT NULL,
					  `taxonomies` TEXT NULL,
					  `parser_settings` TEXT NULL,
					  `template_settings` TEXT NULL,
					  PRIMARY KEY (`id`)
					) $charset_collate; ";

		dbDelta( $sql );

		$sql = "CREATE TABLE `" . $wpdb->prefix . "importer_files`(  
					  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					  `importer_id` INT(11),
					  `author_id` INT(11),
					  `mime_type` VARCHAR(255),
					  `name` VARCHAR(255),
					  `src` VARCHAR(255),
					  `created` DATETIME,
					  PRIMARY KEY (`id`)
					) $charset_collate;";
		dbDelta( $sql );
	}

	public function migration_02($migrate_data = true){

		if(!$migrate_data){
			return;
		}

		global $wpdb;

		// return list of importer file links
		$importer_file_ids = $this->migration_02_get_importer_file_ids();

		// get ids of all importers and fetch all their import files
		$importers = $wpdb->get_col( "SELECT id FROM " . $wpdb->posts . " WHERE post_type = 'jc-imports'" );

		//
		$or_query = '';
		if ( ! empty( $importers ) ) {
			$or_query = "
				OR (
					post_type = 'attachment'
					AND post_parent IN ( " . implode( ',', $importers ) . ")
				)
			";
		}

		$results = $wpdb->get_results( "
			SELECT ID, guid, post_parent, post_author, post_mime_type, post_name, post_date 
			FROM " . $wpdb->posts . " 
			WHERE 
				(post_type = 'jc-import-files')
				" . $or_query . "
		" );


		if ( ! empty( $results ) ) {

			// print_r($importer_attachments);
			$upload_dir = wp_upload_dir();
			$baseurl    = $upload_dir['baseurl'];
			$records    = array();

			foreach ( $results as $importer ) {

				$src = $importer->guid;
				if ( strpos( $src, $baseurl ) === 0 ) {
					$src = substr( $src, strlen( $baseurl ) );
				}
				// $record = array(
				$importer_id    = $importer->post_parent;
				$author_id      = $importer->post_author;
				$mime           = $importer->post_mime_type;
				$name           = $importer->post_name;
				$attachment_src = $src;
				$created        = $importer->post_date;
				// );

				$query_result = $wpdb->query( $wpdb->prepare( "INSERT INTO `" . $wpdb->prefix . "importer_files`(importer_id, author_id, mime_type, name, src, created) VALUES(%d, %d, %s, %s, %s, %s)", $importer_id, $author_id, $mime, $name, $attachment_src, $created ) );

				// check if importer_file_id exists in importer settings array
				if ( is_array( $importer_file_ids ) && array_key_exists( $importer->ID, $importer_file_ids ) ) {
					$importer_file_ids[ $importer->ID ] = $wpdb->insert_id;
					set_transient( 'jci_db_import_file_ids', $importer_file_ids );
				}

				if ( $query_result ) {
					wp_delete_post( $importer->ID, true );
				}
			}
		}

		// loop through all importer_file meta data
		$importer_settings = $wpdb->get_results( "SELECT * FROM `" . $wpdb->prefix . "postmeta` WHERE meta_key='_import_settings'" );
		if ( $importer_settings ) {

			foreach ( $importer_settings as $settings ) {

				$post_id = $settings->post_id;
				$value   = maybe_unserialize( $settings->meta_value );

				if ( is_array( $importer_file_ids ) && array_key_exists( $value['import_file'], $importer_file_ids ) ) {
					$value['import_file'] = $importer_file_ids[ $value['import_file'] ];
					update_post_meta( $post_id, '_import_settings', $value );
					continue;
				}
			}
		}
	}

	/**
	 * Fetch list of current importer_file id's
	 * @return array
	 */
	private function migration_02_get_importer_file_ids() {
		global $wpdb;

		$transient = get_transient( 'jci_db_import_file_ids' );
		if ( get_transient( 'jci_db_import_file_ids' ) === false ) {

			$importer_files = array();

			$importer_settings = $wpdb->get_results( "SELECT * FROM `" . $wpdb->prefix . "postmeta` WHERE meta_key='_import_settings'" );
			if ( $importer_settings ) {

				foreach ( $importer_settings as $settings ) {
					$value                                   = maybe_unserialize( $settings->meta_value );
					$importer_files[ $value['import_file'] ] = null;
				}
			}
			set_transient( 'jci_db_import_file_ids', $importer_files );

			return $importer_files;

		} else {
			return $transient;
		}
	}

	/**
	 * Migration 03
	 * Refactor logs table, so duplication of data.
	 *
	 * @since 1.1.0
	 */
	public function migration_03($migrate_data = true){

		global $wpdb;
		$wpdb->query("ALTER TABLE `" . $wpdb->prefix . "importer_log` 
		DROP COLUMN importer_name, 
		DROP COLUMN src, 
		DROP COLUMN template, 
		DROP COLUMN type, 
		DROP COLUMN import_settings,
		DROP COLUMN mapped_fields,
		DROP COLUMN attachments,
		DROP COLUMN taxonomies,
		DROP COLUMN parser_settings,
		DROP COLUMN template_settings");

		// TODO: Create new Tables (_importer_logs , _importer_log_data)
		// TODO: Migrate existing logs into new format
		// TODO: Delete old table
	}
}