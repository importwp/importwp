<?php

namespace ImportWP\Common\AddonAPI;

use ImportWP\Common\AddonAPI\Exporter\ExporterData;
use ImportWP\Common\AddonAPI\Exporter\ExporterSchema;

class ExporterAddon extends Addon
{
    /**
     * @var ExporterData
     */
    private $_exporter_data;

    public function __construct()
    {
        parent::__construct();

        $allowed_types = $this->export_types();
        foreach ($allowed_types as $allowed_type) {

            // pass template type to exporter
            add_filter('iwp/exporter/' . $allowed_type . '/fields', function ($fields, $template_args) use ($allowed_type) {
                return $this->exporter_modify_fields($fields, $template_args, $allowed_type);
            }, 10, 2);

            // get user custom code at this point before 'iwp/exporter_record/{type} is triggered. 
            add_filter('iwp/exporter/' . $allowed_type . '/setup_data', function ($record, $template_args) use ($allowed_type) {
                return $this->exporter_load_data($record, $template_args, $allowed_type);
            }, 10, 2);
        }
    }

    public function exporter_modify_fields($fields, $template_args, $template_type)
    {
        // capture schema setup
        $schema = new ExporterSchema($template_type, $template_args);
        $this->export_schema($schema);

        foreach ($schema->get_groups() as $group) {

            $fields['children'][$group->get_id()] = [
                'key' => $group->get_id(),
                'label' => $group->get_name(),
                'loop' => false,
                'fields' => $group->get_fields(),
                'children' => []
            ];
        }

        return $fields;
    }

    public function exporter_load_data($record, $template_args, $template_type)
    {
        // setup exporter data
        if (is_null($this->_exporter_data)) {
            $this->_exporter_data = new ExporterData($template_type, $template_args);
        }

        $this->_exporter_data->load_record($record);
        $this->export_data($this->_exporter_data);

        foreach ($this->_exporter_data->get_groups() as $group_id => $group_data) {

            if (!isset($record[$group_id])) {
                $record[$group_id] = [];
            }

            $data = [];
            foreach ($group_data->get_fields() as $field) {

                foreach ($field->get_values() as $sub_field_id => $sub_field_value) {
                    $data[$sub_field_id] = $sub_field_value;
                }
            }

            $record[$group_id] = $data;
        }

        return $record;
    }

    public function export_types()
    {
        return [];
    }

    /**
     * @param ExporterSchema $exporter 
     * @return void 
     */
    public function export_schema($exporter)
    {
    }

    /**
     * @param ExporterData $exporter 
     * @return void 
     */
    public function export_data($exporter)
    {
    }
}
