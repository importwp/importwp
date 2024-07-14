<?php

namespace ImportWP\Common\Addon;

use ImportWP\Common\Model\ImporterModel;

/**
 * @deprecated 2.14.0
 */
class AddonPanelDataApi extends AddonDataApi
{
    protected $_panel;

    /**
     * @param AddonBase $addon
     * @param AddonBasePanel $panel
     * @param string $object_id
     * @param string $section_id
     * @param string[] $data
     * @param ImporterModel $importer_model
     * @param \ImportWP\Common\Importer\Template\Template $template
     */
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
