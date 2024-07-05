<?php

namespace ImportWP\Common\AddonAPI;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\Template;

class AddonData
{
    private $_id;
    /**
     * @var ParsedData
     */
    private $_data;

    /**
     * @var \ImportWP\Common\AddonAPI\Template\Template
     */
    private $_template;

    /**
     * @var \ImportWP\Common\Importer\Importer
     */
    private $_importer;

    /**
     * @var \ImportWP\Common\Importer\Template\Template
     */
    private $_importer_template;

    private $_logs = [];

    /**
     * 
     * @param int $id 
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @param \ImportWP\Common\AddonAPI\Template\Template $template
     * @param \ImportWP\Common\Importer\Importer $importer
     * @param \ImportWP\Common\Importer\Template\Template $importer_template
     * @return void 
     */
    public function __construct($id, $data, $template, $importer, $importer_template)
    {
        $this->_id = $id;
        $this->_data = $data;
        $this->_template = $template;
        $this->_importer = $importer;
        $this->_importer_template = $importer_template;
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_panel($panel_id)
    {
        $panel = $this->_template->get_panel($panel_id);
        return $panel ? new PanelData($panel, $this) : false;
    }

    public function get_custom_fields($custom_fields_id)
    {
        foreach ($this->_template->get_custom_fields() as $custom_fields) {
            if ($custom_fields_id == $custom_fields->get_prefix()) {
                return new CustomFieldsData($custom_fields, $this, $this->_importer_template);
            }
        }

        return false;
    }

    public function process_attachment($id, $attachment_data)
    {
        /**
         * @var ImportWP\Common\Filesystem\Filesystem $filesystem
         */
        $filesystem = \ImportWP\Container::getInstance()->get('filesystem');

        /**
         * @var ImportWP\Common\Ftp\Ftp $ftp
         */
        $ftp = \ImportWP\Container::getInstance()->get('ftp');

        /**
         * @var ImportWP\Common\Attachment\Attachment $attachment
         */
        $attachment = \ImportWP\Container::getInstance()->get('attachment');

        return $this->_importer_template->process_attachment($this->get_id(), $attachment_data, '', $filesystem, $ftp, $attachment);
    }

    public function update_meta($key, $value, $prev_value = '', $skip_permissions = false)
    {
        $this->_importer->getMapper()->update_custom_field($this->get_id(), $key, $value, $prev_value, $skip_permissions);
    }

    public function delete_meta($key, $meta_value = '')
    {
        $this->_importer->getMapper()->clear_custom_field($this->get_id(), $key);
    }

    public function get_data()
    {
        return $this->_data;
    }

    public function log($field_id, $group_id)
    {
        if (!isset($this->_logs[$group_id])) {
            $this->_logs[$group_id] = [];
        }

        $this->_logs[$group_id][] = $field_id;
    }

    public function get_logs()
    {
        return $this->_logs;
    }
}
