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
    private $_panels = [];

    public function register_panel($name, $args = [])
    {
        $panel = new Panel($name, $args);
        $this->_panels[] = $panel;
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

    public function get_panel_ids()
    {
        $output = [];
        foreach ($this->_panels as $panel) {
            $output[] = $panel->get_id();
        }

        return $output;
    }

    public function get_panels()
    {
        return $this->_panels;
    }

    public function get_fields()
    {
        return $this->_fields;
    }
}
