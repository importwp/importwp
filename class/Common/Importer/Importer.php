<?php

namespace ImportWP\Common\Importer;

use ImportWP\Common\Importer\ConfigInterface;
use ImportWP\Common\Importer\Exception\FileException;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Exception\ParserException;
use ImportWP\Common\Importer\Exception\RecordUpdatedSkippedException;
use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\Parser\CSVParser;
use ImportWP\Common\Importer\Parser\XMLParser;
use ImportWP\Common\Importer\ParserInterface;
use ImportWP\Common\Importer\State\ImporterState;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Runner\ImporterRunnerState;
use ImportWP\Common\Util\Logger;
use ImportWP\Common\Util\Util;
use ImportWP\Container;
use ImportWP\Common\Queue\Queue;
use ImportWP\Common\Queue\QueueTaskResult;
use ImportWP\Common\Util\DB;

class TMP_Queue_Task implements \ImportWP\Common\Queue\QueueTaskInterface
{
    public $data_parser;
    public $mapper;

    public function __construct($data_parser, $mapper)
    {
        $this->data_parser = $data_parser;
        $this->mapper = $mapper;
    }

    public function process($import_id, $chunk)
    {
        switch ($chunk['type']) {
            case 'D':
                return $this->process_delete($import_id, $chunk);
            case 'R':
                return $this->process_remove($import_id, $chunk);
            case 'P':
                return $this->process_complete($import_id, $chunk);
            default:
                return $this->process_import($import_id, $chunk);
        }
    }

    public function process_import($import_id, $chunk)
    {
        $i = $chunk['pos'];

        /**
         * @var ParsedData $data
         */
        $data = null;

        $record_id = 0;
        $import_type = '';
        $message = '';

        try {

            $data = $this->data_parser->get($i);
            do_action('iwp/importer/before_row', $data);

            $skip_record = false; //$this->filterRecords();
            $skip_record = apply_filters('iwp/importer/skip_record', $skip_record, $data, $this);

            if ($skip_record) {

                Logger::write('import -skip-record=' . $i);
                $message = apply_filters('iwp/status/record_skipped', "Skipped Record");
                $data = null;
            } else {

                // import
                $data = apply_filters('iwp/importer/before_mapper', $data, $this);
                $data->map();

                if ($data->isInsert()) {
                    Logger::write('import:' . $i . ' -success -insert');
                    $import_type = 'I';
                    $record_id = $data->getId();
                    $message = apply_filters('iwp/status/record_inserted', 'Record Inserted: #' . $data->getId(), $data->getId(), $data);
                }

                if ($data->isUpdate()) {
                    Logger::write('import:' . $i . ' -success -update');
                    $import_type = 'U';
                    $record_id = $data->getId();
                    $message = apply_filters('iwp/status/record_updated', 'Record Updated: #' . $data->getId(), $data->getId(), $data);
                }
            }
        } catch (RecordUpdatedSkippedException $e) {

            Logger::write('import:' . $i . ' -success -update -skipped="hash"');
            $import_type = 'S';
            $message = 'Record Update Skipped: #' . $data->getId() . ' ' . $e->getMessage();
        } catch (ParserException $e) {

            $import_type = 'E';

            Logger::error('import:' . $i . ' -parser-error=' . $e->getMessage());
        } catch (MapperException $e) {

            $import_type = 'E';

            Logger::error('import:' . $i . ' -mapper-error=' . $e->getMessage());
        } catch (FileException $e) {

            $import_type = 'E';

            Logger::error('import:' . $i . ' -file-error=' . $e->getMessage());
        }

        do_action('iwp/importer/after_row');

        return new QueueTaskResult($record_id, $import_type, $message);
    }

