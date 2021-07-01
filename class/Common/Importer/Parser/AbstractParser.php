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
        $output = $this->handle_custom_methods($output);

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

    public function handle_custom_methods($input)
    {
        $input = preg_replace_callback('/\[([\w]+)\(([^)]*)\)]/', function ($matches) {

            $method = $matches[1];

            $result = [];
            $args = [];

            // Dont split comma's if they are inside a double quote
            if (preg_match_all('/(?:".*?"|[^",\s]+)(?=\s*,|\s*$)/s', $matches[2], $result) > 0) {
                $args = $result[0];
                foreach ($args as &$arg) {

                    // Strip commas from start and end of string
                    $arg = preg_replace('/^(\'(.*)\'|"(.*)")$/s', '$2$3', $arg);
                }
            }

            if (is_callable($method)) {
                return call_user_func_array($method, $args);
            }

            return $matches[0];
        }, $input);
        return $input;
    }
}
