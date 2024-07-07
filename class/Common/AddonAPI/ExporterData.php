<?php

namespace ImportWP\Common\AddonAPI;

class ExporterData
{
    public function get_group($group_id)
    {
        return new ExporterGroupData();
    }
}