    public function process_remove($import_id, $chunk)
    {
        $i = $chunk['pos'];
        $object_id = $chunk['record'];

        if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

            try {

                if (apply_filters('iwp/importer/enable_custom_delete_action', false, $import_id)) {

                    Logger::write('custom_delete_action:' . $i . ' -object=' . $object_id);
                    do_action('iwp/importer/custom_delete_action', $import_id, $object_id);
                } else {

                    Logger::write('delete:' . $i . ' -object=' . $object_id);
                    $this->mapper->delete($object_id);
                }

                $message = apply_filters('iwp/status/record_deleted', 'Record Deleted: #' . $object_id, $object_id);
            } catch (MapperException $e) {

                Logger::error('delete:' . $i . ' -mapper-error=' . $e->getMessage());
            }
        }

        return new QueueTaskResult($object_id, 'R');
    }

    public function process_delete($import_id, $chunk)
    {
        if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

            // generate list of items to be deleted
            $object_ids = $this->mapper->get_objects_for_removal();
            $object_ids = array_values(array_unique($object_ids));
            if (!empty($object_ids)) {

                /**
                 * @var \WPDB $wpdb
                 */
                global $wpdb;

                $table_name = DB::get_table_name('queue');
                $base_query = "INSERT INTO {$table_name} (`import_id`,`record`, `pos`,`type`) VALUES ";
                $query_values = [];
                $rollback = false;

                $wpdb->query('START TRANSACTION');

                foreach ($object_ids as $i => $row) {
                    $query_values[] = "('{$chunk['import_id']}','{$row}', {$i}, 'R')";

                    if (count($query_values) > 1000) {

                        if (!$wpdb->query($base_query . implode(',', $query_values))) {
                            $rollback = true;
                            break;
                        }

                        $query_values = [];
                    }
                }

                if (!$rollback && !empty($query_values)) {
                    if (!$wpdb->query($base_query . implode(',', $query_values))) {
                        $rollback = true;
                    }
                }

                if ($rollback) {
                    $wpdb->query('ROLLBACK');
                } else {
                    $wpdb->query('COMMIT');
                }
            }
        }

        // TODO: process which items need to be queued for deleting records.
        return new QueueTaskResult(0, 'Y');
    }

    public function process_complete($import_id, $chunk)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table_name = DB::get_table_name('import')) {
            $wpdb->update($table_name, ['status' => 'Y'], ['id' => $import_id]);
        }

        return new QueueTaskResult(0, 'Y');
    }
}

class Importer
{
    /**
     * @var int
     */
    protected $memory_limit;
    /**
     * @var ConfigInterface $config
     */
    public $config;

    /**
     * @var MapperInterface $mapper
     */
    private $mapper;

    /**
     * @var int $start
     */
    private $start;

    /**
     * @var int end
     */
    private $end;

    /**
     * @var ParserInterface $parser
     */
    private $parser;

    /**
     * Flag used to determine type of shutdown
     * 
     * @var boolean
     */
    private $graceful_shutdown = true;

    /**
     * List of filters that can be applied
     *
     * @var array
     */
    private $filter_data = [];

    /**
     * @param ConfigInterface $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Set Parser
     *
     * @param ParserInterface $parser
     *
     * @return $this
     */
    public function parser($parser)
    {
        $this->parser = $parser;

        return $this;
    }

    /**
     * Set Mapper
     *
     * @param MapperInterface $mapper
     *
     * @return $this
     */
    public function mapper(MapperInterface $mapper)
    {
        $this->mapper = $mapper;

        return $this;
    }

    /**
     * Load XML File
     *
     * @param string $file_path
     *
     * @return $this
     */
    public function xmlFile($file_path)
    {
        $file         = new XMLFile($file_path, $this->config);
        $this->parser = new XMLParser($file);

        return $this;
    }

    /**
     * Load CSV File
     *
     * @param string $file_path
     *
     * @return $this
     */
    public function csvFile($file_path)
    {
        $file         = new CSVFile($file_path, $this->config);
        $this->parser = new CSVParser($file);

        return $this;
    }

    /**
     * Set record to start importing from
     *
     * @param int $start
     */
    public function from($start)
    {
        $this->start = $start;
    }

    /**
     * Set record to end import at
     *
     * @param int $end
     */
    public function to($end)
    {
        $this->end = $end;
    }

