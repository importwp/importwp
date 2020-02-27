<?php

namespace ImportWP\Common\Importer;

interface FileInterface
{

    /**
     * Is there record next
     *
     * @return bool
     */
    public function hasNextRecord();

    /**
     * Is there record previous
     *
     * @return bool
     */
    public function hasPrevRecord();

    /**
     * Get next record
     *
     * @return array
     */
    public function getNextRecord();

    /**
     * Get record
     *
     * @param int $index
     *
     * @return string
     */
    public function getRecord($index = 0);

    /**
     * Get record count
     *
     * @return int
     */
    public function getRecordCount();

    /**
     * Get previous Record
     *
     * @return mixed
     */
    public function getPreviousRecord();
}
