<?php

namespace ImportWP\Common\AddonAPI;

class ExporterSchema
{
    /**
     * @var ExporterGroupSchema[]
     */
    private $_groups = [];

    private $_type;

    private $_args = [];

    public function __construct($type, $args = [])
    {
        $this->_type = $type;
        $this->_args = $args;
    }

    public function get_type()
    {
        return $this->_type;
    }

    public function get_args()
    {
        return $this->_args;
    }

    public function register_group($name, $id)
    {
        $group = new ExporterGroupSchema($name, $id);
        $this->_groups[] = $group;
        return $group;
    }

    public function get_groups()
    {
        return $this->_groups;
    }
}
