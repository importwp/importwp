<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
class AddonBaseContainer extends AddonBaseData
{
    public function register_field($field_name, $field_id, $settings = [])
    {
        $field = new AddonBaseField($this->addon(), [
            'id' => $field_id,
            'name' => $field_name,
            'type' => 'field',
            'settings' => $settings
        ]);

        $this->_data['fields'][] = $field;

        return $field;
    }



    public function register_group($group_name, $group_id, $callback)
    {
        $group = new AddonBaseGroup($this->addon(), $group_id, [
            'id' => $group_id,
            'name' => $group_name,
        ]);

        call_user_func($callback, $group);

        $this->_data['fields'][] = $group;

        return $group;
    }



    public function register_attachment_fields($field_name, $field_id, $field_label = 'Location', $group_args = [])
    {
        $field =  new AddonBaseField($this->addon(), [
            'id' => $field_id,
            'name' => $field_name,
            'field_label' => $field_label,
            'type' => 'attachment',
            'settings' => $group_args
        ]);

        $this->_data['fields'][] = $field;

        return $field;
    }
}
