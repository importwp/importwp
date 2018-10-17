<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 16/08/2017
 * Time: 09:35
 */

class IWP_Debug {

	/**
	 * Toggle debug, true = show all timings, group_name = show that group only
	 *
	 * @var mixed
	 */
	static $_debug = false;

	static $_timing = [];
	static $_start = -1;

	/**
	 * Add checkpoint for timer
	 *
	 * @param string $str Timer Message
	 * @param string $group Timer Group
	 */
	static function timer( $str, $group = '' ) {

		if ( self::$_debug === false ) {
			return;
		}

		if(self::$_start < 0){
			self::$_start = microtime(true);
		}

		$curr_timing = [
			'time'  => microtime( true ),
			'memory' => memory_get_usage(true),
			'name'  => $str,
			'group' => $group,
		];

		if(!empty(self::$_timing)){

			// output time from previous section to current section
			$contents = self::$_timing['name'] . ", ";
			$contents .= self::$_timing['group'] . ", ";
			$contents .= sprintf( " %f, ", $curr_timing['time'] - self::$_timing['time'] );
			$contents .= self::convert(self::$_timing['memory']);
			$contents .= "\n";

			file_put_contents( JCI()->get_tmp_dir() . DIRECTORY_SEPARATOR . 'debug-' . JCI()->importer->get_ID() . '-' . JCI()->importer->get_version()  . '.txt', $contents, FILE_APPEND );

			self::$_timing = [];
		}

		self::$_timing = $curr_timing;
	}

	/**
	 * Output list of all timed checkpoints
	 *
	 * @param string $prefix
	 */
	static function timer_log( $prefix = '' ) {

		if ( self::$_debug === false ) {
			return;
		}

		$contents = "";

		if(!empty(self::$_timing)){

			// output time from previous section to current section
			$contents .= self::$_timing['name'] . ", ";
			$contents .= self::$_timing['group'] . ", ";
			$contents .= sprintf( " %f, ", microtime( true ) - self::$_timing['time'] );
			$contents .= self::convert(self::$_timing['memory']);
			$contents .= "\n";

			file_put_contents( JCI()->get_tmp_dir() . DIRECTORY_SEPARATOR . 'debug-' . JCI()->importer->get_ID() . '-' . JCI()->importer->get_version()  . '.txt', $contents, FILE_APPEND );

			self::$_timing = [];
		}

		$contents .= "Total: " . round( microtime( true ) - self::$_start, 2 ) . "\n";

		file_put_contents( JCI()->get_tmp_dir() . DIRECTORY_SEPARATOR . 'debug-' . JCI()->importer->get_ID() . '-' . JCI()->importer->get_version()  . '.txt', $contents, FILE_APPEND );
	}

	public static function convert($size)
	{
		$unit=array('b','kb','mb','gb','tb','pb');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	}

}