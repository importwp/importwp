<?php

namespace ImportWP\Common\AddonAPI\Exporter;

class GroupSchema
{
    private $_id;

    private $_name;

    private $_fields = [];

    public function __construct($name, $id)
    {
        $this->_name = $name;
        $this->_id = $id;
    }

    public function add_field($field)
    {
        $this->_fields[] = $field;
    }

    public function get_fields()
    {
        return $this->_fields;
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_name()
    {
        return $this->_name;
    }
}
