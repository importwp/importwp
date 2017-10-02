<?php

class JCI_DB_Schema {

	private $config = null;

	public function __construct( &$config ) {
		$this->config = $config;
	}

	/**
	 * Install Importer Tables in db
	 */
	public function install() {

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
			  `import_settings` TEXT NULL,
			  `mapped_fields` TEXT NULL,
			  `attachments` TEXT NULL,
			  `taxonomies` TEXT NULL,
			  `parser_settings` TEXT NULL,
			  `template_settings` TEXT NULL,
			  PRIMARY KEY (`id`)
			) $charset_collate; ";

		dbDelta( $sql );

		$sql = "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "importer_files`(  
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

	/**
	 * Upgrade Existing Databases
	 *
	 * @param  int $old_version
	 */
	public function upgrade( $old_version ) {

		global $wpdb;

		$charset_collate = "";

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$migrate_versions = array();

		switch ( $old_version ) {
			case 0:
			case 1:
				// db version 2
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

				$migrate_versions[] = 2;
				break;
		}

		if ( ! empty( $migrate_versions ) ) {
			add_option( 'jci_db_migrate', serialize( $migrate_versions ) );
		}
	}

	public function db_migration() {

		$migrate_versions = maybe_unserialize( get_option( 'jci_db_migrate', false ) );

		if ( is_array( $migrate_versions ) ) {
			foreach ( $migrate_versions as $version ) {

				switch ( $version ) {
					case 2:
						$this->migrate_ver_2_data();
						break;
				}
			}
		}

		delete_option( 'jci_db_migrate' );
	}

	private function migrate_ver_2_data() {

		set_time_limit( 0 );

		global $wpdb;

		// return list of importer file links
		$importer_file_ids = $this->get_migrate_2_importer_file_ids();

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

		return true;
	}

	/**
	 * Fetch list of current importer_file id's
	 * @return array
	 */
	private function get_migrate_2_importer_file_ids() {
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
}