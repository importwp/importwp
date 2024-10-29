<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
class AddonDataApi
{
    protected $_addon;
    protected $_object_id;
    protected $_section_id;
    protected $_data;
    protected $_importer_model;
    protected $_template;

    /**
     * @param AddonBase $addon
     * @param string $object_id
     * @param string $section_id
     * @param string[] $data
     * @param ImporterModel $importer_model
     * @param \ImportWP\Common\Importer\Template\Template $template
     */
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

    public function get_mapper()
    {
        return $this->template()->get_mapper();
    }

    public function get_panel_id()
    {
        return $this->section_id();
    }

    public function get_meta($section_id = false)
    {
        if ($section_id === false) {
            $section_id = $this->get_panel_id();
        }

        return $this->addon()->get_meta($section_id);
    }

    public function store_meta($key, $value, $i = false)
    {
        $this->addon()->store_meta($this->get_panel_id(), $this->object_id(), $key, $value, $i);
    }

    public function update_meta($key, $value, $is_unique = true)
    {
        $this->addon()->update_meta($this->object_id(), $key, $value, $is_unique);
    }
}
