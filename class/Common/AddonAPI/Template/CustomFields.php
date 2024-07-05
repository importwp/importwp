<?php

namespace ImportWP\Common\AddonAPI\Template;

class CustomFields
{
    private $_name;
    private $_prefix;
    private $_fields = [];

    public function __construct($name, $prefix)
    {
        $this->_name = $name;
        $this->_prefix = $prefix;
    }

    public function get_prefix()
    {
        return $this->_prefix;
    }

    public function register_field($name, $type = 'text')
    {
        $id = sanitize_title($name);
        $this->_fields[$id] = [
            'id' => $id,
            'name' => $name,
            'type' => $type
        ];
    }

    public function get_dropdown_fields()
    {

        $tmp_fields = [];
        foreach ($this->_fields as $field_id => $field) {

            $tmp_fields[] = [
                'value' => $this->_prefix . '::' . $field['type'] . '::' . $field_id,
                'label' => $this->_name . ' - ' . $field['name']
            ];
        }

        return $tmp_fields;
    }

    public function get_fields()
    {
        return $this->_fields;
    }
}