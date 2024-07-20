<?php

namespace ImportWP\Common\AddonAPI\Importer;

use ImportWP\Common\Importer\ParsedData;

class ImporterData
{
    private $_id;
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
     * @var \ImportWP\Common\Importer\Template\Template
     */
    private $_template;

    private $_logs = [];

    /**
     * 
     * @param int $id 
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @param \ImportWP\Common\AddonAPI\Importer\Template\Template $addon_template
     * @param \ImportWP\Common\Importer\Importer $importer
     * @param \ImportWP\Common\Importer\Template\Template $template
     * @return void 
     */
    public function __construct($id, $data, $addon_template, $importer, $template)
    {
        $this->_id = $id;
        $this->_data = $data;
        $this->_addon_template = $addon_template;
        $this->_importer = $importer;
        $this->_template = $template;
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_panel($panel_id)
    {
        $panel = $this->_addon_template->get_panel($panel_id);
        return $panel ? new PanelData($panel, $this) : false;
    }

    public function get_custom_fields($custom_fields_id)
    {
        foreach ($this->_addon_template->get_custom_fields() as $custom_fields) {
            if ($custom_fields_id == $custom_fields->get_prefix()) {
                return new CustomFieldsData($custom_fields, $this);
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

        return $this->_template->process_attachment($this->get_id(), $attachment_data, '', $filesystem, $ftp, $attachment);
    }

    public function add_meta($key, $value, $unique = false)
    {
        switch ($this->_template->get_mapper()) {
            case 'user':
                $result = add_user_meta($this->get_id(), $key, $value, $unique);
                break;
            case 'term':
                $result = add_term_meta($this->get_id(), $key, $value, $unique);
                break;
            default:
                $result = add_post_meta($this->get_id(), $key, $value, $unique);
                break;
        }

        return $result;
    }

    public function update_meta($key, $value, $prev_value = '')
    {
        switch ($this->_template->get_mapper()) {
            case 'user':
                $result = update_user_meta($this->get_id(), $key, $value, $prev_value);
                break;
            case 'term':
                $result = update_term_meta($this->get_id(), $key, $value, $prev_value);
                break;
            default:
                $result = update_post_meta($this->get_id(), $key, $value, $prev_value);
                break;
        }

        return $result;
    }

    public function delete_meta($key, $value = '')
    {
        switch ($this->_template->get_mapper()) {
            case 'user':
                $result = delete_user_meta($this->get_id(), $key, $value, $value);
                break;
            case 'term':
                $result = delete_term_meta($this->get_id(), $key, $value, $value);
                break;
            default:
                $result = delete_post_meta($this->get_id(), $key, $value, $value);
                break;
        }

        return $result;
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
