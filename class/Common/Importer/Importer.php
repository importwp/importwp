<?php

namespace ImportWP\Common\Importer;

use ImportWP\Common\Importer\ConfigInterface;
use ImportWP\Common\Importer\DataParser;
use ImportWP\Common\Importer\Exception\FileException;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Exception\ParserException;
use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\Parser\CSVParser;
use ImportWP\Common\Importer\Parser\XMLParser;
use ImportWP\Common\Importer\ParserInterface;
use ImportWP\Common\Importer\State\ImporterState;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Util\Logger;
use ImportWP\Common\Util\Util;
use ImportWP\Container;

class Importer
{
    /**
     * @var ConfigInterface $config
     */
    private $config;

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
     * Are there any dangling records that need fixed.
     *
     * @var boolean
     */
    private $is_timeout = false;

    /**
     * @var int
     */
    private $memory_limit;

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
     * @param ImporterState $importer_state 
     *
     * @throws \Exception
     */
    public function import($id, $user, $importer_state)
    {
        if ($this->parser == null) {
            throw new \Exception("Parser Not Loaded.");
        }

        if ($this->mapper == null) {
            throw new \Exception("Mapper Not Loaded.");
        }

        $this->mapper->setup();

        $this->register_shutdown($importer_state);

        /**
         * @var Util $util
         */
        $util = Container::getInstance()->get('util');
        $util->set_time_limit();

        /**
         * @var Properties $properties
         */
        $properties = Container::getInstance()->get('properties');

        $time_limit = $properties->get_setting('timeout');
        $start = microtime(true);
        $max_record_time = 0;
        $memory_max_usage = 0;
        $i = 0;

        // Does this current user have any dangling jobs?
        $this->try_import_dangling_rows($id, $user, $importer_state, $user);

        $config = get_site_option('iwp_importer_config_' . $id, []);

        while (
            ($i = 0 || (
                ($time_limit === 0 || $this->has_enough_time($start, $time_limit, $max_record_time))
                && $this->has_enough_memory($memory_max_usage))
            )
            && $importer_state
            && $importer_state->has_section(['import', 'delete', 'timeout'])
        ) {

            $memory_usage = $this->get_memory_usage();

            $this->is_timeout = false;

            $importer_state = $importer_state->update(function ($state) use ($importer_state, $config, $user, $id) {
                return $this->setup_importer_state($importer_state, $state, $config, $user, $id);
            });

            if (!$importer_state || $this->is_timeout || !$importer_state->has_status('running')) {
                break;
            }

            $record_time = microtime(true);
            $this->import_row($id, $user, $importer_state, $importer_state->get_session(), $importer_state->get_section(), $importer_state->get_progress());
            $max_record_time = max($max_record_time, microtime(true) - $record_time);

            if (!wp_using_ext_object_cache()) {
                wp_cache_flush();
            }

            do_action('iwp/importer/shutdown');

            // keep track of largest memory change
            $memory_delta = $this->get_memory_usage() - $memory_usage;
            if ($memory_delta > $memory_max_usage) {
                $memory_max_usage = $memory_delta;
            }

            $i++;
        }

        Util::write_status_session_to_file($id, $importer_state);

        $this->mapper->teardown();
        $this->unregister_shutdown();
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
        $limit = $this->get_memory_limit() * 0.9;
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
        }

