<?php 

class JCI_DB_Schema{

	private $config = null;

	public function __construct( &$config ) {
		$this->config = $config;
	}

	/**
	 * Install Importer Tables in db
	 */
	public function install(){

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
	 * @param  int $old_version 
	 */
	public function upgrade($old_version){

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

		switch($old_version){
			case 0:
			case 1:
				// db version 2
				$sql = "ALTER TABLE `" . $wpdb->prefix . "importer_log`   
					  ADD COLUMN `import_settings` TEXT NULL,
					  ADD COLUMN `mapped_fields` TEXT NULL,
					  ADD COLUMN `attachments` TEXT NULL,
					  ADD COLUMN `taxonomies` TEXT NULL,
					  ADD COLUMN `parser_settings` TEXT NULL,
					  ADD COLUMN `template_settings` TEXT NULL;";

  				$sql .= "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "importer_files`(  
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

				$this->migrate_ver_2_data();
			break;
		}
	}

	private function migrate_ver_2_data(){

		set_time_limit(0);

		global $wpdb;

		$results = array();

		$q1 = new WP_Query(array(
			'post_type' => 'jc-imports',
			'fields' => 'ids'
		));
		$importers = $q1->posts;

		$q2 = new WP_Query(array(
			'post_type'   => 'jc-import-files',
            'post_status' => 'any',
            'posts_per_page' => -1
		));

		if(!empty($importers)){
			$importer_attachments = new WP_Query( array(
	            'post_type'   => 'attachment',
	            'post_parent__in' => $importers,
	            'post_status' => 'any',
	            'posts_per_page' => -1
	        ) );
	        $results = array_merge($results, $importer_attachments->posts);
		}

		if(!empty($q2->posts)){
			$results = array_merge($results, $q2->posts);
		}

		if(!empty($results)){
			// print_r($importer_attachments);
			$upload_dir = wp_upload_dir();
			$baseurl = $upload_dir['baseurl'];
			$records = array();

			foreach($results as $importer){

				$src = $importer->guid;
				if(strpos($src, $baseurl) === 0){
					$src = substr($src, strlen($baseurl));
				}
				// $record = array(
				$importer_id = $importer->post_parent;
				$author_id = $importer->post_author;
				$mime = $importer->post_mime_type;
				$name = $importer->post_name;
				$attachment_src = $src;
				$created = $importer->post_date;
				// );

				$query_result = $wpdb->query( $wpdb->prepare( "INSERT INTO `" . $wpdb->prefix . "importer_files`(importer_id, author_id, mime_type, name, src, created) VALUES(%d, %d, %s, %s, %s, %s)", $importer_id, $author_id, $mime, $name, $attachment_src, $created ) );

				if($query_result){
					wp_delete_post( $importer->ID, true );
				}
			}
		}
	}
}