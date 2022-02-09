<?php

namespace ImportWP\Common\Importer;

use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;

class ImporterStatus
{
    /**
     * Importer Id
     *
     * @var int
     */
    private $importer_id;

    /**
     * Unique importer status hash
     *
     * @var string
     */
    protected $session;

    /**
     * List of all warnings
     *
     * @var int
     */
    protected $warnings;

    /**
     * List of all errors
     *
     * @var int
     */
    protected $errors;

    /**
     * Creation timestamp
     *
     * @var int
     */
    protected $timestamp;

    /**
     * Importer start record
     *
     * @var int
     */
    protected $start;

    /**
     * Importer end record
     *
     * @var int
     */
    protected $end;

    /**
     * Record import counter
     *
     * @var int
     */
    protected $counter;

    /**
     * Record insert counter
     *
     * @var int
     */
    protected $inserts;

    /**
     * Record update counter
     *
     * @var int
     */
    protected $updates;

    /**
     * Record delete counter
     *
     * @var int
     */
    protected $deletes;

    /**
     * Record skip counter
     *
     * @var int
     */
    protected $skips;

    /**
     * Total number of records to delete
     *
     * @var int
     */
    protected $delete_total;

    /**
     * Total number of records to be imported.
     *
     * @var int
     */
    protected $total;

    /**
     * Current Status
     *
     * @var string Possible options are 'init', 'running', 'timeout', 'paused', 'cancelled', 'complete.
     */
    protected $status;

    /**
     * What the importing is doing
     *
     * @var string Possible options are 'processing', 'importing', 'deleting', 'complete'.
     */
    protected $section;

    /**
     * Log Message
     *
     * @var string
     */
    protected $message;

    /**
     * Elapsed time of import
     *
     * @var int
     */
    protected $elapsed_time;

    /**
     * List of status changes to be applied
     *
     * @var array
     */
    private $_changes;

    /**
     * @var int $memory
     */
    private $memory;

    public function __construct($importer_id, $data = null)
    {
        $this->importer_id = $importer_id;
        $this->setup_data($data);
    }

    private function setup_data($data)
    {
        $this->session = $this->getDefault($data, 'session', null);
        $this->status = $this->getDefault($data, 'status', null);
        $this->section = $this->getDefault($data, 'section', null);

        $this->warnings = $this->getDefault($data, 'warnings', 0);
        $this->errors = $this->getDefault($data, 'errors', 0);
        $this->timestamp = $this->getDefault($data, 'timestamp', time());
        $this->total = $this->getDefault($data, 'total');
        $this->counter = $this->getDefault($data, 'counter', 0);
        $this->inserts = $this->getDefault($data, 'inserts', 0);
        $this->updates = $this->getDefault($data, 'updates', 0);
        $this->deletes = $this->getDefault($data, 'deletes', 0);
        $this->skips = $this->getDefault($data, 'skips', 0);
        $this->delete_total = $this->getDefault($data, 'delete_total', 0);
        $this->start = $this->getDefault($data, 'start');
        $this->end = $this->getDefault($data, 'end');
        $this->version = $this->getDefault($data, 'version', 0);
        $this->elapsed_time = $this->getDefault($data, 'elapsed_time', 0);
        $this->message = $this->getDefault($data, 'message', null);
        $this->memory = $this->getDefault($data, 'memory', 0);
    }

    public function is_cancelled()
    {
        if ($this->has_status('cancelled')) {
            // since status is read as cancelled, remove paused meta flag
            delete_post_meta($this->importer_id, '_iwp_cancelled_' . $this->session, 'yes');

            $this->write_to_file();
            return true;
        }

        // check to see if paused meta flag has been set, if so try and change status to cancelled again
        $cancelled = get_post_meta($this->importer_id, '_iwp_cancelled_' . $this->session, true);
        if ('yes' === $cancelled) {
            $this->set_status('cancelled');
            $this->save();

            $this->write_to_file();
            return true;
        }

        return false;
    }

