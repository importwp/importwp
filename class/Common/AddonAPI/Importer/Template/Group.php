<?php

namespace ImportWP\Common\AddonAPI\Importer\Template;

class Group
{
    /**
     * @var Field[]|FieldGroup[]
     */
    private $_fields = [];

    public function register_group($name, $args = [])
    {
        $field_group = new FieldGroup($name, $args);
        $this->_fields[] = $field_group;
        return $field_group;
    }

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

        foreach ($this->get_fields() as $field) {
            if (is_a($field, FieldGroup::class)) {
                $output[] = $template->register_group(
                    $field->get_name(),
                    $field->get_id(),
                    $field->get_template_data($template),
                    $field->get_args()
                );
            } elseif (is_a($field, Field::class)) {
                $output[] = $field->get_template_data($template);
            }
        }

        return $output;
    }
}