    /**
     * Get Record Start Index
     *
     * @return int
     */
    private function getRecordStart()
    {
        return isset($this->start) && $this->start >= 0 ? $this->start : 0;
    }

    /**
     * Get Record End Index
     *
     * @return int
     */
    public function getRecordEnd()
    {
        return isset($this->end) && $this->end >= $this->getRecordStart() ? $this->end : $this->parser->file()->getRecordCount();
    }

    private function register_shutdown($importer_state)
    {
        $this->graceful_shutdown = false;

        register_shutdown_function(function () use ($importer_state) {
            if ($this->is_graceful_shutdown()) {
                // $this->record_time();
                return;
            }

            // TODO: Log errors
            $error = error_get_last();
            if (!is_null($error)) {

                $importer_state->update(function ($state) use ($error) {
                    $state['status'] = 'error';
                    $state['message'] = $error['message'];
                    return $state;
                });


                $this->mapper->teardown();
                echo json_encode($importer_state->get_raw()) . "\n";
                die();
            }

            $this->mapper->teardown();
        });
    }

    private function unregister_shutdown()
    {
        $this->graceful_shutdown = true;
    }

    private function is_graceful_shutdown()
    {
        return $this->graceful_shutdown;
    }

    /**
     * Run Import
     * 
     * @param int $id Importer Id
     * @param string $user Unique user id
     * @param ImporterRunnerState $importer_state 
     *
     * @throws \Exception
     */
    public function import($id, $user, $importer_state)
    {
        if ($this->parser == null) {
            throw new \Exception(__("Parser Not Loaded.", 'jc-importer'));
        }

        if ($this->mapper == null) {
            throw new \Exception(__("Mapper Not Loaded.", 'jc-importer'));
        }

        $this->mapper->setup();

        $this->register_shutdown($importer_state);

        $this->disable_caching();

        /**
         * @var Util $util
         */
        $util = Container::getInstance()->get('util');
        $util->set_time_limit();

        // TODO: 
        // $runner = new ImporterRunner($properties, $this);
        // $runner->process($id, $user, $importer_state);

        if (Queue::is_enabled($id)) {
            $this->process_chunk_queue($id, $importer_state->get_session());
        } else {
            $this->process_chunk($id, $user, $importer_state);
        }


        $this->mapper->teardown();
        $this->unregister_shutdown();
    }

    protected function disable_caching()
    {
        if (!defined('WP_IMPORTING')) {
            define('WP_IMPORTING', true);
        }

        // WP Rocket Integration
        add_filter('rocket_is_importing', '__return_true');
    }

    protected function process_chunk_queue($id, $session_id)
    {
        $queue = new Queue();
        $queue->process($session_id, new TMP_Queue_Task(
            new DataParser($this->getParser(), $this->getMapper(), $this->config->getData()),
            $this->getMapper()
        ));
    }

