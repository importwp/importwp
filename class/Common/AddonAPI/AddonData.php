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

    public function get_value($field_id, $group = null)
    {
        $fields = $this->_template->get_fields();

        $current_field = null;
        foreach ($fields as $field) {
            if ($field->get_id() === $field_id) {
                $current_field = $field;
                break;
            }
        }

        if (!$current_field) {
            throw new \Exception("Missing field: " . $field_id . ", group: " . $group);
        }

        if (is_null($group)) {
            $group = $current_field->get_group();
        }

        $panel = $this->_template->get_panel($group);
        if ($panel->is_repeater()) {

            $max_rows = intval($this->_data->getValue($panel->get_id() . '._index', $panel->get_id()));
            for ($i = 0; $i < $max_rows; $i++) {
                // TODO: We need to get data from group fields recursivly
            }
        }

        if ($current_field->get_type() === 'text') {
            return $this->_data->getValue($group . '.' . $field->get_id(), $group);
        } elseif ($current_field->get_type() === 'attachment') {
            // TODO: how do we handle attachment fields

            $data = $this->_data->getData($group);
            $attachment_prefix = $group . '.' . $field->get_id();

            $attachment_keys = [
                '_meta._title',
                '_meta._alt',
                '_meta._caption',
                '_meta._description',
                '_enable_image_hash',
                '_download',
                '_featured',
                '_remote_url',
                '_ftp_user',
                '_ftp_host',
                '_ftp_pass',
                '_ftp_path',
                '_local_url',
                '_meta._enabled'
            ];
            $attachment_data = [
                'location' => $data[$attachment_prefix . '.location'],
            ];

            foreach ($attachment_keys as $k) {
                if (isset($data[$attachment_prefix . '.settings.' . $k])) {
                    $attachment_data[$k] = $data[$attachment_prefix . '.settings.' . $k];
                } elseif (isset($data[$attachment_prefix . '.' . $k])) {
                    $attachment_data[$k] = $data[$attachment_prefix . '.' . $k];
                } else {
                    $attachment_data[$k] = '';
                }
            }

            return $this->process_attachment($this->get_id(), $attachment_data);
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
}
