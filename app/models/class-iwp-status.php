<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 13/08/2017
 * Time: 16:10
 */

class IWP_Status{

	static function write_file($data, $importer_id = null, $version = null){

		$status_file = self::get_file_path($importer_id, $version);
		return file_put_contents($status_file, json_encode($data));
	}

	static function read_file($importer_id = null, $version = null){

		$status_file = self::get_file_path($importer_id, $version);
		if(!file_exists($status_file)){
			return false;
		}

		$status = json_decode(file_get_contents($status_file), true);
		return $status;
	}

	static function get_file_path($importer_id = null, $version = null){

		$importer_id = is_null($importer_id) ? JCI()->importer->get_ID() : intval($importer_id);
		$version = is_null($version) ? JCI()->importer->get_version() : intval($version);

		return JCI()->get_plugin_dir() . '/app/tmp/status-' . $importer_id.'-'.$version;
	}
}