    protected function process_chunk($id, $user, $importer_state)
    {
        // Introduce new running state, to stop cron running duplicates
        $importer_state->populate([
            'status' => 'processing'
        ]);
        ImporterState::set_state($id, $importer_state->get_raw());

        /**
         * @var Properties $properties
         */
        $properties = Container::getInstance()->get('properties');
        $time_limit = $properties->get_setting('timeout');
        Logger::info('time_limit ' . $time_limit . 's');

        $start = microtime(true);
        $max_record_time = 0;
        $memory_max_usage = 0;

        $progress = $importer_state->get_progress();
        $session = $importer_state->get_session();
        $max_total = $progress['end'] - 1;
        $i = $progress['start'] + $progress['current_row'] - 1;

        // limit to max 20 rows per chunk
        $i_max = $i + apply_filters('iwp/chunk_max_records', 20);

        while (
            $i < $max_total
            && (!defined('REST_REQUEST') || !REST_REQUEST ||  $i < $i_max)
            && (
                $time_limit === 0 || $this->has_enough_time($start, $time_limit, $max_record_time)
            )
            && $this->has_enough_memory($memory_max_usage)
        ) {
            $i++;

            $flag = ImporterState::get_flag($id);

            if (ImporterState::is_paused($flag)) {

                $importer_state->populate([
                    'status' => 'paused'
                ]);

                ImporterState::set_state($id, $importer_state->get_raw());
                Util::write_status_session_to_file($id, $importer_state);
                return;
            }

            if (ImporterState::is_cancelled($flag)) {
                $importer_state->populate([
                    'status' => 'cancelled'
                ]);

                ImporterState::set_state($id, $importer_state->get_raw());
                Util::write_status_session_to_file($id, $importer_state);
                return;
            }

            $stats = [
                'inserts' => 0,
                'updates' => 0,
                'deletes' => 0,
                'skips' => 0,
                'errors' => 0,
            ];

            $record_time = microtime(true);

            if ($importer_state->get_section() === 'import') {

                /**
                 * @var ParsedData $data
                 */
                $data = null;

                $data_parser = new DataParser($this->getParser(), $this->getMapper(), $this->config->getData());

                try {

                    $data = $data_parser->get($i);
                    do_action('iwp/importer/before_row', $data);

                    $skip_record = $this->filterRecords();
                    $skip_record = apply_filters('iwp/importer/skip_record', $skip_record, $data, $this);

                    if ($skip_record) {

                        Logger::write('import -skip-record=' . $i);

                        $stats['skips']++;

                        // set data to null, to flag chunk as skipped
                        $message = apply_filters('iwp/status/record_skipped', "Skipped Record");
                        Util::write_status_log_file_message($id, $session, $message, 'S', $progress['current_row']);

                        $data = null;
                    } else {

                        // import
                        $data = apply_filters('iwp/importer/before_mapper', $data, $this);
                        $data->map();

                        $unique_identifier_str = $this->get_unique_identifier_log_text();

                        if ($data->isInsert()) {

                            Logger::write('import:' . $i . ' -success -insert');

                            $stats['inserts']++;

                            $message = apply_filters('iwp/status/record_inserted', 'Record Inserted: #' . $data->getId(), $data->getId(), $data);
                            Util::write_status_log_file_message($id, $session, $message . $unique_identifier_str, 'S', $progress['current_row']);
                        }

                        if ($data->isUpdate()) {

                            Logger::write('import:' . $i . ' -success -update');

                            $stats['updates']++;

                            $message = apply_filters('iwp/status/record_updated', 'Record Updated: #' . $data->getId(), $data->getId(), $data);
                            Util::write_status_log_file_message($id, $session, $message . $unique_identifier_str, 'S', $progress['current_row']);
                        }
                    }
                } catch (RecordUpdatedSkippedException $e) {

                    Logger::write('import:' . $i . ' -success -update -skipped="hash"');
                    $stats['updates']++;
                    $message = 'Record Update Skipped: #' . $data->getId() . ' ' . $e->getMessage();
                    $unique_identifier_str = $this->get_unique_identifier_log_text();

                    Util::write_status_log_file_message($id, $session, $message . $unique_identifier_str, 'S', $progress['current_row']);
                } catch (ParserException $e) {

                    $stats['errors']++;
                    Logger::error('import:' . $i . ' -parser-error=' . $e->getMessage());
                    Util::write_status_log_file_message($id, $session, $e->getMessage(), 'E', $progress['current_row']);
                } catch (MapperException $e) {

                    $stats['errors']++;
                    Logger::error('import:' . $i . ' -mapper-error=' . $e->getMessage());
                    Util::write_status_log_file_message($id, $session, $e->getMessage(), 'E', $progress['current_row']);
                } catch (FileException $e) {

                    $stats['errors']++;
                    Logger::error('import:' . $i . ' -file-error=' . $e->getMessage());
                    Util::write_status_log_file_message($id, $session, $e->getMessage(), 'E', $progress['current_row']);
                }

                do_action('iwp/importer/after_row');
            } elseif ($importer_state->get_section() === 'delete') {

                if ($this->getMapper()->permission() && $this->getMapper()->permission()->allowed_method('remove')) {

                    try {
                        $GLOBALS['wp_object_cache']->delete('iwp_importer_config_' . $id, 'options');
                        $config = get_site_option('iwp_importer_config_' . $id);

                        $object_ids = $config['delete_ids'];
                        if ($object_ids && count($object_ids) > $i) {

                            $object_id = $object_ids[$i];

                            if (apply_filters('iwp/importer/enable_custom_delete_action', false, $id)) {

                                Logger::write('custom_delete_action:' . $i . ' -object=' . $object_id);
                                do_action('iwp/importer/custom_delete_action', $id, $object_id);
                            } else {

                                Logger::write('delete:' . $i . ' -object=' . $object_id);
                                $this->getMapper()->delete($object_id);
                            }

                            $message = apply_filters('iwp/status/record_deleted', 'Record Deleted: #' . $object_id, $object_id);
                            $stats['deletes']++;

                            Util::write_status_log_file_message($id, $session, $message, 'D', $progress['current_row']);
                        }
                    } catch (MapperException $e) {

                        $stats['errors']++;
                        Logger::error('delete:' . $i . ' -mapper-error=' . $e->getMessage());
                        Util::write_status_log_file_message($id, $session, $e->getMessage(), 'E', $progress['current_row']);
                    }
                }
            }

            $importer_state->update_importer_stats($stats);
            Util::write_status_session_to_file($id, $importer_state);

            $importer_state->increment_current_row();
            $progress = $importer_state->get_progress();

            ImporterState::set_state($id, $importer_state->get_raw());

            $max_record_time = max($max_record_time, microtime(true) - $record_time);
        }

        // TODO: need a new state that will stop the running from happening more than once.
        // if returning timeout then the cron will stop on older versions
        if (defined('IWP_PRO_VERSION') && version_compare(IWP_PRO_VERSION, '2.8.0', '>')) {
            // default status to idle after run
            $importer_state->populate([
                'status' => 'timeout'
            ]);
        } else {
            $importer_state->populate([
                'status' => 'running'
            ]);
        }

        $state_data = $importer_state->get_raw();

        $progress = $importer_state->get_progress();
        if ($progress['end'] - $progress['start'] <= $progress['current_row']) {

            switch ($importer_state->get_section()) {
                case 'import':


                    if ($this->getMapper()->permission() && $this->getMapper()->permission()->allowed_method('remove')) {

                        // importer delete
                        $state_data['section'] = 'delete';

                        // generate list of items to be deleted
                        $object_ids = $this->getMapper()->get_objects_for_removal();
                        if (!empty($object_ids)) {

                            $config = get_site_option('iwp_importer_config_' . $id);
                            $config['delete_ids'] = $object_ids;
                            update_site_option('iwp_importer_config_' . $id, $config);

                            $state_data['progress']['delete']['start'] = 0;
                            $state_data['progress']['delete']['end'] = $object_ids ? count($object_ids) : 0;
                        } else {
                            $state_data['section'] = '';
                            $state_data['status'] = 'complete';
                        }
                    } else {
                        $state_data['section'] = '';
                        $state_data['status'] = 'complete';
                    }

                    break;
                case 'delete':

                    // importer complete
                    $state_data['section'] = '';
                    $state_data['status'] = 'complete';

                    break;
            }
        }

        ImporterState::set_state($id, $state_data);
        $importer_state->populate($state_data);

        Util::write_status_session_to_file($id, $importer_state);
    }