        return $this->memory_limit;
    }

    function setup_importer_state($importer_state, $state, $config, $user, $id)
    {
        $importer_state->populate($state);

        if (!$importer_state->validate($config['id'])) {
            throw new \Exception("Importer session has changed");
        }

        if ($importer_state->has_status('running')) {

            $section = $importer_state->get_section();
            if (isset($state['progress'][$section]) && $state['progress'][$section]['end'] - $state['progress'][$section]['start'] <= $state['progress'][$section]['current_row']) {

                // Does this user or any user have any dangling jobs?
                $dangling = $this->try_import_dangling_rows($id, $user, $importer_state);
                if (!$dangling) {
                    $this->is_timeout = true;
                    $state['duration'] = floatval($state['duration']) + Logger::timer();
                    return $state;
                }

                switch ($importer_state->get_section()) {
                    case 'import':

                        if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

                            // importer delete
                            $state['section'] = 'delete';

                            // generate list of items to be deleted
                            $object_ids = $this->mapper->get_objects_for_removal();

                            $config = get_site_option('iwp_importer_config_' . $id);
                            $config['delete_ids'] = $object_ids;
                            update_site_option('iwp_importer_config_' . $id, $config);

                            $state['progress']['delete']['start'] = 0;
                            $state['progress']['delete']['end'] = $object_ids ? count($object_ids) : 0;
                        } else {
                            $state['section'] = '';
                            $state['status'] = 'complete';
                        }

                        break;
                    case 'delete':

                        // importer complete
                        $state['section'] = '';
                        $state['status'] = 'complete';

                        break;
                }
            }

            // Get increase index, locking record, and saving to user importer state
            if (!empty($state['section'])) {
                $state['progress'][$state['section']]['current_row']++;
                update_site_option('iwp_importer_state_' . $id . '_' . $user, array_merge($state, ['last_modified' => current_time('timestamp')]));
            }
        }

        $state['duration'] = floatval($state['duration']) + Logger::timer();

        return $state;
    }

    function try_import_dangling_rows($id, $user, $importer_state, $user_to_check = null)
    {
        $dangling = $this->has_dangling_state($id, $user_to_check);
        if (!empty($dangling)) {
            $fixed = 0;
            foreach ($dangling as $dangling_id) {

                // TODO: Should the option be renamed to the current user first? to make sure its not ran multiple times.
                // TODO: status should not be overwritten like this.
                $GLOBALS['wp_object_cache']->delete($dangling_id, 'options');
                $status = get_site_option($dangling_id);
                if ($status && $status['last_modified'] < current_time('timestamp') - 30) {

                    Logger::write('try_import_dangling_rows -id=' . $id . ' -user=' . $user . ' -dangling=' . $dangling_id);

                    $GLOBALS['wp_object_cache']->delete($dangling_id, 'options');
                    $status['last_modified'] = current_time('timestamp');
                    update_site_option($dangling_id, $status);


                    $this->import_row($id, $user, $importer_state, $importer_state->get_session(), $status['section'], $status['progress'][$status['section']]);
                    delete_site_option($dangling_id);
                    $fixed++;
                }
            }

            if ($fixed !== count($dangling)) {
                // escape due to dangling records that have not timed out
                return false;
            }
        }

        return true;
    }

    function import_row($id, $user, $importer_state, $session, $section, $progress)
    {
        $stats = [
            'inserts' => 0,
            'updates' => 0,
            'deletes' => 0,
            'skips' => 0,
            'errors' => 0,
        ];

        if ($section === 'import') {


            // TODO: Run through field map from config (xml or csv)
            $data_parser = new DataParser($this->parser, $this->mapper, $this->config->getData());

            $i = $progress['start'] + $progress['current_row'] - 1;

            /**
             * @var ParsedData $data
             */
            $data = null;

            try {

                $data = $data_parser->get($i);

                $skip_record = $this->filterRecords();
                $skip_record = apply_filters('iwp/importer/skip_record', $skip_record, $data, $this);

                if ($skip_record) {

                    Logger::write('import -skip-record=' . $i);

                    $stats['skips']++;

                    // set data to null, to flag chunk as skipped
                    Util::write_status_log_file_message($id, $session, "Skipped Record", 'S', $progress['current_row']);

                    $data = null;
                } else {

                    // import
                    $data = apply_filters('iwp/importer/before_mapper', $data, $this);
                    $data->map();

                    if ($data->isInsert()) {

                        Logger::write('import:' . $i . ' -success -insert');

                        $stats['inserts']++;

                        $message = apply_filters('iwp/status/record_inserted', 'Record Inserted: #' . $data->getId(), $data->getId(), $data);
                        Util::write_status_log_file_message($id, $session, $message, 'S', $progress['current_row']);
                    }

                    if ($data->isUpdate()) {

                        Logger::write('import:' . $i . ' -success -update');

                        $stats['updates']++;

                        $message = apply_filters('iwp/status/record_updated', 'Record Updated: #' . $data->getId(), $data->getId(), $data);
                        Util::write_status_log_file_message($id, $session, $message, 'S', $progress['current_row']);
                    }
                }
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

            $this->update_importer_stats($importer_state, $stats);
            Util::write_status_session_to_file($id, $importer_state);

            delete_site_option('iwp_importer_state_' . $id . '_' . $user);
            return;
        }

        if ($section === 'delete') {
            if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

                $GLOBALS['wp_object_cache']->delete('iwp_importer_config_' . $id, 'options');
                $config = get_site_option('iwp_importer_config_' . $id);
                $i = $progress['current_row'] - 1;

                $object_ids = $config['delete_ids'];
                if ($object_ids && count($object_ids) > $i) {
                    $object_id = $object_ids[$i];
                    $this->mapper->delete($object_id);
                    $stats['deletes']++;

                    Logger::write('delete:' . $i . ' -object=' . $object_id);

                    $message = apply_filters('iwp/status/record_deleted', 'Record Deleted: #' . $object_id, $object_id);
                    Util::write_status_log_file_message($id, $session, $message, 'D', $progress['current_row']);
                }
            }

            $this->update_importer_stats($importer_state, $stats);
            Util::write_status_session_to_file($id, $importer_state);

            delete_site_option('iwp_importer_state_' . $id . '_' . $user);
            return;
        }
    }

    function update_importer_stats($importer_state, $stats)
    {
        $importer_state->update(function ($state) use ($stats) {
            if (!isset($state['stats'])) {
                $state['stats'] = [
                    'inserts' => 0,
                    'updates' => 0,
                    'deletes' => 0,
                    'skips' => 0,
                    'errors' => 0,
                ];
            }

            $state['stats']['inserts'] += $stats['inserts'];
            $state['stats']['updates'] += $stats['updates'];
            $state['stats']['deletes'] += $stats['deletes'];
            $state['stats']['skips'] += $stats['skips'];
            $state['stats']['errors'] += $stats['errors'];

            return $state;
        });
    }

    function has_dangling_state($id, $user = null, $key_prefix = 'iwp_importer_state')
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $key_prefix = str_replace('_', '\_', $key_prefix);
        $query = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '{$key_prefix}\_{$id}\_";

        if (!empty($user)) {
            $query .= $user;
        } else {
            $query .= '%';
        }

        $query .= "'";

        $option_names = $wpdb->get_col($query);
        return $option_names;
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
