<?php

namespace ImportWP\Common\AddonAPI;

class AddonData
{
    private $_id;
    private $_data = [];

    public function __construct($id, $data = [])
    {
        $this->_id = $id;
        $this->_data = $data;
    }

    public function get_id()
    {
        return $this->_id;
    }
}
