<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;

class Template extends AbstractTemplate
{
    /**
     * Template name
     *
     * @var string
     */
    protected $name;
    /**
     * Mapper name
     *
     * @var string
     */
    protected $mapper;
    /**
     * @var ImporterModel
     */
    protected $importer;

    /**
     * Registered data groups
     *
     * @var string[]
     */
    protected $groups;

    /**
     * List of field option callbacks
     * 
     * @var array
     */
    protected $field_options = [];

    public function get_name()
    {
        return $this->name;
    }

    public function get_mapper()
    {
        return $this->mapper;
    }

    public function register_hooks(ImporterModel $importer)
    {
        $this->importer = $importer;

        add_filter('iwp/importer/before_mapper', array($this, 'pre_process'));
        add_filter('iwp/status/record_inserted', [$this, 'display_record_info'], 10, 3);
        add_filter('iwp/status/record_updated', [$this, 'display_record_info'], 10, 3);
    }

    public function unregister_hooks()
    {
        remove_filter('iwp/importer/before_mapper', array($this, 'pre_process'));
        remove_filter('iwp/status/record_inserted', [$this, 'display_record_info'], 10, 3);
        remove_filter('iwp/status/record_updated', [$this, 'display_record_info'], 10, 3);
    }

    /**
     * @param string $message
     * @param int $id
     * @param ParsedData $data
     * @return $string
     */
    public function display_record_info($message, $id, $data)
    {
        $fields = $data->getData();
        if (!empty($fields)) {
            $message .= ' (' . implode(', ', array_keys($fields)) . ')';
        }
        return $message;
    }

    public function register_group($label, $key, $fields, $args = [])
    {
        return [
            'id' => $key,
            'heading' => $label,
            'type' => isset($args['type']) ? $args['type'] : 'group',
            'fields' => $fields
        ];
    }

    public function register_core_field($label, $key, $args = [])
    {
        $args['core'] = true;
        return $this->register_field($label, $key, $args);
    }

    public function register_field($label, $key, $args = [])
    {
        $data = [
            'id' => $key,
            'label' => $label,
            'type' => isset($args['type']) ? $args['type'] : 'field',
            'core' => isset($args['core']) ? $args['core'] : false,
            'tooltip' => isset($args['tooltip']) ? $args['tooltip'] : false
        ];

        if (isset($args['options'])) {
            $data['options'] = $args['options'];
        }

        if (isset($args['default'])) {
            $data['default'] = $args['default'];
        }

        if (isset($args['condition'])) {
            $data['condition'] = $args['condition'];
        }

        return $data;
    }

    public function get_field_options($field_name, $id)
    {
        $callback = false;
        foreach ($this->field_options as $callback_field_name => $temp_callback) {
            if (strpos($callback_field_name, '*') !== false) {

                $callback_field_name = str_replace('.', '\.', $callback_field_name);

                $pattern = str_replace('*', '[\d]+', $callback_field_name);
                if (1 !== preg_match("/^{$pattern}/i", $field_name)) {
                    continue;
                }

                $callback = $temp_callback;
                break;
            }

            if ($field_name !== $callback_field_name) {
                continue;
            }

            $callback = $temp_callback;
            break;
        }

        if (!$callback) {
            return false;
        }

        if (!method_exists($callback[0], $callback[1])) {
            return false;
        }

        if (!is_callable($callback)) {
            return false;
        }

        return call_user_func_array($callback, [$id]);
    }

    /**
     * Alter fields before they are parsed
     *
     * @param array $fields
     * @return array
     */
    public function field_map($fields)
    {
        return $fields;
    }

    /**
     * Process data before record is importer.
     *
     * Alter data that is passed to the mapper.
     *
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data)
    {
        $data = $this->pre_process_groups($data);
        return $data;
    }

    public function process($post_id, ParsedData $data)
    {
    }

    /**
     * Process data after record is importer.
     *
     * Use data that is returned from the mapper.
     *
     * @param int $post_id
     * @param ParsedData $data
     * @return void
     */
    public function post_process($post_id, ParsedData $data)
    {
    }

    public function pre_process_groups(ParsedData $data)
    {

        $map = $data->getData('default');
        foreach ($this->groups as $group) {
            $group_map = [];

            foreach ($map as $field_key => $fields_map) {
                if (preg_match('/^' . $group . '\.(.*?)$/', $field_key) === 1) {
                    $group_map[$field_key] = $fields_map;
                }
            }

            $data->replace($group_map, $group);
        }

        return $data;
    }
}