    public function is_paused()
    {
        if ($this->has_status('paused')) {
            // since status is read as paused, remove paused meta flag
            delete_post_meta($this->importer_id, '_iwp_paused_' . $this->session, 'yes');
            return true;
        }

        // check to see if paused meta flag has been set, if so try and change status to paused again
        $paused = get_post_meta($this->importer_id, '_iwp_paused_' . $this->session, true);
        if ('yes' === $paused) {
            $this->set_status('paused');
            $this->save();
            return true;
        }

        return false;
    }

    public function has_status($status)
    {
        return $status === $this->status;
    }

    public function get_status()
    {
        if (!in_array($this->status, ['cancelled', 'paused', 'running']) && !empty($this->get_session_id())) {

            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;
            $rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE (meta_key LIKE '\_" . $this->get_session_id() . "\_%\_chunk' OR meta_key LIKE '\_" . $this->get_session_id() . "\_%\_delete') AND post_id=" . $this->importer_id);
            if (!empty($rows)) {
                foreach ($rows as $item) {
                    $item = maybe_unserialize($item);
                    if (isset($item['status']) && $item['status'] === 'running') {
                        return 'running';
                    }
                }
            }
        }

        return $this->status;
    }

    public function set_start($start)
    {
        $this->_changes[] = 'start';
        $this->start = $start;
        $this->generate_total();
    }

    public function has_section($section)
    {
        return $section === $this->section;
    }

    public function set_end($end)
    {
        $this->_changes[] = 'end';
        $this->end = $end;
        $this->generate_total();
    }

    public function set_status($status)
    {
        $this->status = $status;
        $this->_changes[] = 'status';
    }

    public function set_section($section)
    {
        $this->section = $section;
        $this->_changes[] = 'section';
    }

    private function generate_total()
    {
        if (intval($this->start) >= 0 && intval($this->end) > 0) {
            $this->_changes[] = 'total';
            $this->total = $this->end - $this->start;
        }
    }

    private function getDefault($data, $key, $default = null)
    {
        return isset($data[$key]) ? $data[$key] : $default;
    }

    public function get_session_id()
    {
        return $this->session;
    }

    public function get_counter()
    {
        if (!empty($this->get_session_id())) {
            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;

            $rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_" . $this->get_session_id() . "\_%\_chunk' AND post_id=" . $this->importer_id);
            if (!empty($rows)) {
                return array_reduce($rows, function ($carry, $item) {
                    $item = maybe_unserialize($item);
                    return $carry += intval(isset($item['counter']) ? $item['counter'] : 0);
                }, 0);
            }
        }

        return $this->counter;
    }

    public function get_running_chunks()
    {
        $output = [0, 0, 0];
        if (!empty($this->get_session_id())) {
            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;

            $rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE (meta_key LIKE '\_" . $this->get_session_id() . "\_%\_chunk' OR meta_key LIKE '\_" . $this->get_session_id() . "\_%\_delete') AND post_id=" . $this->importer_id);
            if (!empty($rows)) {


                /**
                 * @var Properties $properties
                 */
                $properties = Container::getInstance()->get('properties');
                $time_limit = $properties->chunk_timeout;

                return array_reduce($rows, function ($carry, $item) use ($time_limit) {
                    $item = maybe_unserialize($item);
                    if (isset($item['status'])) {
                        $carry[0] += ($item['status'] == 'running' && time() - $time_limit <= $item['time'] ? 1 : 0);
                        $carry[1] += ($item['status'] != 'complete' ? 1 : 0);
                        $carry[2] += 1;
                    }
                    return $carry;
                }, $output);
            }
        }

        return $output;
    }

    public function get_inserts()
    {
        if (!empty($this->get_session_id())) {
            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;

            $rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_" . $this->get_session_id() . "\_%\_chunk' AND post_id=" . $this->importer_id);
            if (!empty($rows)) {
                return array_reduce($rows, function ($carry, $item) {
                    $item = maybe_unserialize($item);
                    return $carry += intval(isset($item['inserts']) ? $item['inserts'] : 0);
                }, 0);
            }
        }

        return $this->inserts;
    }

