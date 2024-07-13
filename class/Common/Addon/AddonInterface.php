<?php

namespace ImportWP\Common\Addon;

use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Model\ImporterModel;

/**
 * @deprecated 2.14.0
 */
interface AddonInterface
{
    function get_meta($panel_id);

    /**
     * @param string $field_name
     * @param string $field_id
     * @param string $field_label
     * @param array $group_args
     * 
     * @return AddonFieldInterface
     */
    // function register_attachment_fields($field_name, $field_id, $field_label = 'Location', $group_args = []);

    /**
     * @param string $field_name
     * @param string $field_id
     * @param array $settings
     * 
     * @return AddonFieldInterface
     */
    // function register_field($field_name, $field_id, $settings = []);

    /**
     * @param string $name
     * @param string $id
     * @param callable $fields
     * @param array $settings
     * 
     * @return AddonPanelInterface
     */
    function register_panel($name, $id, $fields, $settings = []);

    /**
     * @param string $name
     * @param string $id
     * @param AddonFieldInterface[] $fields
     * 
     * @return AddonGroupInterface
     */
    // function register_group($name, $id, $fields);

    function store_meta($section_id, $object_id, $key, $value);

    /**
     * @param callable $api
     * @return void
     */
    function register_custom_fields($name, $api);

    /**
     * @return ImporterModel
     */
    function importer_model();

    /**
     * @return Template
     */
    function template();

    /**
     * @param \Callback $callback
     */
    function register_migrations($callback);
}
