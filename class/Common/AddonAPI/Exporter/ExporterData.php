<?php

namespace ImportWP\Common\AddonAPI\Exporter;

class ExporterData
{
    private $_template_type;
    private $_args = [];
    private $_record = [];

    /**
     * @var GroupData[]
     */
    private $_groups = [];

    public function __construct($template_type, $args = [])
    {
        $this->_template_type = $template_type;
        $this->_args = $args;
    }

    public function get_type()
    {
        return $this->_template_type;
    }

    public function get_args()
    {
        return $this->_args;
    }

    public function get_record()
    {
        return $this->_record;
    }

    public function get_group($group_id)
    {
        if (!isset($this->_groups[$group_id])) {
            $this->_groups[$group_id]  = new GroupData($group_id, []);
        }
        return $this->_groups[$group_id];
    }

    public function load_record($record)
    {
        $this->_record = $record;
    }

    public function get_groups()
    {
        return $this->_groups;
    }
}