    public function get_updates()
    {
        if (!empty($this->get_session_id())) {
            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;

            $rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_" . $this->get_session_id() . "\_%\_chunk' AND post_id=" . $this->importer_id);
            if (!empty($rows)) {
                return array_reduce($rows, function ($carry, $item) {
                    $item = maybe_unserialize($item);
                    return $carry += intval(isset($item['updates']) ? $item['updates'] : 0);
                }, 0);
            }
        }

        return $this->updates;
    }

    public function get_deletes()
    {
        if (!empty($this->get_session_id())) {
            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;

            $rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_" . $this->get_session_id() . "\_%\_delete' AND post_id=" . $this->importer_id);
            if (!empty($rows)) {
                return array_reduce($rows, function ($carry, $item) {
                    $item = maybe_unserialize($item);
                    return $carry += intval($item['deletes']);
                }, 0);
            }
        }

        return $this->deletes;
    }

    public function get_skips()
    {
        return $this->skips;
    }

    public function get_errors_total()
    {
        if (!empty($this->get_session_id())) {
            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;

            $rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE (meta_key LIKE '\_" . $this->get_session_id() . "\_%\_chunk' OR meta_key LIKE '\_" . $this->get_session_id() . "\_%\_delete') AND post_id=" . $this->importer_id);
            if (!empty($rows)) {
                return array_reduce($rows, function ($carry, $item) {
                    $item = maybe_unserialize($item);
                    return $carry += intval(isset($item['errors']) ? $item['errors'] : 0);
                }, 0);
            }
        }

        return $this->errors;
    }

    public function get_total()
    {
        return $this->total;
    }

    public function output()
    {
        $output = [
            's' => $this->get_status(), // status
            'b' => $this->section,
            'd' => date('Y-m-d H:i:s'), // date
            'c' => $this->get_counter(), // counter
            't' => $this->total, // total
            'e' => $this->get_errors_total(), // errors
            'w' => $this->warnings, // warnings
            'r' => $this->get_deletes(),
            'a' => $this->delete_total,
            'f' => $this->skips,
            'i' => $this->get_inserts(),
            'u' => $this->get_updates(),
            'm' => $this->message,
            'z' => $this->elapsed_time
        ];

        if (defined('WP_DEBUG') && true === WP_DEBUG && $this->memory > 0) {
            $output['x'] = $this->size_formatted($this->memory);
        }

        return $output;
    }

    public function data($action = 'view')
    {
        $output = [
            'session' => $this->session,
            'warnings' => $this->warnings,
            'errors' => $this->get_errors_total(),
            'timestamp' => $this->timestamp,
            'counter' => $this->get_counter(),
            'inserts' => $this->get_inserts(),
            'updates' => $this->get_updates(),
            'deletes' => $this->get_deletes(),
            'skips' => $this->skips,
            'delete_total' => $this->delete_total,
            'total' => $this->total,
            'status' => $action == 'edit' ? $this->status : $this->get_status(),
            'section' => $this->section,
            'version' => $this->version,
            'elapsed_time' => $this->elapsed_time,
            'start' => $this->start,
            'end' => $this->end,
            'message' => $this->message,
        ];

        if (defined('WP_DEBUG') && true === WP_DEBUG && $this->memory > 0) {
            $output['memory'] = $this->memory;
        }

        return $output;
    }

