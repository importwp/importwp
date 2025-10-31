<?php

namespace ImportWP\Common\AddonAPI\Importer;

use ImportWP\Common\Importer\ParsedData;

class ImporterRecordGroupData
{
    /**
     * @var string
     */
    private $_id;

    /**
     * @var ParsedData
     */
    private $_data;

    private $_rows = [];

    private $_keys = [];

    /**
     * @param string $group_id
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @return void 
     */
    public function __construct($group_id, $data)
    {
        $this->_id = $group_id;
        $this->_data = $data;
        $this->setup($this->_data->getData('default'));
    }

    public function setup($data)
    {
        $max_rows = isset($data["{$this->_id}._index"]) ? intval($data["{$this->_id}._index"]) : 0;

        $this->_rows = [];
        $this->_keys = [];

        foreach ($data as $row_id => $value) {

            $matches = [];
            if (preg_match('/^' . $this->_id . '\.(\d+)\.(\S+)$/', $row_id, $matches) !== 1) {
                continue;
            }

            $row_index = intval($matches[1]);
            $row_key = $matches[2];

            // Don't capture data that shouldn't be there.
            if ($row_index > $max_rows - 1) {
                continue;
            }

            if (!isset($this->_rows[$row_index])) {
                $this->_rows[$row_index] = [];
            }

            if (!isset($this->_keys[$row_key])) {
                $this->_keys[$row_key] = '';
            }

            $this->_rows[$row_index][$row_key] = $value;
        }
    }

    public function get_rows()
    {
        return $this->_rows;
    }

    public function get_row($index)
    {
        return $this->_rows[$index];
    }

    public function add_row($data = [])
    {
        $row = [];
        foreach (array_keys($this->_keys) as $key) {
            $row[$key] = isset($data[$key]) ? $data[$key] : $this->_keys[$key];
        }

        $this->_rows[] = $row;

        return array_key_last($this->_rows);
    }

    public function delete_row($index)
    {
        $tmp = $this->get_rows();
        if (isset($tmp[$index])) {
            unset($tmp[$index]);
            $this->_rows = $tmp;
        }

        return $this->get_rows();
    }

    /**
     * Find a matching row based on a field value
     * @param string $field 
     * @param string|\Closure $value 
     * @return int
     */
    public function find_row($field, $value)
    {
        foreach ($this->_rows as $i => $row) {

            if (!isset($row[$field])) {
                continue;
            }

            if (is_callable($value) && $value($row[$field]) === true) {
                return $i;
            } elseif ($row[$field] === $value) {
                return $i;
            }
        }

        return false;
    }

    public function set_row_defaults($key, $value)
    {
        $this->_keys[$key] = $value;
    }

    /**
     * Flatten rows back into an array and add it back into the default group
     * @return void 
     */
    public function save()
    {
        // Remove existing group data from default group.
        $data = array_filter($this->_data->getData('default'), function ($key) {
            return preg_match('/^' . $this->_id . '\./', $key) !== 1;
        }, ARRAY_FILTER_USE_KEY);

        // Add row count
        $data["{$this->_id}._index"] = count($this->_rows);

        // Flatten data and add it to default group.
        foreach ($this->_rows as $index => $row) {
            foreach ($row as $key => $val) {
                $data["{$this->_id}.{$index}.{$key}"] = $val;
            }
        }

        $this->_data->update($data, 'default');
    }
}
