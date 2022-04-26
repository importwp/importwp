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
use ImportWP\Common\Util\Logger;

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
     * @var ImporterStatus $status
     */
    private $status;

    /**
     * @var int $start
     */
    private $start;

    /**
     * @var int end
     */
    private $end;

    /**
     * @var int
     */
    private $start_time;

    /**
     * @var int
     */
    private $import_start_time;

    /**
     * @var ParserInterface $parser
     */
    private $parser;

    private $graceful_shutdown = true;

    private $chunk_size;

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

    public function status(ImporterStatus $status)
    {
        $this->status = $status;
        return $this;
    }

    public function permissions(PermissionInterface $permission)
    {
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

    public function chunk($size, $original_start, $original_end)
    {
        $this->chunk_size = $size;
        $this->original_start = $original_start;
        $this->original_end = $original_end;
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

    private function register_shutdown()
    {
        $this->graceful_shutdown = false;
        register_shutdown_function([$this, 'shutdown']);
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
     * @throws \Exception
     */
    public function import()
    {
        $this->start_time = time();
        $this->import_start_time = time();

        if ($this->parser == null) {
            throw new \Exception("Parser Not Loaded.");
        }

        if ($this->mapper == null) {
            throw new \Exception("Mapper Not Loaded.");
        }

        if ($this->status == null) {
            throw new \Exception("Status Not Loaded.");
        }

        $this->mapper->setup();

        $this->register_shutdown();

        $start = $this->getRecordStart();
        $end   = $this->getRecordEnd();
        $chunk_index = -1;

        Logger::write(__CLASS__ . '::import -start=' . $start . ' -end=' . $end);

        // get next chunk to process
        if (!is_null($this->chunk_size)) {
            list($start, $end, $chunk_index) = $this->status->get_next_chunk($this->chunk_size, $this->original_start, $this->original_end);
            $this->status->set_chunk_index($chunk_index);
            Logger::write(__CLASS__ . '::import -start=' . $start . ' -end=' . $end . ' -chunk=' . $chunk_index);
        }

        if ($this->status->has_section('importing')) {

            Logger::write(__CLASS__ . '::import -importing');

            // TODO: Run through field map from config (xml or csv)
            $data_parser = new DataParser($this->parser, $this->mapper, $this->config->getData());

            if ($start < $end) {
                for ($i = $start; $i < $end; $i++) {

                    $this->status->refresh();

                    // escape if invalid session
                    if (!$this->status->validate()) {
                        Logger::write(__CLASS__ . '::import -invalid-session');
                        $this->mapper->teardown();
                        $this->unregister_shutdown();
                        return;
                    }

                    // escape if we are paused or cancelled
                    if ($this->status->is_paused() || $this->status->is_cancelled()) {
                        Logger::write(__CLASS__ . '::import -paused');
                        $this->mapper->teardown();
                        $this->unregister_shutdown();
                        return;
                    }

                    /**
                     * @var ParsedData $data
                     */
                    $data = null;

                    try {

                        $data = $data_parser->get($i);

                        $skip_record = $this->filterRecords();
                        $skip_record = apply_filters('iwp/importer/skip_record', $skip_record, $data, $this);

                        if ($skip_record) {

                            // skip record
                            Logger::write(__CLASS__ . '::import -skip-record=' . $i);
                            $this->record_time();
                            $this->status->record_skip();

                            // set data to null, to flag chunk as skipped
                            $data = null;
                        } else {

                            // import
                            $data = apply_filters('iwp/importer/before_mapper', $data, $this);
                            $data->map();

                            Logger::write(__CLASS__ . '::import -success');
                            $this->status->record_success($data);
                        }
                    } catch (ParserException $e) {

                        Logger::write(__CLASS__ . '::import -parser-error=' . $e->getMessage());
                        $this->status->record_error($e->getMessage());
                    } catch (MapperException $e) {

                        Logger::write(__CLASS__ . '::import -mapper-error=' . $e->getMessage());
                        $this->status->record_error($e->getMessage());
                    } catch (FileException $e) {

                        Logger::write(__CLASS__ . '::import -file-error=' . $e->getMessage());
                        $this->status->record_error($e->getMessage());
                    }

                    if (!is_null($this->chunk_size)) {
                        Logger::write(__CLASS__ . '::import -record-chunk');
                        $this->status->record_chunk($chunk_index, $data);
                    }


                    $this->record_time();
                    $this->status->record_finished();
                    Logger::write(__CLASS__ . '::import -record-finished');

                    if ($this->timeout()) {
                        Logger::write(__CLASS__ . '::import -timeout');
                        $this->unregister_shutdown();
                        return;
                    }
                }
            }

            // escape if there are more chunks processing
            Logger::write(__CLASS__ . '::import -chunk-size=' . $this->chunk_size . ' -original-start=' . $this->original_start . ' -original_end=' . $this->original_end);
            if (!is_null($this->chunk_size)) {
                $has_more_chunks = $this->status->has_more_chunks($this->chunk_size, $this->original_start, $this->original_end);

                Logger::write(__CLASS__ . '::import -has-more-chunks=' . ($has_more_chunks ? 'yes' : 'no'));
                if ($has_more_chunks) {
                    Logger::write(__CLASS__ . '::import -chunk-timeout');
                    $this->status->timeout();
                    $this->mapper->teardown();
                    $this->unregister_shutdown();
                    return;
                }
            }

            Logger::write(__CLASS__ . '::import -deleting');
            $this->status->set_section('deleting');

            $object_ids = $this->mapper->get_objects_for_removal();
            if (false !== $object_ids && count($object_ids) > 0) {
                $this->status->set_delete_total(count($object_ids));

                if (!is_null($this->chunk_size)) {
                    $this->status->setup_chunk_delete_list($object_ids);
                }
            }

            $this->status->save();
        }


        if ($this->status->has_section('deleting')) {
            if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

                if (!is_null($this->chunk_size)) {
                    list($object_ids, $chunk_index) = $this->status->get_next_delete_chunk($this->chunk_size);
                }

                if (!isset($object_ids)) {
                    $object_ids = $this->mapper->get_objects_for_removal();
                }

                if (false !== $object_ids && count($object_ids) > 0) {

                    foreach ($object_ids as $object_id) {

                        if ($this->status->is_paused()) {
                            $this->mapper->teardown();
                            $this->unregister_shutdown();
                            return;
                        }

                        $this->mapper->delete($object_id);
                        $this->record_time();
                        $this->status->record_delete($object_id);

                        if (!is_null($this->chunk_size)) {
                            $this->status->record_chunk_delete($chunk_index);
                        }

                        if ($this->timeout($this->start_time)) {
                            $this->unregister_shutdown();
                            return;
                        }
                    }
                }

                if (!is_null($this->chunk_size)) {
                    $has_more_chunks = $this->status->has_more_delete_chunks($this->chunk_size);
                    if ($has_more_chunks) {
                        $this->unregister_shutdown();
                        return;
                    }
                }
            }
        }

        $this->status->clear_chunk_data();

        $this->record_time();
        $this->status->complete();

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

                        if(!$found){
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

    private function record_time()
    {
        $current_time = time();
        $this->status->record_time($current_time - $this->start_time);
        $this->start_time = $current_time;
    }

    public function shutdown()
    {
        if ($this->is_graceful_shutdown()) {
            $this->record_time();
            return;
        }

        // TODO: Log errors
        $error = error_get_last();
        if (!is_null($error)) {
            $this->status->record_fatal_error($error['message']);
            $this->mapper->teardown();
            echo json_encode($this->status->output()) . "\n";
            die();
        }

        $this->status->timeout();
        $this->mapper->teardown();
    }

    private function timeout()
    {
        $limit = intval(ini_get('max_execution_time'));
        if ($limit > 0) {
            $limit = ceil($limit * 0.9);
        } else {
            $limit = 3600;
        }

        $limit = apply_filters('iwp/importer/timeout', $limit);

        $current_time = time();
        if ($current_time - $this->import_start_time >= $limit) {
            $this->status->timeout();
            $this->mapper->teardown();
            return true;
        }
        return false;
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
