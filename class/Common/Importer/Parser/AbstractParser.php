<?php

namespace ImportWP\Common\Importer\Parser;

use ImportWP\Common\Importer\FileInterface;

abstract class AbstractParser
{
    /**
     * @var \ImportWP\Common\Importer\FileInterface $file
     */
    protected $file;

    protected $record_index;
    protected $record;

    /**
     * Parser constructor.
     *
     * @param \ImportWP\Common\Importer\FileInterface $file
     */
    public function __construct(FileInterface $file)
    {
        $this->file = $file;
    }

    public function getRecord($record_index = 0)
    {

        if ($this->record_index !== $record_index) {
            $this->record_index = $record_index;
            $this->record       = $this->file->getRecord($this->getRecordIndex());
            $this->onRecordLoaded();
        }

        return $this;
    }

    public function getRecordIndex()
    {
        return $this->record_index;
    }

    abstract protected function onRecordLoaded();

    public function queryGroup($group)
    {
        $output = [];

        if (isset($group['fields'])) {
            foreach ($group['fields'] as $field_key => $field_value) {
                $output[$field_key] = $this->query_string($field_value);
            }
        }

        return $output;
    }

    /**
     * Parse Query String for {} run query on them
     *
     * @param string $query
     *
     * @return string
     */
    public function query_string($query)
    {

        $output = preg_replace_callback('/{(.*?)}/', array($this, 'query_matches'), $query);

        return $output;
    }

    public function query_matches($matches)
    {
        if (isset($matches[0]) && isset($matches[1])) {
            return $this->query($matches[1]);
        }

        return '';
    }

    abstract public function query($query);

    public function file()
    {
        return $this->file;
    }
}
