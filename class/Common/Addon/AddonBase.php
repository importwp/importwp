<?php

namespace ImportWP\Common\Addon;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Model\ImporterModel;

final class AddonBase implements AddonInterface
{
    /**
     * @var callable
     */
    private $_run_conditions;

    /**
     * @var AddonBasePanel[]
     */
    private $_sections = [];

    private $name;

    private $id;

    private $_meta = [];

    private $_process_mapper;

    private $_process_data;

    private $_panels_callback;

    /**
     * @var AddonCustomFieldsApi[] $_custom_fields
     */
    private $_custom_fields = [];

    private $_init_callback;

    /**
     * @var ImporterModel
     */
    private $_importer_model;

    /**
     * @var Template
     */
    private $_template;

    public function __construct($name, $id, $callback)
    {
        $this->name = $name;
        $this->id = $id;
        $this->_init_callback = $callback;

        add_action('iwp/register_events', function ($event_handler, $service_provider) {

            // template
            $event_handler->listen('template.fields', [$this, '_register_template_fields']);
            $event_handler->listen('template.pre_process_groups', [$this, '_data_groups']);
            $event_handler->listen('template.pre_process', [$this, '_pre_process']);
            $event_handler->listen('template.process', [$this, '_process']);

            // custom fields
            $event_handler->listen('importer.custom_fields.init', [$this, '_custom_fields_init']);
            $event_handler->listen('importer.custom_fields.get_fields', [$this, '_custom_fields_get_fields']);
            $event_handler->listen('importer.custom_fields.process_field', [$this, '_custom_fields_process_field']);
            $event_handler->listen('importer.custom_fields.post_process', [$this, '_custom_fields_post_process']);
        }, 10, 2);
    }

    public function get_name()
    {
        return $this->name;
    }

    public function get_id()
    {
        return $this->id;
    }

    /**
     * Register template fields
     *
     * @param array $fields
     * @param Template $template
     * @param ImporterModel $importer_model
     * @return array
     */
    public function _register_template_fields($fields, $template, $importer_model)
    {
        $this->_try_init($importer_model, $template);

        if (!is_null($this->_run_conditions) && is_callable($this->_run_conditions)) {

            $is_allowed = call_user_func_array($this->_run_conditions, [$template, $importer_model]);
            if (!$is_allowed) {
                return $fields;
            }
        }

        if (!is_null($this->_panels_callback) && is_callable($this->_panels_callback)) {
            call_user_func($this->_panels_callback, $importer_model);
        }

        // TODO: Add field modifiers
        foreach ($this->_sections as $section_id =>  $section) {

            $section->_register($importer_model);

            $section_data = $section->data();

            if (isset($section_data['settings']['maybe_run']) && is_callable($section_data['settings']['maybe_run']) && !call_user_func_array($section_data['settings']['maybe_run'], [$template, $importer_model])) {
                continue;
            }

            $group_fields = $section->fields();
            $group_fields = $this->_register_fields($group_fields, $template, $importer_model);


            $fields = array_merge($fields, [
                $template->register_group($section_data['name'], $section_id, (array)$group_fields, $section->settings())
            ]);
        }

        return $fields;
    }

    /**
     * @param AddonBaseField[]|AddonBaseGroup[] $group_fields
     * @param Template $template
     * @param ImporterModel $importer_model
     * 
     * @return []
     */
    public function _register_fields($group_fields, $template, $importer_model)
    {
        // array_values, reindexes array from zero
        $group_fields = array_values(array_filter($group_fields, function ($item) use ($template, $importer_model) {
            /**
             * @var AddonBaseField $item
             */
            return $item->_is_allowed($template, $importer_model);
        }));

        $group_fields = array_map(function ($item) use ($template, $importer_model) {

            /**
             * @var AddonBaseField $item
             */

            $field_data = $item->data();
            switch ($field_data['type']) {
                case 'group':
                    /**
                     * @var AddonBaseGroup $item
                     */
                    return $template->register_group($field_data['name'], $field_data['id'], $this->_register_fields($item->fields(), $template, $importer_model));
                    break;
                case 'attachment':
                    return $template->register_attachment_fields($field_data['name'], $field_data['id'], $field_data['field_label'], $field_data['settings']);
                    break;
                default:
                    return $template->register_field($field_data['name'], $field_data['id'], $field_data['settings']);
                    break;
            }
        }, $group_fields);

        return $group_fields;
    }

    public function _data_groups($groups, $data, $template)
    {
        $importer_model = $template->get_importer();
        $this->_try_init($importer_model, $template);

        if (empty($this->_sections)) {
            return $groups;
        }
        return array_merge((array) $groups, array_keys($this->_sections));
    }

