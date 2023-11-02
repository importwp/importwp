<?php

namespace ImportWP\Common\Exporter;

class ExporterRecord implements \ArrayAccess
{
    private $_data = [];
    private $_mapper_type;

    public function __construct($data, $mapper_type)
    {
        $this->_data = $data;
        $this->_mapper_type = $mapper_type;
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!isset($this->_data[$offset])) {
            $value = apply_filters('iwp/exporter_record/' . $this->_mapper_type, null, $offset, $this->_data);
            if (!is_null($value)) {
                $this->_data[$offset] = $value;
            }
        }

        return isset($this->_data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->offsetExists($offset) ? $this->_data[$offset] : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->_data[] = $value;
        } else {
            $this->_data[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->_data[$offset]);
    }
}