    function get_unique_identifier_log_text()
    {
        $unique_identifier_str = '';

        $unqiue_identifier_settings = $this->getMapper()->get_unqiue_identifier_settings();
        if (!empty($unqiue_identifier_settings) && isset($unqiue_identifier_settings['field'], $unqiue_identifier_settings['value'])) {

            $unique_identifier_str = ' using unique identifier ';
            if ($unqiue_identifier_settings['field'] === '_iwp_ref_uid') {
                $unique_identifier_str .= sprintf('("%s")', $unqiue_identifier_settings['value']);
            } else {
                $unique_identifier_str .= sprintf('("%s" = "%s")', $unqiue_identifier_settings['field'], $unqiue_identifier_settings['value']);
            }
        }

        return $unique_identifier_str;
    }

    function has_enough_time($start, $time_limit, $max_record_time)
    {
        return (microtime(true) - $start) < $time_limit - $max_record_time;
    }

    function get_memory_usage()
    {
        return memory_get_usage(true);
    }

    function has_enough_memory($memory_max_usage)
    {
        $limit = $this->get_memory_limit();

        // Has unlimited memory
        if ($limit == '-1') {
            return true;
        }

        $limit *= 0.9;
        $current_usage = $this->get_memory_usage();

        if ($current_usage + $memory_max_usage < $limit) {
            return true;
        }

        Logger::error(sprintf("Not Enough Memory left to use %s,  %s/%s", Logger::formatBytes($memory_max_usage, 2), Logger::formatBytes($current_usage, 2), Logger::formatBytes($limit, 2)));

        return false;
    }

