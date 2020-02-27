<?php

namespace ImportWP\Common\Importer;

interface ConfigInterface
{
    /**
     * Get setting
     *
     * @param $key
     *
     * @return mixed
     */
    public function get($key);

    /**
     * Set setting
     *
     * @param $key
     * @param $data
     *
     * @return mixed
     */
    public function set($key, $data);

    /**
     * Get data to import
     *
     * @return array
     */
    public function getData();

    public function getIndex($key, $record);

    public function setIndex($key, $record, $start, $end);

    public function readIndexes($key);

    public function storeIndexes($key);

    public function getRecordCount($key);

    public function getIndexFile($key);
}