    /**
     * @param ParsedData $data
     * @param ImporterModel $importer_model
     * @param Template $template
     * 
     * @return ParsedData
     */
    public function _pre_process($data, $importer_model, $template)
    {
        $this->_try_init($importer_model, $template);

        if (!is_null($this->_panels_callback) && is_callable($this->_panels_callback)) {
            call_user_func($this->_panels_callback, $importer_model);
        }

        // check to see what fields are enabled
        foreach ($this->_sections as $section_id => $section) {

            $section->_register($importer_model);

            $group_fields = $section->fields();
            $section_data = $data->getData($section_id);
            $section_settings = $section->settings();
            $repeatable = isset($section_settings['type']) && $section_settings['type'] == 'repeatable';
            $output = $this->_pre_process_fields($group_fields, $data, $section_id, $section_data, $importer_model, [], false, $repeatable);
            $data->replace($output, $section_id);
        }


        return $data;
    }

    /**
     * @param AddonBaseField[] $group_fields
     * @param ParsedData $data
     * @param string $section_id
     * @param array $data
     * @param ImporterModel $importer_model
     * @param array $output
     * 
     * @return array
     */
    public function _pre_process_fields($group_fields, $data, $section_id, $section_data, $importer_model, $output = [], $enable_id = false, $repeatable = false)
    {
        if ($repeatable) {
            $row_count = isset($section_data["{$section_id}._index"]) ? $section_data["{$section_id}._index"] :  0;

            $rows = [];

            for ($i = 0; $i < $row_count; $i++) {

                $row = [];
                $prefix = "{$section_id}.{$i}";

                if (isset($section_data["{$prefix}.row_base"]) && !empty($section_data["{$prefix}.row_base"])) {
                    $sub_rows = $data->getData($prefix);
                } else {
                    $sub_rows = [$section_data];
                }

                foreach ($sub_rows as $custom_field_row) {
                    $row = [];
                    foreach ($group_fields as $group_field) {

                        $field = $group_field->data();
                        $group_field->_enable_processing();

                        foreach ($custom_field_row as $field_id => $field_value) {
                            if (preg_match('/^' . str_replace('.', '\.', $prefix) . '\.(' . $field['id'] . '(\.\S+)?)$/', $field_id, $matches) !== 1) {
                                continue;
                            }

                            $row[$matches[1]] = $field_value;
                        }
                    }

                    $rows[] = $row;
                }
            }

            return $rows;
        }

        foreach ($group_fields as $group_field) {

            /**
             * @var AddonBaseField $group_field
             */

            $field = $group_field->data();

            // section.group_id or section.field_id
            $field_enable_id = false === $enable_id ? "{$section_id}.{$field['id']}" : $enable_id;

            if ($field['type'] === 'group') {

                /**
                 * @var AddonBaseGroup $group_field
                 */
                $output = $this->_pre_process_fields($group_field->fields(), $data, "{$section_id}.{$field['id']}",  $section_data, $importer_model, $output, $field_enable_id);
            }
            if ($importer_model->isEnabledField($field_enable_id)) {

                // Mark field for processing
                $group_field->_enable_processing();

                foreach ($section_data as $field_id => $field_value) {
                    if (preg_match('/^' . str_replace('.', '\.', $section_id) . '\.(' . $field['id'] . '(\.\S+)?)$/', $field_id, $matches) !== 1) {
                        continue;
                    }
                    $output[$matches[1]] = $field_value;
                }
            }
        }

        return $output;
    }

    public function update_meta($object_id, $key, $value, $is_unique = true)
    {
        switch ($this->_process_mapper) {
            case 'user':
                if (!$is_unique) {
                    add_user_meta($object_id, $key, $value);
                } else {
                    delete_user_meta($object_id, $key);
                    update_user_meta($object_id, $key, $value);
                }
                break;
            case 'post':
                if (!$is_unique) {
                    add_post_meta($object_id, $key, $value);
                } else {
                    delete_post_meta($object_id, $key);
                    update_post_meta($object_id, $key, $value);
                }
                break;
            case 'term':
                if (!$is_unique) {
                    add_term_meta($object_id, $key, $value);
                } else {
                    delete_term_meta($object_id, $key);
                    update_term_meta($object_id, $key, $value);
                }
                break;
        }
    }

    /**
     * @param integer $id
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @param ImportWP\Common\Model\ImporterModel $importer_model
     * @param ImportWP\Common\Importer\Template\Template $template
     * @return void
     */
    public function _process($id, $data, $importer_model, $template)
    {
        // setup
        $this->_process_mapper = $template->get_mapper();
        $this->_process_data = $data;
        $this->clear_meta();

        foreach ($this->_sections as $section_id => $section) {

            $group_fields = $section->fields();
            $section_settings = $section->settings();
            $repeatable = isset($section_settings['type']) && $section_settings['type'] == 'repeatable';

            $rows = $data->getData($section_id);
            if ($repeatable) {
                foreach ($rows as $i => $row) {
                    $this->_process_fields($id, $group_fields, $section_id, $row, $importer_model, $template, $i);
                }
            } else {
                $this->_process_fields($id, $group_fields, $section_id, $rows, $importer_model, $template, false);
            }

            $section->_process($id, $rows, $importer_model, $template);
        }

        // teardown
        $this->_process_mapper = null;
        $this->_process_data = null;

        return $id;
    }

