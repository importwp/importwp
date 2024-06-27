<?php

namespace ImportWP\Common\AddonAPI\Template;

class Field
{
    private $_id;
    private $_name;
    private $_type = 'text';
    private $_group;

    public function __construct($name, $args = [])
    {
        $this->_id = $args['id'] ?? sanitize_title($name);
        $this->_name = $name;

        if (isset($args['type'])) {
            $this->_type = $args['type'];
        }

        if (isset($args['group'])) {
            $this->_group = $args['group'];
        }
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
        return [
            'core' => true,
            'type' => $this->get_type()
        ];
    }
}
