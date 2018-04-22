<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 13/08/2017
 * Time: 16:10
 */

class IWP_Status {

	private static $_cache_key = '';
	private static $_cache = array();
	private static $_fh = null;

	static function has_status( $status, $importer_id = null, $version = null ) {

		$data = self::read_file( $importer_id, $version );

		return isset( $data['status'] ) && $data['status'] === $status ? true : false;
	}

	static function read_file( $importer_id = null, $version = null ) {

		if ( ( $data = self::read_cache( $importer_id, $version ) ) !== false ) {
			return $data;
		}

		$status_file = self::get_file_path( $importer_id, $version );
		if ( ! file_exists( $status_file ) ) {
			return false;
		}

		$fh     = self::get_file_handle( $importer_id, $version );
		$status = fread( $fh, filesize( $status_file ) );
		$status = json_decode( $status, true );

		return $status;
	}

	private static function read_cache( $importer_id = null, $version = null ) {

		$cache_key = self::generate_cache_key( $importer_id, $version );
		if ( self::$_cache_key === $cache_key ) {
			return self::$_cache;
		}

		return false;
	}

	public static function reset_handle(){
		if(self::$_fh != null){
			flock(self::$_fh, LOCK_UN);
			self::$_fh = null;
		}
	}

	private static function generate_cache_key( $importer_id = null, $version = null ) {

		$importer_id = is_null( $importer_id ) ? JCI()->importer->get_ID() : intval( $importer_id );
		$version     = is_null( $version ) ? JCI()->importer->get_version() : intval( $version );

		return sprintf( "%d-%d", $importer_id, $version );
	}

	static function get_file_path( $importer_id = null, $version = null ) {

		$importer_id = is_null( $importer_id ) ? JCI()->importer->get_ID() : intval( $importer_id );
		$version     = is_null( $version ) ? JCI()->importer->get_version() : intval( $version );

		return JCI()->get_tmp_dir() . DIRECTORY_SEPARATOR . 'status-' . $importer_id . '-' . $version . '.json';
	}

	static function get_file_handle( $importer_id = null, $version = null ) {

		if ( is_null( self::$_fh ) ) {
			self::get_lock( $importer_id, $version );
		}

		return self::$_fh;
	}

	static function get_lock( $importer_id = null, $version = null ) {

		$file_path = self::get_file_path( $importer_id, $version );

		if ( ! file_exists( $file_path ) ) {
			file_put_contents( $file_path, json_encode( array( 'status' => 'started' ) ) );
		}

		self::$_fh = fopen( $file_path, 'r+' );

		if ( flock( self::$_fh, LOCK_EX | LOCK_NB ) ) {
			return true;
		}

		return false;
	}

	static function write_file( $data, $importer_id = null, $version = null ) {

		// Debug show memory usage
		if(defined('IWP_DEBUG') && IWP_DEBUG === true){
			$data['peak_memory_usage'] = memory_get_peak_usage();
			$data['memory_usage'] = memory_get_usage();
		}

		$status_file = self::get_file_path( $importer_id, $version );
		self::update_cache( $data, $importer_id, $version );

		$json = json_encode( $data );

		$fh = self::get_file_handle( $importer_id, $version );
		ftruncate( $fh, 0 );
		rewind( $fh );

		return fwrite( $fh, $json );
	}

	private static function update_cache( $data, $importer_id = null, $version = null ) {

		$cache_key = self::generate_cache_key( $importer_id, $version );

		self::$_cache_key = $cache_key;
		self::$_cache     = $data;
	}
}