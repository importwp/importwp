<?php

namespace ImportWP\Common\AddonAPI\Importer;

class CustomFieldsData
{
    /**
     * @var \ImportWP\Common\AddonAPI\Importer\Template\CustomFields
     */
    private $_custom_fields;

    /**
     * @var \ImportWP\Common\AddonAPI\Importer\ImporterData
     */
    private $_addon_data;

    private $_data_cache = null;

    /**
     * @param \ImportWP\Common\AddonAPI\Importer\Template\CustomFields $panel 
     * @param \ImportWP\Common\AddonAPI\Importer\ImporterData $addon_data
     * @return void 
     */
    public function __construct($custom_fields, $addon_data)
    {
        $this->_custom_fields = $custom_fields;
        $this->_addon_data = $addon_data;
    }

    public function get_value($field_id)
    {
        if (is_null($this->_data_cache)) {

            $this->_data_cache = [];

            $custom_field_data = $this->_addon_data->get_data()->getData('custom_fields');
            $max_rows = intval($custom_field_data['custom_fields._index']);

            for ($i = 0; $i < $max_rows; $i++) {

                // skip if field is not part of these custom fields
                if (strpos($custom_field_data['custom_fields.' . $i . '.key'], $this->_custom_fields->get_prefix() . '::') !== 0) {
                    continue;
                }

                // TODO: check to see if field is registered, and if so capture data and process

                $current_field = false;
                foreach ($this->_custom_fields->get_fields() as $custom_field_id => $custom_field) {
                    if ($custom_field_data['custom_fields.' . $i . '.key'] === $this->_custom_fields->get_prefix() . '::' . $custom_field['type'] . '::' . $custom_field_id) {
                        $current_field = $custom_field;
                        break;
                    }
                }

                if (!$current_field) {
                    continue;
                }

                // capture custom field settings
                $this->_data_cache[$current_field['id']] = [
                    'field' => $current_field,
                    'settings' => []
                ];
                foreach ($custom_field_data as $k => $v) {

                    $cf_prefix = 'custom_fields.' . $i . '.';
                    $key_length = strlen($cf_prefix);
                    if (strpos($k, $cf_prefix) !== 0) {
                        continue;
                    }

                    $this->_data_cache[$current_field['id']]['settings'][substr($k, $key_length)] = $v;
                }
            }
        }

        if (!isset($this->_data_cache[$field_id])) {
            return false;
        }

        // TODO: process custom field
        $custom_field = $this->_data_cache[$field_id]['field'];
        $settings = $this->_data_cache[$field_id]['settings'];

        // check permissions
        $permission_key = 'custom_fields' . '.' . apply_filters('iwp/custom_field_key', $settings['key']);
        $allowed = $this->_addon_data->get_data()->permission()->validate([$permission_key => ''], $this->_addon_data->get_data()->getMethod(), 'custom_fields');
        $is_allowed = isset($allowed[$permission_key]) ? true : false;
        if (!$is_allowed) {
            return false;
        }

        $value = $settings['value'];
        $value = apply_filters('iwp/template/process_field', $value, $field_id, iwp()->importer);

        switch ($custom_field['type']) {
            case 'attachment':
                $settings['location'] = $value;
                return $this->_addon_data->process_attachment($this->_addon_data->get_id(), $settings);
            case 'text':
            default:
                return $value;
        }

        return false;
    }

    public function log($field_id)
    {
        $this->_addon_data->log($field_id, $this->_custom_fields->get_prefix());
    }
}
