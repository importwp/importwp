<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
class AddonGroupDataApi extends AddonDataApi
{
    protected $_group;

    public function __construct($addon, $group, $object_id, $section_id, $data, $importer_model, $template)
    {
        parent::__construct($addon, $object_id, $section_id, $data, $importer_model, $template);

        $this->_group = $group;
    }

    public function group()
    {
        return $this->_group;
    }
}
