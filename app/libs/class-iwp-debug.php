<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 16/08/2017
 * Time: 09:35
 */

class IWP_Debug{

	static $_debug = false;

	/**
	 * Add checkpoint for timer
	 */
	static function timer($str){

		if(self::$_debug !== true){
			return;
		}

		global $prof_timing, $prof_names;
		$prof_timing[] = microtime(true);
		$prof_names[] = $str;
	}

	/**
	 * Output list of all timed checkpoints
	 */
	static function timer_log(){

		if(self::$_debug !== true){
			return;
		}

		global $prof_timing, $prof_names;
		$size = count($prof_timing);
		ob_start();
		for($i=0;$i<$size - 1; $i++)
		{
			echo "{$prof_names[$i]}\n";
			echo sprintf(" %f\n", $prof_timing[$i+1]-$prof_timing[$i]);
		}
		echo "  {$prof_names[$size-1]}\n";
		$contents = ob_get_clean();

		file_put_contents(JCI()->get_plugin_dir() . '/app/tmp/debug-'.time().'.txt', $contents, FILE_APPEND);
	}

}