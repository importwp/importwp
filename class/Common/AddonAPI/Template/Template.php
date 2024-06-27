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
        $this->_groups[] = new Panel($name, $args);
    }

    public function register_field($name, $args = [])
    {
        $this->_fields[] = new Field($name, $args);
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
