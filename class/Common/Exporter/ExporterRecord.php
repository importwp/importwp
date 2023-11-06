<?php

namespace ImportWP\Common\Exporter;

class ExporterRecord implements \ArrayAccess, \Iterator, \Countable
{
    private $_data = [];
    private $_mapper_type;
    private $keys = array();
    private $position;

    public function __construct($data, $mapper_type)
    {
        $this->_data = $data;
        $this->_mapper_type = $mapper_type;

        $this->keys = array_keys($this->_data);
    }

    public function current()
    {
        return $this->_data[$this->keys[$this->position]];
    }

    /** @return void  */
    public function next()
    {
        $this->position++;
    }

    public function key()
    {
        return $this->keys[$this->position];
    }

    /** @return bool  */
    public function valid()
    {
        return isset($this->keys[$this->position]);
    }

    /** @return void  */
    public function rewind()
    {
        $this->position = 0;
    }

    /** @return int<0, \max>  */
    public function count()
    {
        return count($this->keys);
    }

    /**
     * @param mixed $offset 
     * @return bool 
     */
    public function offsetExists($offset)
    {
        if (!isset($this->_data[$offset])) {
            $value = apply_filters('iwp/exporter_record/' . $this->_mapper_type, null, $offset, $this->_data);
            if (!is_null($value)) {
                $this->_data[$offset] = $value;
                $this->keys = array_keys($this->_data);
            }
        }

        return isset($this->_data[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->_data[$offset] : null;
    }

    /**
     * @param mixed $offset 
     * @param mixed $value 
     * @return void 
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->_data[] = $value;
        } else {
            $this->_data[$offset] = $value;
        }

        $this->keys = array_keys($this->_data);
    }

    /**
     * @param mixed $offset 
     * @return void 
     */
    public function offsetUnset($offset)
    {
        unset($this->_data[$offset]);
        $this->keys = array_keys($this->_data);
    }
}
