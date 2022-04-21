<?php

namespace ImportWP\Common\Addon;

class AddonPanelDataApi extends AddonDataApi
{
    protected $_panel;

    public function __construct($addon, $panel, $object_id, $section_id, $data, $importer_model, $template)
    {
        parent::__construct($addon, $object_id, $section_id, $data, $importer_model, $template);

        $this->_panel = $panel;
    }

    public function panel()
    {
        return $this->_panel;
    }
}
