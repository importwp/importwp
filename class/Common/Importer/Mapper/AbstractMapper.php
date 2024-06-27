<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\PermissionInterface;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Util\Logger;

class AbstractMapper
{
    protected $ID;
    /**
     * Importer Template
     *
     * @var TemplateInterface
     */
    protected $template;
    /**
     * Importer Data
     *
     * @var ImporterModel
     */
    protected $importer;
    /**
     * Importer Permissions
     *
     * @var PermissionInterface
     */
    protected $permission;

    protected $unique_indentifier_data;

    public function __construct(ImporterModel $importer, Template $template, PermissionInterface $permission = null)
    {
        $this->importer = $importer;
        $this->template = $template;
        $this->permission = $permission;
    }

    public function permission()
    {
        if (is_null($this->permission)) {
            return false;
        }

        return $this->permission;
    }

    /**
     * 
     * @param ParsedData $data 
     * @return array 
     */
    public function exists_get_identifier($data)
    {
        $unique_fields = [];
        $has_unique_field = false;
        $meta_args = [];

        if ($this->importer->has_custom_unique_identifier()) {

            // custom unique identifier is stored in meta table
            $has_unique_field = true;
            $key = $this->importer->get_iwp_reference_meta_key();

            $value = $data->getValue($key, 'iwp');
            if (!$value) {
                $value = '';
            }

            $meta_args[] = array(
                'key'   => $key,
                'value' => $value
            );

            $this->set_unique_identifier_settings($key, $value);

            Logger::debug('AbstractMapper::exists_get_identifier -type="custom" -field="' . $key . '" -value="' . $value . '"');
        } elseif ($this->importer->has_field_unique_identifier()) {

            // we have set a specific identifier
            $unique_field = $this->importer->getSetting('unique_identifier');
            if ($unique_field !== null && !empty($unique_field)) {
                $unique_fields = is_string($unique_field) ? [$unique_field] : $unique_field;
            }

            Logger::debug('AbstractMapper::exists_get_identifier -type="field" -field="' . wp_json_encode($unique_fields) . '"');
        } else {

            // NOTE: fallback to allow templates to set this in pre 2.11.9
            $unique_fields = TemplateManager::get_template_unique_fields($this->template);

            // allow user to set unique field name, get from importer setting
            $unique_field = $this->importer->getSetting('unique_field');
            if ($unique_field !== null) {
                $unique_fields = is_string($unique_field) ? [$unique_field] : $unique_field;
            }

            $unique_fields = $this->getUniqueIdentifiers($unique_fields);
            $unique_fields = apply_filters('iwp/template_unique_fields', $unique_fields, $this->template, $this->importer);

            Logger::debug('AbstractMapper::exists_get_identifier -type="legacy" -fields="' . wp_json_encode($unique_fields) . '"');
        }


        return [$unique_fields, $meta_args, $has_unique_field];
    }

    public function set_unique_identifier_settings($field, $value)
    {
        $this->unique_indentifier_data = ['field' => $field, 'value' => $value];
    }

    public function get_unqiue_identifier_settings()
    {
        return $this->unique_indentifier_data;
    }

    public function getUniqueIdentifiers($unique_fields = [])
    {

        // set via importer interface
        $unique_identifier = $this->importer->getSetting('unique_identifier');
        if (empty($unique_identifier)) {
            return $unique_fields;
        }

        if (is_string($unique_identifier)) {

            $unique_identifier = explode(',', $unique_identifier);
            if (!$unique_identifier) {
                return $unique_fields;
            }
        }

        $parts = array_filter(array_map('trim', (array)$unique_identifier));
        if (empty($parts)) {
            return $unique_fields;
        }

        return $parts;
    }

    public function find_unique_field_in_data($data, $field)
    {
        // TODO: this should only be done once per update
        $unique_value = $data->getValue($field, '*');
        if (empty($unique_value)) {
            $cf = $data->getData('custom_fields');
            if (!empty($cf)) {
                $cf_index = intval($cf['custom_fields._index']);
                if ($cf_index > 0) {
                    for ($i = 0; $i < $cf_index; $i++) {
                        $row = 'custom_fields.' . $i . '.';
                        $custom_field_key = apply_filters('iwp/custom_field_key', $cf[$row . 'key']);
                        if ($custom_field_key !== $field) {
                            continue;
                        }
                        $unique_value = $cf[$row . 'value'];
                        break;
                    }
                }
            }
        }

        return $unique_value;
    }

    public function is_session_tag_enabled()
    {
        $db_version = intval(get_site_option('iwp_db_version', 0));
        $config_data = get_site_option('iwp_importer_config_' . $this->importer->getId(), []);

        if ($db_version >= 7 && isset($config_data['features'], $config_data['features']['session_table']) && $config_data['features']['session_table']) {
            return true;
        }

        return false;
    }

    /**
     * Update or insert record in session table
     * 
     * Session table keeps track of what records where modified during the import
     *
     * @return void
     */
    public function add_session_tag($type)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $data = [
            'importer_id' => $this->importer->getId(),
            'item_id' => $this->ID,
            'item_type' => $type,
        ];
        $data_validation = ['%d', '%d', '%s'];

        if (is_multisite()) {
            $data['site_id'] = $wpdb->siteid;
            $data_validation[] = '%d';
        }

        $updated = $wpdb->update($wpdb->prefix . 'iwp_sessions', [
            'session' => $this->importer->getStatusId()
        ], $data, ['%s'], $data_validation);

        if (!$updated) {

            $wpdb->insert($wpdb->prefix . 'iwp_sessions', array_merge($data, [
                'session' => $this->importer->getStatusId()
            ]));
        }
    }

    /**
     * Remove record from session table
     *
     * @param int $id
     * @param string $type
     * @return void
     */
    public function remove_session_tag($id, $type)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $data = [
            'importer_id' => $this->importer->getId(),
            'item_id' => $id,
            'item_type' => $type,
        ];
        $data_validation = ['%d', '%d', '%s'];

        if (is_multisite()) {
            $data['site_id'] = $wpdb->siteid;
            $data_validation[] = '%d';
        }

        $wpdb->delete($wpdb->prefix . 'iwp_sessions', $data, $data_validation);
    }

    public function get_ids_without_session_tag($type)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $query = "SELECT item_id FROM `{$wpdb->prefix}iwp_sessions` WHERE importer_id={$this->importer->getId()} AND item_type='{$type}' AND `session` != '{$this->importer->getStatusId()}'";

        if (is_multisite()) {
            $query .= " AND site_id='{$wpdb->siteid}'";
        }

        $item_ids = $wpdb->get_col($query);
        return $item_ids;
    }

    public function update_custom_field($id, $key, $value, $unique = false, $skip_permissions = false)
    {
    }

    /**
     * Clear all meta before adding custom field
     */
    public function clear_custom_field($id, $key)
    {
    }

    public function get_custom_field($id, $key = '', $single = false)
    {
        return false;
    }

    public function add_reference_tag($data)
    {
        if (!$this->importer->has_custom_unique_identifier()) {
            return;
        }

        $key = $this->importer->get_iwp_reference_meta_key();
        $this->update_custom_field($this->ID, $key, $data->getValue($key, 'iwp'));
    }

    public function add_version_tag()
    {
    }
}
