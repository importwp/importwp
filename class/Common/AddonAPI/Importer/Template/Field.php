<?php

namespace ImportWP\Common\AddonAPI\Importer\Template;

class Field
{
    private $_id;
    private $_name;
    private $_type = 'text';
    private $_group;
    private $_args = [];

    public function __construct($name, $args = [])
    {
        $this->_id = $args['id'] ?? sanitize_title($name);
        $this->_name = $name;

        if (isset($args['type'])) {
            $this->_type = $args['type'];
        }

        if (isset($args['group'])) {

            if (is_a($args['group'], \ImportWP\Common\AddonAPI\Importer\Template\Panel::class)) {
                $this->_group = $args['group']->get_id();
            } elseif (is_string($args['group'])) {
                $this->_group = $args['group'];
            }
        }

        $this->_args = $args;
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_name()
    {
        return $this->_name;
    }

    public function get_type()
    {
        return $this->_type;
    }

    public function get_group()
    {
        return $this->_group;
    }

    public function get_args()
    {
        return $this->_args;
    }

    public function get_arg($key)
    {
        return isset($this->_args[$key]) ? $this->_args[$key] : null;
    }

    /**
     * @param \ImportWP\Common\Importer\Template\Template $template 
     * @return [] 
     */
    public function get_template_data($template)
    {
        switch ($this->_type) {
            case 'text':
                return $template->register_field($this->get_name(), $this->get_id(), $this->get_args());
            case 'attachment':
                return $template->register_attachment_fields($this->get_name(), $this->get_id(), $this->get_arg('field_label'), $this->get_args());
        }
        return false;
    }
}