    /**
     * @param integer $id
     * @param AddonBaseField[] $group_fields
     * @param string $section_id
     * @param mixed $data
     * @param ImportWP\Common\Model\ImporterModel $importer_model
     * @param ImportWP\Common\Importer\Template\Template $template
     * @return void
     */
    public function _process_fields($id, $group_fields, $section_id, $data, $importer_model, $template, $i = false)
    {
        $group_fields = array_values(array_filter($group_fields, function ($item) use ($template, $importer_model) {
            /**
             * @var AddonBaseField $item
             */
            return $item->_is_allowed($template, $importer_model) && $item->_is_processing_enabled();
        }));

        foreach ($group_fields as $group_field) {

            $field = $group_field->data();

            if ($field['type'] === 'group') {
                /**
                 * @var AddonBaseGroup $group_field
                 */
                $this->_process_fields($id, $group_field->fields(), $section_id, $data, $importer_model, $template);
                continue;
            }

            $permission_key = $section_id . '.' . $field['id'];
            $allowed = $this->_process_data->permission()->validate([$permission_key => ''], $this->_process_data->getMethod(), $section_id);
            $is_allowed = isset($allowed[$permission_key]) ? true : false;
            if (!$is_allowed) {
                continue;
            }

            $group_field->_process($this, $id, $section_id, $data, $importer_model, $template, $i);
        }
    }

    /**
     * When should the addon run?
     *
     * @param callable $callback
     * @return bool
     */
    public function enabled($callback)
    {
        $this->_run_conditions = $callback;
    }

    public function register_panel($section_name, $section_id, $callback, $settings = [])
    {
        $this->_sections[$section_id] = new AddonBasePanel($this, $callback, $section_id, [
            'name' => $section_name,
            'settings' => $settings
        ]);
    }

    public function register_panels($callback)
    {
        $this->_panels_callback = $callback;
    }

    public function store_meta($section_id, $id, $key, $value, $i = false)
    {
        if (!isset($this->_meta[$section_id])) {
            $this->_meta[$section_id] = [];
        }

        if ($i === false) {

            $this->_meta[$section_id][$key] = [
                'id' => $id,
                'key' => $key,
                'value' => $value
            ];
        } else {

            if (!isset($this->_meta[$section_id][$key])) {
                $this->_meta[$section_id][$key] = [
                    'id' => $id,
                    'key' => $key,
                    'value' => []
                ];
            }

            $this->_meta[$section_id][$key]['value'][$i] = $value;
        }
    }

    public function get_meta($section_id)
    {
        return isset($this->_meta[$section_id]) ? $this->_meta[$section_id] : [];
    }

    public function clear_meta()
    {
        $this->_meta = [];
    }

    private $_setup = false;

    public function register_custom_fields($name, $callback)
    {
        $api = new AddonCustomFieldsApi($name, $callback);
        $this->_custom_fields[] = $api;

        if ($this->_setup) {
            $api->_init($api);
        }
    }

    /**
     * @param mixed $result
     * @param \ImportWP\Pro\Importer\Template\CustomFields $custom_fields
     * 
     * @return void
     */
    public function _custom_fields_init($result, $custom_fields)
    {
        foreach ($this->_custom_fields as $api) {
            $api->_init($custom_fields);
        }

        $this->_setup = true;
    }

    /**
     * @param array $fields
     * @param ImporterModel $importer_model
     * @return array
     */
    public function _custom_fields_get_fields($fields, $importer_model)
    {
        $this->_try_init($importer_model);
        foreach ($this->_custom_fields as $api) {

            $api->_register_fields($importer_model);

            $fields = array_merge($api->_get_fields(), []);
        }

        return $fields;
    }

    public function _custom_fields_process_field($result, $post_id, $key, $value, $custom_field_record, $prefix, $importer_model, $custom_field)
    {
        foreach ($this->_custom_fields as $api) {

            $response = new AddonCustomFieldSaveResponse($importer_model, $custom_field);
            $response->_set_records($custom_field_record, $prefix);

            $api->_save($response, $post_id, $key, $value);
        }

        return $result;
    }

    public function _custom_fields_post_process($result, $post_id, $importer_model, $custom_field)
    {
        // All custom fields go through there
        return $result;
    }

    /**
     * @param ImporterModel $importer_model
     * @param Template $template
     * 
     * @return ParsedData
     */
    private function _try_init($importer_model, $template = null)
    {
        $this->_importer_model = $importer_model;
        $this->_template = $template;

        if (!is_null($this->_init_callback) && is_callable($this->_init_callback)) {
            call_user_func($this->_init_callback, $this);

            // clear callback so its not triggered twice
            $this->_init_callback = null;
        }
    }

    public function importer_model()
    {
        return $this->_importer_model;
    }

    public function template()
    {
        return $this->_template;
    }
}
