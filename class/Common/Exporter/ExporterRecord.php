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

    public function current(): mixed
    {
        return $this->_data[$this->keys[$this->position]];
    }

    public function next(): void
    {
        $this->position++;
    }

    public function key(): mixed
    {
        return $this->keys[$this->position];
    }

    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function count(): int
    {
        return count($this->keys);
    }

    public function offsetExists(mixed $offset): bool
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

        $this->keys = array_keys($this->_data);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->_data[$offset]);
        $this->keys = array_keys($this->_data);
    }
}
