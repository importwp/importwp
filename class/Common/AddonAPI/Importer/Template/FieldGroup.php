<?php

namespace ImportWP\Common\AddonAPI\Importer\Template;

class FieldGroup extends Group
{
    private $_id;
    private $_name;

    public function __construct($name, $args = [])
    {
        $this->_id = $args['id'] ?? sanitize_title($name);
        $this->_name = $name;
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_name()
    {
        return $this->_name;
    }

    public function get_args()
    {
        $args = [];
        return $args;
    }
}