    public function size_formatted($size)
    {
        $size = intval($size);
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    public function save($force = false)
    {
        $session = get_post_meta($this->importer_id, '_iwp_session', true);
        if (false === $this->validate($session)) {
            return false;
        }

        $status_data = $this->data('save');

        if (defined('WP_DEBUG') && true === WP_DEBUG) {

            $memory = memory_get_peak_usage(true);
            if (!isset($status_data['memory']) || intval($status_data['memory']) < $memory) {
                $status_data['memory'] = $memory;
            }
        }

        $post_arr = [
            'ID' => $this->importer_id,
            'post_excerpt' => serialize($status_data)
        ];

        if (false === $force) {
            // TODO: get the raw data and apply only the changed fields
            $raw_status = $this->get_raw_status();
            $current_data = $status_data;

            $tmp_log = '';

            foreach ($this->_changes as $field) {
                $raw_status[$field] = $current_data[$field];
                $tmp_log .= '  -' . $field . '=' . $current_data[$field];
            }

            Logger::write(__CLASS__ . '::save -update ' . $tmp_log, $this->importer_id);

            $post_arr['post_excerpt'] = serialize($raw_status);

            $this->_changes = [];
        } else {
            Logger::write(__CLASS__ . '::save -update-all', $this->importer_id);
        }

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->posts,
            ['post_excerpt' => $post_arr['post_excerpt']],
            ['ID' => $post_arr['ID']],
            ['%s'],
            ['%d']
        );

        do_action('iwp/importer/status/save', $this);

        return $result;
    }

    public function write_to_file()
    {
        $data = $this->get_last_session_from_status($this->get_session_id());
        if ($data) {
            $data['data'] = $this->data('edit');
            $this->update_status_session($data);
        } else {
            $this->update_status_session([
                'start' => '-1',
                'length' => 0,
                'data' => $this->data('edit')
            ]);
        }
    }

    private function get_last_session_from_status($session)
    {

        $file = $this->get_status_file();
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
        $result = isset($json_data['session']) && $json_data['session'] === $session ? ['start' => $ftell > 1 ? $ftell : 0, 'length' => strlen($line), 'data' => $json_data] : false;

        fclose($f);
        return $result;
    }

