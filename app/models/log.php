<?php
class ImportLog{

	static $config;

	static function init(&$config){
		self::$config = $config;
	}

	static function insert($import_id, $row, $record){

		global $wpdb;

		$template = ImporterModel::getImportSettings($import_id);
		$importer = ImporterModel::getImporter($import_id);

		if($row == 1){
			// increase next row
			$version = self::get_current_version($import_id);
			$version++;
		}else{
			$version = self::get_current_version($import_id);
		}


		$wpdb->query("
			INSERT INTO `".$wpdb->prefix."importer_log` (importer_name, object_id, template,type,file, version, row, src, value, created) 
			VALUES('".$importer->post->post_name."', '".$import_id."', '".$template['template']."', '".$template['template_type']."', '".$template['import_file']."', '".$version."', '".$row."', '', '".mysql_real_escape_string(serialize($record))."', NOW());");
	}

	static function get_current_version($import_id){
		global $wpdb;
		$import_id = intval($import_id);
		$row = $wpdb->get_row("SELECT version FROM `".$wpdb->prefix."importer_log` WHERE object_id='".$import_id."' GROUP BY version ORDER BY version DESC");

		if(!$row){
			return 0;
		}
		return $row->version;
	}

	static function scaffold(){

		global $wpdb;

		$wpdb->show_errors();
		$charset_collate = "";

		if ( ! empty($wpdb->charset) ) $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) ) $charset_collate .= " COLLATE $wpdb->collate";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		$sql = "CREATE TABLE `".$wpdb->prefix."importer_log` (
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

		dbDelta($sql);
	}
}