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
        return $this->_data->getValue($panel_id . '.' . $field_id, 'default');
    }

    public function set_value($panel_id, $field_id, $value)
    {
        $data = $this->_data->getData('default');
        $data["{$panel_id}.{$field_id}"] = $value;
        $this->_data->update($data, 'default');
    }

    public function enable_field($panel_id, $field_id)
    {
        iwp()->importer->setEnabled("{$panel_id}.{$field_id}", true);
    }

    public function get_group($panel_id)
    {
        return new ImporterRecordGroupData($panel_id, $this->_data);
    }
}
