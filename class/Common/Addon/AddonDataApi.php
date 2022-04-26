<?php

namespace ImportWP\Common\Addon;

class AddonDataApi
{
    protected $_addon;
    protected $_object_id;
    protected $_section_id;
    protected $_data;
    protected $_importer_model;
    protected $_template;

    public function __construct($addon, $object_id, $section_id, $data, $importer_model, $template)
    {
        $this->_addon = $addon;
        $this->_object_id = $object_id;
        $this->_section_id = $section_id;
        $this->_data = $data;
        $this->_importer_model = $importer_model;
        $this->_template = $template;
    }

    public function addon()
    {
        return $this->_addon;
    }

    public function object_id()
    {
        return $this->_object_id;
    }

    public function section_id()
    {
        return $this->_section_id;
    }

    public function data($key = false)
    {
        if ($key) {
            return isset($this->_data[$key]) ? $this->_data[$key] : false;
        }
        return $this->_data;
    }

    public function importer_model()
    {
        return $this->_importer_model;
    }

    public function template()
    {
        return $this->_template;
    }
}