    function get_memory_limit($force = false)
    {
        if ($force || is_null($this->memory_limit)) {

            $memory_limit = ini_get('memory_limit');
            if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
                if ($matches[2] == 'G') {
                    $memory_limit = $matches[1] * 1024 * 1024 * 1024; // nnnM -> nnn MB
                } elseif ($matches[2] == 'M') {
                    $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
                } else if ($matches[2] == 'K') {
                    $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
                }
            }

            $this->memory_limit = $memory_limit;

            Logger::info('memory_limit ' . $this->memory_limit . ' bytes');
        }

        return $this->memory_limit;
    }

    /**
     * Apply any importer filters to skip records
     *
     * @return boolean
     */
    function filterRecords()
    {
        $result = false;

        if (empty($this->filter_data)) {
            return $result;
        }

        foreach ($this->filter_data as $group) {

            $result = true;

            if (empty($group)) {
                continue;
            }

            foreach ($group as $row) {

                $left = trim($this->parser->query_string($row['left']));
                $right = $row['right'];
                $right_parts = array_map('trim', explode(',', $right));

                switch ($row['condition']) {
                    case 'equal':
                        if (strcasecmp($left, $right) !== 0) {
                            $result = false;
                        }
                        break;
                    case 'contains':
                        if (stripos($left, $right) === false) {
                            $result = false;
                        }
                        break;
                    case 'in':
                        $found = false;
                        foreach ($right_parts as $right_part) {
                            if (strcasecmp($left, $right_part) === 0) {
                                $found = true;
                                break 1;
                            }
                        }

                        if (!$found) {
                            $result = false;
                        }

                        break;
                    case 'contains-in':
                        $found = false;
                        foreach ($right_parts as $right_part) {
                            if (stripos($left, $right_part) !== false) {
                                $found = true;
                                break 1;
                            }
                        }

                        if (!$found) {
                            $result = false;
                        }
                        break;
                    case 'not-equal':
                        if (strcasecmp($left, $right) === 0) {
                            $result = false;
                        }
                        break;
                    case 'not-contains':
                        if (stripos($left, $right) !== false) {
                            $result = false;
                        }
                        break;
                    case 'not-in':
                        $found = false;
                        foreach ($right_parts as $right_part) {
                            if (strcasecmp($right_part, $left) === 0) {
                                $found = true;
                                break 1;
                            }
                        }

                        if ($found) {
                            $result = false;
                        }

                        break;
                    case 'not-contains-in':
                        $found = false;
                        foreach ($right_parts as $right_part) {
                            if (stripos($left, $right_part) !== false) {
                                $found = true;
                                break 1;
                            }
                        }

                        if ($found) {
                            $result = false;
                        }
                        break;
                }
            }

            if ($result) {
                return true;
            }
        }


        return $result;
    }

    function filter($filter_data = [])
    {
        $this->filter_data = $filter_data;
    }

    public function getParser()
    {
        return $this->parser;
    }

    public function getMapper()
    {
        return $this->mapper;
    }
}
