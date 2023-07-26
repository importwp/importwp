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

            // find files matching ._mapped._index

            $maps = [];
            foreach ($group['fields'] as $field_key => $field_value) {

                $matches = [];
                if (
                    // post.post_name._mapped._index
                    // custom_fields.0.value._mapped._index
                    preg_match('/^(.*?)\._mapped\._index$/', $field_key, $matches) === 1 &&
                    // ignore custom_fields.0._mapped._index old style FieldMapper
                    preg_match('/^custom_fields\.(?:[0-9]+)\._mapped\._index$/', $field_key) === 0 &&
                    intval($field_value) > 0
                ) {
                    $maps[$matches[1]] = $field_value;
                } else {
                    $output[$field_key] = $this->query_string($field_value);
                }
            }
        }

        if (!empty($maps)) {
            foreach ($maps as $field_key => $field_rows) {

                $data = [];
                $delimiter = false;

                if (isset($output[$field_key . '._mapped._delimiter']) && !empty($output[$field_key . '._mapped._delimiter'])) {

                    // get delimiter from FieldMap delimiter field, this has priority
                    $delimiter = $output[$field_key . '._mapped._delimiter'];
                } else {

                    // get delimiter from parent section settings. e.g. taxonomies and attachments
                    $lastPos = strrpos($field_key, '.');
                    if ($lastPos !== false) {
                        $tmp = substr($field_key, 0, $lastPos);
                        if (isset($output[$tmp . '.settings._delimiter'])) {
                            $delimiter = !empty($output[$tmp . '.settings._delimiter']) ? $output[$tmp . '.settings._delimiter'] : ',';
                        }
                    }
                }

                foreach ($output as $item_key => $item_value) {
                    $matches = [];
                    if (preg_match('/^' . $field_key . '\._mapped\.([0-9]+)\.(.*?)$/', $item_key, $matches) === 1) {

                        // row is to high
                        if (intval($matches[1]) >= intval($field_rows)) {

                            unset($output[$matches[0]]);
                            continue;
                        }

                        if (!isset($data[$matches[1]])) {
                            $data[$matches[1]] = [];
                        }

                        $data[$matches[1]][$matches[2]] = $item_value;

                        unset($output[$matches[0]]);
                    }
                }

                if (!empty($delimiter)) {

                    $field_parts = explode($delimiter, $output[$field_key]);
                    foreach ($field_parts as $k => $field_part) {
                        $field_parts[$k] = $this->map_field_data($field_part, array_values($data));
                    }
                    $output[$field_key] = implode($delimiter, $field_parts);
                } else {
                    $output[$field_key] = $this->map_field_data($output[$field_key], array_values($data));
                }
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
        // m: Multiline modifier
        // s: matches all characters including newlines
        $input = preg_replace_callback('/\[([\w]+)\((.*?)\)\]/ms', function ($matches) {

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

    public function map_field_data($input, $map)
    {
        foreach ($map as $map_data) {

            $condition = isset($map_data['_condition']) && !empty($map_data['_condition']) ? $map_data['_condition'] : 'equal';
            $key = isset($map_data['key']) ? $map_data['key'] : '';
            $output = isset($map_data['value']) ? $map_data['value'] : '';

            switch ($condition) {

                case 'gt':
                    $left = intval($input);
                    $right = intval($key);
                    if ($left > $right) {
                        return $output;
                    }
                    break;
                case 'gte':
                    $left = intval($input);
                    $right = intval($key);
                    if ($left >= $right) {
                        return $output;
                    }
                    break;
                case 'lt':
                    $left = intval($input);
                    $right = intval($key);
                    if ($left < $right) {
                        return $output;
                    }
                    break;
                case 'lte':
                    $left = intval($input);
                    $right = intval($key);
                    if ($left <= $right) {
                        return $output;
                    }
                    break;
                case 'contains':
                    if (stripos($input, trim($key)) !== false) {
                        return $output;
                    }
                    break;
                case 'in':
                    if (in_array($input, explode(',', $key))) {
                        return $output;
                    }
                    break;
                case 'not-equal':
                    if (trim($key) !== trim($input)) {
                        return $output;
                    }
                    break;
                case 'not-contains':
                    if (stripos($input, trim($key)) === false) {
                        return $output;
                    }
                    break;
                case 'not-in':
                    if (!in_array($input, explode(',', $key))) {
                        return $output;
                    }
                    break;
                default:
                    if (trim($key) === trim($input)) {
                        return $output;
                    }
                    break;
            }
        }
        return $input;
    }
}
