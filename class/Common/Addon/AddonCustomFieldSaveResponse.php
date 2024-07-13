<?php

namespace ImportWP\Common\Addon;

use ImportWP\Common\Model\ImporterModel;
use ImportWP\Pro\Importer\Template\CustomFields;

/**
 * @deprecated 2.14.0
 */
class AddonCustomFieldSaveResponse
{
    protected $_raw_records = [];

    /**
     * @var ImporterModel
     */
    protected $_importer_model;

    /**
     * @var CustomFields
     */
    protected $_custom_fields;

    public function __construct($importer_model, $custom_fields)
    {
        $this->_custom_fields = $custom_fields;
        $this->_importer_model = $importer_model;
    }

    public function template()
    {
        return $this->_importer_model->getTemplate();
    }

    public function importer_model()
    {
        return $this->_importer_model;
    }

    public function custom_field()
    {
        return $this->_custom_fields;
    }

    public function processTextField($value)
    {
        return $this->custom_field()->processTextField($value);
    }

    public function processAttachmentField($value, $post_id, $overrides = [])
    {
        return $this->custom_field()->processAttachmentField($value, $post_id, array_merge($this->_raw_records, $overrides), '');
    }

    public function processSerializedField($value, $post_id, $overrides = [])
    {
        return $this->custom_field()->processSerializedField($value, $post_id, array_merge($this->_raw_records, $overrides), '');
    }

    public function processMappedField($value, $post_id, $overrides = [])
    {
        return $this->custom_field()->processMappedField($value, $post_id, array_merge($this->_raw_records, $overrides), '');
    }

    public function get_meta($object_id, $key = '', $single = false)
    {
        switch ($this->template()) {
            case 'user':
                return get_user_meta($object_id, $key, $single);
            case 'term':
                return get_term_meta($object_id, $key, $single);
            default:
                return get_post_meta($object_id, $key, $single);
        }
    }

    public function add_meta($object_id, $meta_key, $meta_value, $unique = false)
    {
        switch ($this->template()) {
            case 'user':
                return add_user_meta($object_id, $meta_key, $meta_value, $unique);
            case 'term':
                return add_term_meta($object_id, $meta_key, $meta_value, $unique);
            default:
                return add_post_meta($object_id, $meta_key, $meta_value, $unique);
        }
    }

    public function update_meta($object_id, $meta_key, $meta_value, $prev_value = '')
    {
        switch ($this->template()) {
            case 'user':
                return update_user_meta($object_id, $meta_key, $meta_value, $prev_value);
            case 'term':
                return update_term_meta($object_id, $meta_key, $meta_value, $prev_value);
            default:
                return update_post_meta($object_id, $meta_key, $meta_value, $prev_value);
        }
    }

    public function delete_meta($object_id, $meta_key, $meta_value = '')
    {
        switch ($this->template()) {
            case 'user':
                return delete_user_meta($object_id, $meta_key, $meta_value);
            case 'term':
                return delete_term_meta($object_id, $meta_key, $meta_value);
            default:
                return delete_post_meta($object_id, $meta_key, $meta_value);
        }
    }

    public function _set_records($custom_field_record, $prefix)
    {
        $records = [];
        foreach ($custom_field_record as $record => $value) {
            if (strpos($record, $prefix) === 0) {
                $records[substr($record, strlen($prefix))] = $value;
            }
        }

        $this->_raw_records = $records;
    }
}
