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
  static $_debug = 'core';

  static $_timings = [];

  /**
   * Add checkpoint for timer
   */
  static function timer($str, $group = '') {

    if (self::$_debug === FALSE) {
      return;
    }

    self::$_timings[] = [
      'time'  => microtime(TRUE),
      'name'  => $str,
      'group' => $group,
    ];
  }

  /**
   * Output list of all timed checkpoints
   */
  static function timer_log($prefix = '') {

    if (self::$_debug === FALSE) {
      return;
    }

    if(!empty($prefix)){
      $prefix .= '-';
    }

    $total = '';
    $timing_count = count(self::$_timings);
    if($timing_count > 1){

      $total = round(self::$_timings[$timing_count - 1]['time'] - self::$_timings[0]['time'], 2) . '-';
    }

    $contents = self::output_timings();
    file_put_contents(JCI()->get_plugin_dir() . '/app/tmp/debug-' . $prefix . $total . time() . '.txt', $contents, FILE_APPEND);
  }

  private static function output_timings() {

    ob_start();

    if (!empty(self::$_timings)) {
      $last_time = FALSE;
      foreach (self::$_timings as $timing) {

        if (
          self::$_debug === TRUE
          || self::$_debug === $timing['group']
        ) {

          if ($last_time !== FALSE) {
            echo sprintf(" %f\n", $timing['time'] - $last_time);
          }
          $last_time = $timing['time'];

          echo $timing['name'];
          if (!empty($timing['group'])) {
            echo " - " . $timing['group'];
          }

          echo "\n";
        }
      }
    }

    return ob_get_clean();
  }

}