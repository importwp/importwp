<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 13/08/2017
 * Time: 16:10
 */

class IWP_Status{

	private static $_cache_key = '';
	private static $_cache = array();

	static function has_status($status, $importer_id = null, $version = null){

		$data = self::read_file($importer_id, $version);
		return isset($data['status']) && $data['status'] === $status ? true : false;
	}

	static function write_file($data, $importer_id = null, $version = null){

		$status_file = self::get_file_path($importer_id, $version);
		self::update_cache($data, $importer_id, $version);
		return file_put_contents($status_file, json_encode($data));
	}

	static function read_file($importer_id = null, $version = null){

		if(($data = self::read_cache($importer_id, $version)) !== false){
			return $data;
		}

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

	private static function generate_cache_key($importer_id = null, $version = null){

		$importer_id = is_null($importer_id) ? JCI()->importer->get_ID() : intval($importer_id);
		$version = is_null($version) ? JCI()->importer->get_version() : intval($version);

		return sprintf("%d-%d", $importer_id, $version);
	}

	private static function update_cache($data, $importer_id = null, $version = null){

		$cache_key = self::generate_cache_key($importer_id, $version);

		self::$_cache_key = $cache_key;
		self::$_cache     = $data;
	}

	private static function read_cache($importer_id = null, $version = null){

		$cache_key = self::generate_cache_key($importer_id, $version);
		if( self::$_cache_key === $cache_key){
			return self::$_cache;
		}

		return false;
	}
}