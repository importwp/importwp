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
	}

	/**
	 * Upgrade Existing Databases
	 * @param  int $old_version 
	 */
	public function upgrade($old_version){

		global $wpdb;

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
				$wpdb->query($sql);
			break;
		}
	}
}