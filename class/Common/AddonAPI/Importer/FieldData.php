<?php

namespace ImportWP\Common\AddonAPI\Importer;

use ImportWP\Common\AddonAPI\Importer\Template\Field;

class FieldData
{
    /**
     * @var \ImportWP\Common\AddonAPI\Importer\Template\Field
     */
    private $_field;

    /**
     * @var \ImportWP\Common\AddonAPI\Importer\ImporterData
     */
    private $_addon_data;

    /**
     * @var string
     */
    private $_data_group;

    /**
     * @var string
     */
    private $_field_prefix;

    /**
     * @param \ImportWP\Common\AddonAPI\Importer\Template\Field $field 
     * @param \ImportWP\Common\AddonAPI\Importer\ImporterData $addon_data
     * @param array $args
     * @return void 
     */
    public function __construct($field, $addon_data, $args = [])
    {
        $this->_field = $field;
        $this->_addon_data = $addon_data;

        if (isset($args['data_group'])) {
            $this->_data_group = $args['data_group'];
        }

        if (isset($args['field_prefix'])) {
            $this->_field_prefix = $args['field_prefix'];
        }
    }

    public function get_value()
    {
        // Handle permssions
        $permission_key = $this->_field_prefix . '.' . $this->_field->get_id();
        $allowed = $this->_addon_data->get_data()->permission()->validate([$permission_key => ''], $this->_addon_data->get_data()->getMethod(), $this->_data_group);
        $is_allowed = isset($allowed[$permission_key]) ? true : false;
        if (!$is_allowed) {
            return false;
        }

        $field_type = $this->_field->get_type();
        if ($field_type === 'text') {

            return $this->_addon_data->get_data()->getValue($this->_field_prefix . '.' . $this->_field->get_id(), $this->_data_group);
        } elseif ($field_type === 'attachment') {
            // TODO: how do we handle attachment fields

            $data = $this->_addon_data->get_data()->getData($this->_data_group);
            $attachment_prefix = $this->_field_prefix . '.' . $this->_field->get_id();

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

            return $this->_addon_data->process_attachment($this->_addon_data->get_id(), $attachment_data);
        }

        return false;
    }
}
