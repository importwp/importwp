<?php

namespace ImportWP\Common\AddonAPI\Importer;

use ImportWP\Common\Importer\ParsedData;

class ImporterRecordData
{
    /**
     * @var ParsedData
     */
    private $_data;

    /**
     * @var \ImportWP\Common\AddonAPI\Importer\Template\Template
     */
    private $_addon_template;

    /**
     * @var \ImportWP\Common\Importer\Importer
     */
    private $_importer;

    /**
     * 
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @param \ImportWP\Common\AddonAPI\Importer\Template\Template $addon_template
     * @param \ImportWP\Common\Importer\Importer $importer
     * @return void 
     */
    public function __construct($data, $addon_template, $importer)
    {
        $this->_data = $data;
        $this->_addon_template = $addon_template;
        $this->_importer = $importer;
    }

    public function get_value($panel_id, $field_id)
    {
        return $this->_data->getValue($panel_id . '.' . $field_id);
    }
}
