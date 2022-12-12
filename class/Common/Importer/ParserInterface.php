<?php

namespace ImportWP\Common\Importer;

interface ParserInterface
{

    /**
     * @return FileInterface
     */
    public function file();

    /**
     * Set record to parse
     *
     * @param $record_index
     *
     * @return ParserInterface
     */
    public function getRecord($record_index);

    public function getRecordIndex();

    public function query($query);

    public function queryGroup($group);

    public function query_string($query);
}
