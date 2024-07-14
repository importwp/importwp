<?php

namespace ImportWP\Common\Util;

use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Importer\State\ImporterState;
use ImportWP\Container;

class Util
{
    function set_time_limit()
    {
        if (function_exists('set_time_limit')) {
            @\set_time_limit(0);
        }
    }

    function set_time_limit_available()
    {
        if (!function_exists('set_time_limit') || !function_exists('ini_get')) {
            return false;
        }

        $tmp_max_execution_time = (ini_get('max_execution_time') == 30) ? 31 : 30;
        @set_time_limit($tmp_max_execution_time);

        return $tmp_max_execution_time == ini_get('max_execution_time');
    }

    function format_time($seconds)
    {
        $t = round($seconds);

        $hours = ($t / 3600);
        $minutes = (floor($t / 60) % 60);
        $seconds = $t % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Get Importer log file path based on specified session id.
     *
     * @param int $id Importer Id
     * @param string $session Importer session id
     * @return string
     */
    public static function get_importer_log_file_path($id, $session)
    {
        /**
         * @var ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');
        return $importer_manager->get_config_path($id, false, $session) . '.logs-' . $session;
    }

    /**
     * Get importer status file path
     *
     * @param int $id Importer Id
     * @return string
     */
    public static function get_importer_status_file_path($id)
    {
        /**
         * @var ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');
        return $importer_manager->get_config_path($id) . '.status';
    }

    /**
     * Write importer status log file message
     *
     * @param int $id Importer id
     * @param string $session Importer session
     * @param string $message Message to log
     * @param string $type
     * @param boolean $counter
     * @return void
     */
    public static function write_status_log_file_message($id, $session, $message, $type = 'S', $counter = false)
    {
        $file_path = self::get_importer_log_file_path($id, $session);
        $fh = fopen($file_path, 'a');

        if (!is_writable($file_path)) {
            throw new \Exception(sprintf(__("Importer status file is not writable: %s.", 'jc-importer'), $file_path));
        }

        if (false === $fh) {
            throw new \Exception(sprintf(__("Unable to open Importer status file: %s.", 'jc-importer'), $file_path));
        }

        fputcsv($fh, [$counter, $type, $message]);
        fclose($fh);
    }

    /**
     * Write importer status to file
     *
     * @param int $id
     * @param ImporterState $state
     * @return void
     */
    public static function write_status_session_to_file($id, $state)
    {
        $session = $state->get_session();
        $session_data = self::get_last_session_from_status($id, $session);
        if ($session_data) {
            $session_data['data'] = $state->get_raw();
            self::update_status_session_file($id, $session_data);
        } else {
            self::update_status_session_file($id, [
                'start' => '-1',
                'length' => 0,
                'data' => $state->get_raw()
            ]);
        }
    }

    public static function get_last_session_from_status($id, $session)
    {

        $file = self::get_importer_status_file_path($id);
        if (!file_exists($file)) {
            return false;
        }

        $line = '';

        $f = fopen($file, 'r');
        $cursor = -1;

        fseek($f, $cursor, SEEK_END);
        $char = fgetc($f);

        /**
         * Trim trailing newline chars of the file
         */
        while ($char === "\n" || $char === "\r") {
            fseek($f, $cursor--, SEEK_END);
            $char = fgetc($f);
        }

        /**
         * Read until the start of file or first newline char
         */
        while ($char !== false && $char !== "\n" && $char !== "\r") {
            /**
             * Prepend the new char
             */
            $line = $char . $line;
            fseek($f, $cursor--, SEEK_END);
            $char = fgetc($f);
        }

        $json_data = json_decode($line, true);

        $ftell = ftell($f);
        $result = isset($json_data['id']) && $json_data['id'] === $session ? ['start' => $ftell > 1 ? $ftell : 0, 'length' => strlen($line), 'data' => $json_data] : false;

        fclose($f);
        return $result;
    }

    public static function update_status_session_file($id, $data)
    {
        $file = self::get_importer_status_file_path($id);

        if (false === file_exists($file) && false === file_put_contents($file, '')) {
            throw new \Exception(sprintf(__("Unable to create status file: %s", 'jc-importer'), $file));
        }

        if (!is_writable($file)) {
            throw new \Exception(sprintf(__("Unable to write status file: %s", 'jc-importer'), $file));
        }

        $f = fopen($file, 'r+');

        $session_data = json_encode($data['data']);

        if ($data['start'] > -1) {

            // get end of file after existing status
            fseek($f, $data['start'] + $data['length']);
            $end_content = fgets($f);

            if (empty($end_content)) {
                $end_content = "\n";
            }

            fseek($f, $data['start'], SEEK_SET);
            fputs($f, $session_data . $end_content);
        } else {
            fseek($f, 0, SEEK_END);
            fputs($f, $session_data . "\n");
        }

        // end file here
        ftruncate($f, ftell($f));

        fclose($f);
    }
}