    private function update_status_session($data)
    {
        $file = $this->get_status_file();
        if (!file_exists($file)) {
            file_put_contents($file, '');
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

    /**
     * Validate the current session against current status
     *
     * @param string $session Optional session to validate against.
     * @return bool
     */
    public function validate($session = null)
    {
        if (is_null($session)) {
            $session = get_post_meta($this->importer_id, '_iwp_session', true);
        }

        if ($this->get_session_id() !== $session) {
            Logger::write(__CLASS__ . '::validate -invalid-session=' . $session . ' -current-session=' . $this->get_session_id(), $this->importer_id);
            return false;
        }

        return true;
    }

    public function record_error($error)
    {
        $this->_changes[] = 'errors';
        $this->_changes[] = 'counter';
        $this->errors++;
        $this->counter++;
        $this->log_row_message($error, 'E');
    }

    public function record_fatal_error($error)
    {
        $this->set_status('error');
        $this->log_row_message($error, 'E');
        $this->save();
    }

    public function get_errors()
    {
        $file_path = $this->get_log_file();
        $row_limit = 0;
        $errors = [];
        if (file_exists($file_path)) {
            $fh = fopen($file_path, 'r');
            if ($fh !== false) {
                while (($data = fgetcsv($fh)) !== false) {

                    // get the last 10 errors
                    if ($row_limit > 0 && count($errors) >= $row_limit) {
                        array_shift($errors);
                    }
                    $errors[] = $data;
                }
                fclose($fh);
            }
        }
        return array_reverse($errors);
    }

    public function record_finished()
    {
        $this->save();
    }

    public function record_time($time_spent)
    {
        $this->_changes[] = 'elapsed_time';
        $this->elapsed_time += ceil($time_spent);
    }

    public function timeout()
    {
        if ($this->chunk_index > -1) {
            $this->timeout_chunk();
        }

        $this->set_status('timeout');
        $this->save();

        $this->write_to_file();
    }

    public function shutdown()
    {
        $this->set_status('shutdown');
        $this->save();

        $this->write_to_file();
    }

    public function pause()
    {
        update_post_meta($this->importer_id, '_iwp_paused_' . $this->session, 'yes');
        $this->set_status('paused');
        $this->save();

        $this->write_to_file();
    }

    public function stop()
    {
        update_post_meta($this->importer_id, '_iwp_cancelled_' . $this->session, 'yes');
        $this->set_status('cancelled');
        $this->save();

        // TODO: Writing status to file at this point can lead to miscount of records
        $this->write_to_file();
    }

    public function resume()
    {
        $this->set_status('timeout');
        $this->save();
    }

    public function complete()
    {
        $this->_changes[] = 'message';
        $this->message = 'complete';
        $this->set_status('complete');
        $this->set_section('complete');
        $this->save();

        $this->write_to_file();
    }

    public function record_success(ParsedData $data)
    {
        $this->_changes[] = 'counter';
        $this->counter++;

        if ($data->isInsert()) {
            $this->_changes[] = 'inserts';
            $this->inserts++;
            $message = apply_filters('iwp/status/record_inserted', 'Record Inserted: #' . $data->getId(), $data->getId(), $data);
            $this->log_row_message($message, 'S');
        }

        if ($data->isUpdate()) {
            $this->_changes[] = 'updates';
            $this->updates++;
            $message = apply_filters('iwp/status/record_updated', 'Record Updated: #' . $data->getId(), $data->getId(), $data);
            $this->log_row_message($message, 'S');
        }
    }

    public function record_delete($id)
    {
        $this->_changes[] = 'deletes';
        $this->deletes++;
        $message = apply_filters('iwp/status/record_deleted', 'Record Deleted: #' . $id, $id);
        $this->log_row_message($message, 'D', $this->deletes);
        $this->save();
    }

    public function record_skip()
    {
        $this->_changes[] = 'counter';
        $this->_changes[] = 'skips';
        $this->counter++;
        $this->skips++;
        $this->log_row_message("Skipped Record", 'S');
        $this->save(true);
    }

    public function set_delete_total($total = 0)
    {
        $this->_changes[] = 'delete_total';
        $this->delete_total = $total;
        $this->save();
    }

    private function log_row_message($message, $type = 'S', $counter = false)
    {
        $file_path = $this->get_log_file();
        $fh = fopen($file_path, 'a');

        if ($counter === false) {
            $counter = ($this->start - 1) + $this->counter;
        }
        $this->_changes[] = 'message';
        $this->message = $message;
        fputcsv($fh, [$counter, $type, $this->message]);
        fclose($fh);
    }

    private function get_raw_status()
    {
        clean_post_cache($this->importer_id);
        $raw_post = get_post($this->importer_id);
        return maybe_unserialize($raw_post->post_excerpt);
    }

    public function refresh()
    {
        $raw_status = $this->get_raw_status();
        if ($raw_status['session'] === $this->session) {
            $this->setup_data($raw_status);
        } else {
            // TODO: Current session has changed
            throw new \Exception("Importer session has changed, unable to continue current import.");
        }
    }

    private function get_log_file()
    {
        /**
         * @var ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');
        return $importer_manager->get_config_path($this->importer_id, false, $this->session) . '.logs-' . $this->session;
    }

    private function get_status_file()
    {
        /**
         * @var ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');
        return $importer_manager->get_config_path($this->importer_id) . '.status';
    }

    private $chunk_index = -1;

    public function get_max_chunks($size, $file_start, $file_end)
    {
        if (!is_null($size) && $size < $file_end - $file_start) {
            return ceil(($file_end - $file_start) / $size);
        }

        return 1;
    }

    public function get_chunk_status($size, $file_start, $file_end)
    {
        if ($this->get_max_chunks($size, $file_start, $file_end) && 1 == 2) {
            $output = [];
            for ($i = 0; $i * $size < $file_end - $file_start; $i++) {
                $chunk_key = '_' . $this->session . '_' . $i . '_chunk';
                $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);

                if ($chunk_status !== false) {
                    $chunk_status = maybe_unserialize($chunk_status);
                    if (!in_array($chunk_status['status'], $output)) {
                        $output[] = $chunk_status;
                    }
                }
            }

            return $output;
        }

        return $this->status;
    }

    public function has_chunk_status($status, $size, $file_start, $file_end)
    {
        $status_list = $this->get_chunk_status($size, $file_start, $file_end);
        if (is_array($status_list) && in_array($status, $status_list)) {
            return true;
        } elseif (!is_array($status_list) && $status_list == $status) {
            return true;
        }
        return false;
    }

    public function get_next_chunk($size, $file_start, $file_end)
    {
        $max_chunks = $this->get_max_chunks($size, $file_start, $file_end);
        if ($max_chunks) {
            for ($i = 0; $i < $max_chunks; $i++) {
                $chunk_key = '_' . $this->session . '_' . $i . '_chunk';
                $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);

                if (!empty($chunk_status)) {
                    $chunk_status = maybe_unserialize($chunk_status);

                    if ($chunk_status['counter'] > $size) {
                        continue;
                    }

                    if ($chunk_status['status']  == 'timeout' || ($chunk_status['status']  != 'complete' && time() - 30 > $chunk_status['time'])) {
                        $chunk_status['status'] = 'running';
                        $chunk_status['time'] = time();
                        update_post_meta($this->importer_id, $chunk_key, $chunk_status);
                        return [$chunk_status['start'] + $chunk_status['counter'], $chunk_status['end'], $i];
                    }
                } else {

                    $start  = $file_start + ($i * $size);
                    $chunk_status = [
                        'status' => 'running',
                        'start' => $start,
                        'end' => min($start + $size, $file_end),
                        'counter' => 0,
                        'inserts' => 0,
                        'updates' => 0,
                        'deletes' => 0,
                        'errors' => 0,
                        'time' => time()
                    ];

                    update_post_meta($this->importer_id, $chunk_key, $chunk_status);
                    return [$chunk_status['start'] + $chunk_status['counter'], $chunk_status['end'], $i];
                }
            }

            return [-1, -1, -1];
        }

        return [$file_start, $file_end, -1];
    }

    public function update_chunk_status($i, $status = null, $counter = null)
    {

        if (is_null($status) && is_null($counter)) {
            return;
        }

        $chunk_key = '_' . $this->session . '_' . $i . '_chunk';
        $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);
        if (!is_null($status)) {
            $chunk_status['status'] = $status;
        }

        if (!is_null($counter)) {
            $chunk_status['counter'] = $counter;
        }

        $chunk_status['time'] = time();

        update_post_meta($this->importer_id, $chunk_key, $chunk_status);
    }

    public function has_more_chunks($size, $file_start, $file_end)
    {
        $max_chunks = $this->get_max_chunks($size, $file_start, $file_end);
        if ($max_chunks) {
            for ($i = 0; $i < $max_chunks; $i++) {
                $chunk_key = '_' . $this->session . '_' . $i . '_chunk';
                $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);

                if (empty($chunk_status) || (isset($chunk_status) && $chunk_status['status'] != 'complete')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function clear_chunk_data()
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $result = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id='" . $this->importer_id . "' AND meta_key LIKE '\_" . $this->get_session_id() . "\_%'");
    }

    public function record_chunk($i, ParsedData $data = null)
    {
        $chunk_key = '_' . $this->session . '_' . $i . '_chunk';
        $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);
        $chunk_status['counter'] = intval($chunk_status['counter']) + 1;

        if ($data != null) {
            if ($data->isInsert()) {
                if (!isset($chunk_status['inserts'])) {
                    $chunk_status['inserts'] = 0;
                }

                $chunk_status['inserts']++;
            } elseif ($data->isUpdate()) {
                if (!isset($chunk_status['updates'])) {
                    $chunk_status['updates'] = 0;
                }

                $chunk_status['updates']++;
            }
        }

        if ($chunk_status['counter'] == $chunk_status['end'] - $chunk_status['start']) {
            $chunk_status['status'] = 'complete';
        }

        $chunk_status['time'] = time();

        update_post_meta($this->importer_id, $chunk_key, $chunk_status);
    }

    public function timeout_chunk($i = -1)
    {
        if ($i == -1 && $this->chunk_index > -1) {
            $i = $this->chunk_index;
        }

        $chunk_key = '_' . $this->session . '_' . $i . '_chunk';
        $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);

        if ($chunk_status['counter'] == $chunk_status['end'] - $chunk_status['start']) {
            $chunk_status['status'] = 'complete';
        } else {
            $chunk_status['status'] = 'timeout';
        }

        $chunk_status['time'] = time();

        update_post_meta($this->importer_id, $chunk_key, $chunk_status);
    }

    public function set_chunk_index($i)
    {
        $this->chunk_index = $i;
    }

    public function setup_chunk_delete_list($object_ids = [])
    {
        $list_key = '_' . $this->session . '_delete_list';
        if (false == get_post_meta($this->importer_id, $list_key, true)) {
            update_post_meta($this->importer_id, $list_key, $object_ids);
        }
    }

    public function get_chunk_delete_list()
    {
        $list_key = '_' . $this->session . '_delete_list';
        $list = get_post_meta($this->importer_id, $list_key, true);
        return !empty($list) ? $list : false;
    }

    public function get_next_delete_chunk($size)
    {
        $delete_list = $this->get_chunk_delete_list();
        if (false === $delete_list) {
            return [false, -1];
        }

        $file_start = 0;
        $file_end = count($delete_list);
        $max_chunks = $this->get_max_chunks($size, $file_start, $file_end);
        if ($max_chunks) {
            for ($i = 0; $i < $max_chunks; $i++) {

                $chunk_key = '_' . $this->session . '_' . $i . '_delete';
                $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);
                if (!empty($chunk_status)) {
                    $chunk_status = maybe_unserialize($chunk_status);
                    if ($chunk_status['status']  == 'timeout' || ($chunk_status['status']  != 'complete' && time() - 30 > $chunk_status['time'])) {
                        $chunk_status['status'] = 'running';
                        $chunk_status['time'] = time();
                        update_post_meta($this->importer_id, $chunk_key, $chunk_status);

                        $start = $chunk_status['start'] + $chunk_status['counter'];
                        return [array_slice($delete_list, $start, $chunk_status['end'] - $start), $i];
                    }
                } else {

                    $start  = $file_start + ($i * $size);
                    $chunk_status = [
                        'status' => 'running',
                        'start' => $start,
                        'end' => min($start + $size, $file_end),
                        'counter' => 0,
                        'deletes' => 0,
                        'errors' => 0,
                        'time' => time()
                    ];

                    update_post_meta($this->importer_id, $chunk_key, $chunk_status);

                    $start = $chunk_status['start'] + $chunk_status['counter'];
                    return [array_slice($delete_list, $start, $chunk_status['end'] - $start), $i];
                }
            }

            return [false, -1];
        }

        return [$delete_list, -1];
    }

    public function has_more_delete_chunks($size)
    {
        $delete_list = $this->get_chunk_delete_list();
        if (false !== $delete_list) {
            $file_start = 0;
            $file_end = count($delete_list);
            $max_chunks = $this->get_max_chunks($size, $file_start, $file_end);
            if ($max_chunks) {
                for ($i = 0; $i < $max_chunks; $i++) {
                    $chunk_key = '_' . $this->session . '_' . $i . '_delete';
                    $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);

                    if (empty($chunk_status) || (isset($chunk_status) && $chunk_status['status'] != 'complete')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function record_chunk_delete($i)
    {
        $chunk_key = '_' . $this->session . '_' . $i . '_delete';
        $chunk_status = get_post_meta($this->importer_id, $chunk_key, true);
        $chunk_status['counter'] = intval($chunk_status['counter']) + 1;

        $chunk_status['deletes']++;

        if ($chunk_status['counter'] == $chunk_status['end'] - $chunk_status['start']) {
            $chunk_status['status'] = 'complete';
        }

        $chunk_status['time'] = time();

        update_post_meta($this->importer_id, $chunk_key, $chunk_status);
    }
}
