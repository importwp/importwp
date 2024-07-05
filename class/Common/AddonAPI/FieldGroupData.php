<?php

namespace ImportWP\Common\AddonAPI;

use ImportWP\Common\AddonAPI\Template\FieldGroup;

class FieldGroupData
{
    /**
     * @var \ImportWP\Common\AddonAPI\Template\FieldGroup
     */
    private $_field_group;

    /**
     * @var \ImportWP\Common\AddonAPI\AddonData
     */
    private $_addon_data;

    /**
     * @var string
     */
    private $_data_group;

    /**
     * @var string
     */
    private $_field_prefix;

    /**
     * @param \ImportWP\Common\AddonAPI\Template\FieldGroup $field 
     * @param \ImportWP\Common\AddonAPI\AddonData $addon_data
     * @param array $args
     * @return void 
     */
    public function __construct($field_group, $addon_data, $args = [])
    {
        $this->_field_group = $field_group;
        $this->_addon_data = $addon_data;

        if (isset($args['data_group'])) {
            $this->_data_group = $args['data_group'];
        }

        if (isset($args['field_prefix'])) {
            $this->_field_prefix = $args['field_prefix'];
        }
    }

    public function get_value()
    {
        $output = [];
        foreach ($this->_field_group->get_fields() as $field) {

            $args = [
                'data_group' => $this->_data_group,
                'field_prefix' => $this->_field_prefix . '.' . $this->_field_group->get_id(),
            ];

            if (is_a($field, \ImportWP\Common\AddonAPI\Template\Field::class)) {

                $field_data = new FieldData($field, $this->_addon_data, $args);

                $output[$field->get_id()] = $field_data->get_value();
            } elseif (is_a($field, \ImportWP\Common\AddonAPI\Template\FieldGroup::class)) {

                $field_group = new FieldGroupData($field, $this->_addon_data, $args);

                $output[$field->get_id()] = $field_group->get_value();
            }
        }
        return $output;
    }
}
