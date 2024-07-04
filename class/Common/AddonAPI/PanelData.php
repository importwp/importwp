<?php

namespace ImportWP\Common\AddonAPI;

use ImportWP\Common\AddonAPI\Template\Panel;

class PanelData
{
    /**
     * @var \ImportWP\Common\AddonAPI\Template\Panel
     */
    private $_panel;

    /**
     * @var \ImportWP\Common\AddonAPI\AddonData
     */
    private $_addon_data;

    private $_fields = [];

    /**
     * @param \ImportWP\Common\AddonAPI\Template\Panel $panel 
     * @param \ImportWP\Common\AddonAPI\AddonData $addon_data
     * @return void 
     */
    public function __construct($panel, $addon_data)
    {
        $this->_panel = $panel;
        $this->_addon_data = $addon_data;
    }

    public function get_field_prefix()
    {
        return $this->_panel->get_id();
    }

    public function get_data_group_id()
    {
        return $this->_panel->get_id();
    }

    public function get_value()
    {
        $field_prefix = $this->get_field_prefix();
        $group_id = $this->get_data_group_id();

        if ($this->_panel->is_repeater()) {

            $max_rows = intval($this->_addon_data->get_data()->getValue($field_prefix . '._index', $group_id));
            $output = [];
            for ($i = 0; $i < $max_rows; $i++) {
                $row = [];
                foreach ($this->_panel->get_fields() as $field) {

                    // TODO: let FieldData handle this
                    $field_data = new FieldData($field, $this->_addon_data, [
                        'data_group' => $this->get_data_group_id(),
                        'field_prefix' => $this->get_field_prefix() . '.' . $i,
                    ]);

                    $row[$field->get_id()] = $field_data->get_value();

                    // $row[$field->get_id()] = $this->_addon_data->get_data()->getValue($field_prefix . '.' . $i . '.' . $field->get_id(), $group_id);
                }
                $output[] = $row;
            }

            return $output;
        }

        return false;
    }

    /**
     * @param Field $field 
     * @return FieldData 
     */
    private function get_field_data($field)
    {
        $field_id = $field->get_id();
        if (isset($this->_fields[$field_id])) {
            return $this->_fields[$field_id];
        }

        $this->_fields[$field_id] = new FieldData($field, $this->_addon_data, [
            'data_group' => $this->get_data_group_id(),
            'field_prefix' => $this->get_field_prefix(),
        ]);
        return $this->_fields[$field_id];
    }

    public function get_field($field_id)
    {
        $current_field = null;
        foreach ($this->_panel->get_fields() as $field) {
            if ($field->get_id() === $field_id) {
                $current_field = $field;
                break;
            }
        }

        if (!$current_field) {
            throw new \Exception("Missing field: " . $field_id);
        }

        return $this->get_field_data($field);
    }
}
