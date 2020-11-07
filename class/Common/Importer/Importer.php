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

    public function __construct(ConfigInterface $config)
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
    public function parser(ParserInterface $parser)
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
        return isset($this->end) && $this->end > $this->getRecordStart() ? $this->end : $this->parser->file()->getRecordCount();
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

        if ($this->status->has_section('importing')) {

            // TODO: Run through field map from config (xml or csv)
            $data_parser = new DataParser($this->parser, $this->mapper, $this->config->getData());

            for ($i = $start; $i < $end; $i++) {

                $this->status->refresh();

                // escape if invalid session
                if (!$this->status->validate()) {
                    $this->mapper->teardown();
                    $this->unregister_shutdown();
                    return;
                }

                // escape if we are paused or cancelled
                if ($this->status->is_paused() || $this->status->is_cancelled()) {
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

                    // Add in ability to filter input file records, could this be moved up before data parser (speed checks)?
                    $skip_record = apply_filters('iwp/importer/skip_record', false, $data, $this);
                    if ($skip_record) {
                        $this->record_time();
                        $this->status->record_skip();
                        continue;
                    }

                    $data = apply_filters('iwp/importer/before_mapper', $data, $this);
                    $data->map();


                    $this->status->record_success($data);
                } catch (ParserException $e) {


                    $this->status->record_error($e->getMessage());
                } catch (MapperException $e) {


                    $this->status->record_error($e->getMessage());
                } catch (FileException $e) {


                    $this->status->record_error($e->getMessage());
                }


                $this->record_time();
                $this->status->record_finished();

                if ($this->timeout()) {
                    $this->unregister_shutdown();
                    return;
                }
            }

            $this->status->set_section('deleting');

            $object_ids = $this->mapper->get_objects_for_removal();
            if (false !== $object_ids && count($object_ids) > 0) {
                $this->status->set_delete_total(count($object_ids));
            }

            $this->status->save();
        }


        if ($this->status->has_section('deleting')) {
            if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

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

                        if ($this->timeout($this->start_time)) {
                            $this->unregister_shutdown();
                            return;
                        }
                    }
                }
            }
        }

        $this->record_time();
        $this->status->complete();

        $this->mapper->teardown();
        $this->unregister_shutdown();
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
