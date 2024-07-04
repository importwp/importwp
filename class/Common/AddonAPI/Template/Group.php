<?php

namespace ImportWP\Common\AddonAPI\Template;

class Group
{
    /**
     * @var Field[]
     */
    private $_fields = [];

    public function register_field($name, $args = [])
    {
        $field = new Field($name, $args);
        $this->_fields[] = $field;
        return $field;
    }

    public function register_attachment_field($name, $field_label = '', $args = [])
    {
        $field = new Field($name, array_merge($args, [
            'type' => 'attachment',
            'field_label' => $field_label,
        ]));
        $this->_fields[] = $field;
        return $field;
    }

    public function get_fields()
    {
        return $this->_fields;
    }

    /**
     * @param \ImportWP\Common\Importer\Template\Template $template 
     * @return [] 
     */
    public function get_template_data($template)
    {
        $output = [];
        foreach ($this->_fields as $field) {
            $output[] = $field->get_template_data($template);
        }

        return $output;
    }
}
