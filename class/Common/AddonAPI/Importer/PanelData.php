<?php

namespace ImportWP\Common\AddonAPI\Importer;

use ImportWP\Common\AddonAPI\Importer\Template\Field;
use ImportWP\Common\AddonAPI\Importer\Template\FieldGroup;

class PanelData
{
    /**
     * @var \ImportWP\Common\AddonAPI\Importer\Template\Panel
     */
    private $_panel;

    /**
     * @var \ImportWP\Common\AddonAPI\Importer\ImporterData
     */
    private $_addon_data;

    private $_fields = [];

    /**
     * @param \ImportWP\Common\AddonAPI\Importer\Template\Panel $panel 
     * @param \ImportWP\Common\AddonAPI\Importer\ImporterData $addon_data
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

    public function get_value($field_id = null)
    {
        $field_prefix = $this->get_field_prefix();
        $group_id = $this->get_data_group_id();

        // Check group permission
        $permission_key = $group_id;
        $allowed = $this->_addon_data->get_data()->permission()->validate([$permission_key => ''], $this->_addon_data->get_data()->getMethod(), $group_id);
        $is_allowed = isset($allowed[$permission_key]) ? true : false;
        if (!$is_allowed) {
            return false;
        }

        if ($this->_panel->is_repeater()) {

            $max_rows = intval($this->_addon_data->get_data()->getValue($field_prefix . '._index', $group_id));
            $output = [];
            for ($i = 0; $i < $max_rows; $i++) {
                $row = [];
                foreach ($this->_panel->get_fields() as $field) {

                    $args = [
                        'data_group' => $this->get_data_group_id(),
                        'field_prefix' => $this->get_field_prefix() . '.' . $i,
                    ];

                    if (is_a($field, \ImportWP\Common\AddonAPI\Importer\Template\Field::class)) {

                        $field_data = new FieldData($field, $this->_addon_data, $args);
                        $row[$field->get_id()] = $field_data->get_value();
                    } elseif (is_a($field, \ImportWP\Common\AddonAPI\Importer\Template\FieldGroup::class)) {
                        $field_group_data = new FieldGroupData($field, $this->_addon_data, $args);
                        $row[$field->get_id()] = $field_group_data->get_value();
                    }
                }
                $output[] = $row;
            }

            return $output;
        } else {

            $output = [];
            foreach ($this->_panel->get_fields() as $field) {

                // dont fetch data if we are only requesting a single element
                if (!is_null($field_id) && $field->get_id() !== $field_id) {
                    continue;
                }

                $args = [
                    'data_group' => $this->get_data_group_id(),
                    'field_prefix' => $this->get_field_prefix(),
                ];

                if (is_a($field, \ImportWP\Common\AddonAPI\Importer\Template\Field::class)) {

                    $field_data = new FieldData($field, $this->_addon_data, $args);
                    $output[$field->get_id()] = $field_data->get_value();
                } elseif (is_a($field, \ImportWP\Common\AddonAPI\Importer\Template\FieldGroup::class)) {

                    $field_group = new FieldGroupData($field, $this->_addon_data, $args);
                    $output[$field->get_id()] = $field_group->get_value();
                }

                if (!is_null($field_id)) {
                    return isset($output[$field_id]) ? $output[$field_id] : false;
                }
            }

            return $output;
        }

        return false;
    }

    /**
     * @param Field|FieldGroup $field 
     * @return FieldData|FieldGroupData 
     */
    private function get_field_data($field)
    {
        $field_id = $field->get_id();
        if (isset($this->_fields[$field_id])) {
            return $this->_fields[$field_id];
        }

        if (is_a($field, Field::class)) {
            $this->_fields[$field_id] = new FieldData($field, $this->_addon_data, [
                'data_group' => $this->get_data_group_id(),
                'field_prefix' => $this->get_field_prefix(),
            ]);
        } elseif (is_a($field, FieldGroup::class)) {
            $this->_fields[$field_id] = new FieldGroupData($field, $this->_addon_data, [
                'data_group' => $this->get_data_group_id(),
                'field_prefix' => $this->get_field_prefix(),
            ]);
        }

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

    public function log($field_id)
    {
        $this->_addon_data->log($field_id, $this->_panel->get_id());
    }
}
