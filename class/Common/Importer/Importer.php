<?php

namespace ImportWP\Common\Importer;

use ImportWP\Common\Importer\ConfigInterface;
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

        /**
         * @var Properties $properties
         */
        $properties = Container::getInstance()->get('properties');

        $runner = new ImporterRunner($properties, $this);
        $runner->process($id, $user, $importer_state);

        Util::write_status_session_to_file($id, $importer_state);

        $this->mapper->teardown();
        $this->unregister_shutdown();
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
