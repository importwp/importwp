<?php

namespace ImportWP\Common\AddonAPI\Template;

class Template
{
    /**
     * @var Field[]
     */
    private $_fields = [];

    /**
     * @var Panel[]
     */
    private $_groups = [];

    public function register_group($name, $args = [])
    {
        $panel = new Panel($name, $args);
        $this->_groups[] = $panel;
        return $panel;
    }

    public function register_field($name, $args = [])
    {
        $this->_fields[] = new Field($name, $args);
    }

    public function register_attachment_field($name, $field_label = '', $args = [])
    {
        $this->_fields[] = new Field($name, array_merge($args, [
            'type' => 'attachment',
            'field_label' => $field_label,
        ]));
    }

    public function get_group_ids()
    {
        $output = [];
        foreach ($this->_groups as $group) {
            $output[] = $group->get_id();
        }

        return $output;
    }

    public function get_groups()
    {
        return $this->_groups;
    }

    public function get_fields()
    {
        return $this->_fields;
    }
}
