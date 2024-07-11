<?php

namespace ImportWP\Common\AddonAPI\Exporter;

class FieldData
{
    private $_id;

    private $_value;

    private $_sub_fields = [];

    public function __construct($id, $value = null)
    {
        $this->_id = $id;
        $this->set_value($value);
    }

    public function set_value($value, $suffix = null)
    {
        if (!is_null($suffix)) {
            $this->_sub_fields[$suffix] = $value;
        } else {
            $this->_value = $value;
        }
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_value()
    {
        return $this->_value;
    }

    public function get_values()
    {
        $tmp = [
            $this->get_id() => $this->get_value(),
        ];

        if (empty($this->_sub_fields)) {
            return $tmp;
        }


        foreach ($this->_sub_fields as $suffix => $value) {
            $tmp[$this->get_id() . '::' . $suffix] = $value;
        }

        return $tmp;
    }
}
