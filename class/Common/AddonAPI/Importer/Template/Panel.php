<?php

namespace ImportWP\Common\AddonAPI\Importer\Template;

class Panel extends Group
{
    private $_id;
    private $_name;
    private $_repeater = false;

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

        if ($this->is_repeater()) {
            $args['type'] = 'repeatable';
        }

        return $args;
    }

    public function repeater($bool = true)
    {
        $this->_repeater = $bool;
        return $this;
    }

    public function is_repeater()
    {
        return $this->_repeater;
    }
}
