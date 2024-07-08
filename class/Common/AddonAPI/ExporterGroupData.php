<?php

namespace ImportWP\Common\AddonAPI;

class ExporterGroupData
{
    private $_id;

    /**
     * @var ExporterFieldData[]
     */
    private $_fields = [];

    public function __construct($id, $fields = [])
    {
        $this->_id = $id;


        foreach ($fields as $field_id => $value) {
            $this->_fields[$field_id] = new ExporterFieldData($field_id, $value);
        }
    }

    public function get_field($field_id)
    {
        if (!isset($this->_fields[$field_id])) {
            $this->_fields[$field_id] = new ExporterFieldData($field_id);
        }

        return $this->_fields[$field_id];
    }

    public function get_fields()
    {
        return $this->_fields;
    }
}
