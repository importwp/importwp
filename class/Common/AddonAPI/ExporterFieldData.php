<?php

namespace ImportWP\Common\AddonAPI;

class ExporterFieldData
{
    private $_id;

    private $_value;

    public function __construct($id, $value = null)
    {
        $this->_id = $id;
        $this->set_value($value);
    }

    public function set_value($value)
    {
        $this->_value = $value;
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_value()
    {
        return $this->_value;
    }
}
