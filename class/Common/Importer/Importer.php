<?php

namespace ImportWP\Common\Importer;

use FFI\ParserException;
use ImportWP\Common\Importer\ConfigInterface;
use ImportWP\Common\Importer\Exception\FileException;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\Parser\CSVParser;
use ImportWP\Common\Importer\Parser\XMLParser;
use ImportWP\Common\Importer\ParserInterface;
use ImportWP\Common\Importer\State\ImporterState;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Runner\ImporterRunner;
use ImportWP\Common\Runner\ImporterRunnerState;
use ImportWP\Common\Util\Logger;
use ImportWP\Common\Util\Util;
use ImportWP\Container;

class Importer
{
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

        // TODO: 
        // $runner = new ImporterRunner($properties, $this);
        // $runner->process($id, $user, $importer_state);
        $this->process_chunk($id, $user, $importer_state);

        $this->mapper->teardown();
        $this->unregister_shutdown();
    }

    protected function process_chunk($id, $user, $importer_state)
    {
        $importer_state->populate([
            'status' => 'running'
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

        $max_total = $progress['end'] - $progress['start'];

        $i = $progress['start'] + $progress['current_row'];
        $max_chunk = min(10, $max_total);

        $chunk_limit = $i + $max_chunk;


        for ($i; $i < $chunk_limit; $i++) {

            $stats = [
                'inserts' => 0,
                'updates' => 0,
                'deletes' => 0,
                'skips' => 0,
                'errors' => 0,
            ];

            if ($importer_state->get_section() === 'import') {
                /**
                 * @var ParsedData $data
                 */
                $data = null;

                $data_parser = new DataParser($this->getParser(), $this->getMapper(), $this->config->getData());

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
            } elseif ($importer_state->get_section() === 'delete') {

                if ($this->getMapper()->permission() && $this->getMapper()->permission()->allowed_method('remove')) {

                    $GLOBALS['wp_object_cache']->delete('iwp_importer_config_' . $id, 'options');
                    $config = get_site_option('iwp_importer_config_' . $id);

                    $object_ids = $config['delete_ids'];
                    if ($object_ids && count($object_ids) > $i) {
                        $object_id = $object_ids[$i];
                        $this->getMapper()->delete($object_id);
                        $stats['deletes']++;

                        Logger::write('delete:' . $i . ' -object=' . $object_id);

                        $message = apply_filters('iwp/status/record_deleted', 'Record Deleted: #' . $object_id, $object_id);
                        Util::write_status_log_file_message($id, $session, $message, 'D', $progress['current_row']);
                    }
                }
            }

            $importer_state->update_importer_stats($stats);
            Util::write_status_session_to_file($id, $importer_state);

            $importer_state->increment_current_row();
            $progress = $importer_state->get_progress();

            ImporterState::set_state($id, $importer_state->get_raw());
        }

        // default status to idle after run
        $importer_state->populate([
            'status' => 'timeout'
        ]);

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
