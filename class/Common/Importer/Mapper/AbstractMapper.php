<?php

namespace ImportWP\Common\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\PermissionInterface;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\Common\Model\ImporterModel;

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

    public function getUniqueIdentifiers($unique_fields = [])
    {

        // set via importer interface
        $unique_identifier = $this->importer->getSetting('unique_identifier');
        if (empty($unique_identifier)) {
            return $unique_fields;
        }

        $parts = array_filter(array_map('trim', explode(',', $unique_identifier)));
        if (empty($parts)) {
            return $unique_fields;
        }

        return $parts;
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
}
