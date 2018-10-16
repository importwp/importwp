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

	static $_timings = [];

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

		self::$_timings[] = [
			'time'  => microtime( true ),
			'memory' => memory_get_usage(true),
			'name'  => $str,
			'group' => $group,
		];
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

		if ( ! empty( $prefix ) ) {
			$prefix .= '-';
		}

		$total        = '';
		$timing_count = count( self::$_timings );
		if ( $timing_count > 1 ) {

			$total = round( self::$_timings[ $timing_count - 1 ]['time'] - self::$_timings[0]['time'], 2 ) . '-';
		}

		$contents = self::output_timings();
		file_put_contents( JCI()->get_tmp_dir() . DIRECTORY_SEPARATOR . 'debug-' . $prefix . $total . time() . '.txt', $contents, FILE_APPEND );
	}

	/**
	 * Display list of timings
	 *
	 * If debug true display all timings, otherwise display $_debug group name if not false.
	 *
	 * @return string
	 */
	private static function output_timings() {

		ob_start();

		if ( ! empty( self::$_timings ) ) {
			$last_time = false;
			foreach ( self::$_timings as $timing ) {

				if (
					self::$_debug === true
					|| self::$_debug === $timing['group']
				) {

					if ( $last_time !== false ) {
						echo sprintf( " %f\n", $timing['time'] - $last_time );
					}
					$last_time = $timing['time'];
					echo self::convert($timing['memory']). "\n";

					echo $timing['name'];
					if ( ! empty( $timing['group'] ) ) {
						echo " - " . $timing['group'];
					}

					echo "\n";
				}
			}
		}

		return ob_get_clean();
	}

	public static function convert($size)
	{
		$unit=array('b','kb','mb','gb','tb','pb');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	}

}