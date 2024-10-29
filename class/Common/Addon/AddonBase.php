<?php

namespace ImportWP\Common\Addon;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Container;

/**
 * @deprecated 2.14.0
 */
class AddonBase implements AddonInterface
{
    /**
     * @var callable
     */
    protected $_run_conditions;

    /**
     * @var AddonBasePanel[]
     */
    protected $_sections = [];

    protected $name;

    protected $id;

    protected $_meta = [];

    /**
     * @var string
     */
    protected $_process_mapper;

    protected $_register_panels_callback;

    /**
     * @var AddonCustomFieldsApi[] $_custom_fields
     */
    protected $_custom_fields = [];

    protected $_init_callback;

    /**
     * @var ImporterModel
     */
    protected $_importer_model;

    /**
     * @var Template
     */
    protected $_template;

    protected $_migrations = [];

    protected $_migrations_callback;

    protected $_setup = false;

    /**
     * @var \ImportWP\ServiceProvider
     */
    protected $_service_provider;

    public function __construct($name, $id, $callback)
    {
        $this->name = $name;
        $this->id = $id;
        $this->_init_callback = $callback;

        add_action('iwp/register_events', function ($event_handler, $service_provider) {

            $this->_service_provider = $service_provider;

            // template
            $event_handler->listen('template.fields', [$this, '_register_template_fields']);
            $event_handler->listen('template.pre_process_groups', [$this, '_data_groups']);
            $event_handler->listen('template.pre_process', [$this, '_pre_process']);
            $event_handler->listen('template.process', [$this, '_process']);

            // custom fields
            $event_handler->listen('importer.custom_fields.init', [$this, '_custom_fields_init']);
            $event_handler->listen('importer.custom_fields.get_fields', [$this, '_custom_fields_get_fields']);
            $event_handler->listen('importer.custom_fields.process_field', [$this, '_custom_fields_process_field']);
        }, 10, 2);

        add_action('iwp/importer/shutdown', function () {

            foreach ($this->_sections as $section) {
                $section->clear();
            }
        });
    }

    public function get_service_provider($prop)
    {
        if (property_exists($this->_service_provider, $prop)) {
            return $this->_service_provider->{$prop};
        }

        return false;
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

        if (!$this->_can_run($template, $importer_model)) {
            return $fields;
        }

        $this->_setup_panels($importer_model);

        // TODO: Add field modifiers
        if (!empty($this->_sections)) {
            foreach ($this->_sections as $section_id =>  $section) {

                $section->_register($importer_model);

                $section_data = $section->data();

                if (isset($section_data['settings']['maybe_run']) && is_callable($section_data['settings']['maybe_run']) && !call_user_func_array($section_data['settings']['maybe_run'], [$template, $importer_model])) {
                    continue;
                }

                $group_fields = $section->fields();
                $group_fields = $this->_register_fields($group_fields, $template, $importer_model);

                add_filter('iwp/template/permission_fields', function ($permission_fields) use ($section) {

                    $section_id = $section->get_id();
                    $section_label = $section->data('name');

                    if (!isset($permission_fields[$section_label])) {
                        $permission_fields[$section_label] = [];
                    }

                    foreach ($section->fields() as $field) {
                        $permission_fields[$section_label][$section_id . '.' . $field->get_id()] = $field->data('name');
                    }

                    return $permission_fields;
                });


                $fields = array_merge($fields, [
                    $template->register_group($section_data['name'], $section_id, (array)$group_fields, $section->settings())
                ]);
            }
        }

        return $fields;
    }

    public function _can_run($template, $importer_model)
    {
        if (!is_null($this->_run_conditions) && is_callable($this->_run_conditions)) {
            return call_user_func_array($this->_run_conditions, [$template, $importer_model]);
        }

        return true;
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

    /**
     * @param string[] $groups
     * @param ParsedData $data
     * @param Template $template
     * 
     * @return void
     */
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

        $this->_setup_panels($importer_model);

        // check to see what fields are enabled
        foreach ($this->_sections as $section_id => $section) {

            $section->_register($importer_model);
            $output = $section->_pre_process($data, $importer_model);
            $data->replace($output, $section_id);
        }

        return $data;
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
                // TODO: Addon mappers should not be listed here replace with filter?
            case 'woocommerce-product':
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
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     * @param \ImportWP\Common\Importer\Template\Template $template
     * @return void
     */
    public function _process($id, $data, $importer_model, $template)
    {
        // setup
        $this->_process_mapper = $template->get_mapper();
        $this->clear_meta();

        foreach ($this->_sections as $section) {

            $section->_process($id, $data, $importer_model, $template);
        }

        // teardown
        $this->_process_mapper = null;

        return $id;
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
        $this->_register_panels_callback = $callback;
    }

    public function _setup_panels($importer_model)
    {
        if (!is_null($this->_register_panels_callback) && is_callable($this->_register_panels_callback)) {
            call_user_func($this->_register_panels_callback, $importer_model);
        }
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

            $fields = array_merge($api->_get_fields(), $fields);
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

    /**
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     * @param \ImportWP\Common\Importer\Template\Template $template
     * 
     * @return ParsedData
     */
    protected function _try_init($importer_model, $template = null)
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

    public function register_migrations($callback)
    {
        $this->_migrations_callback = $callback;
        // Migrate has to be called after the init callback
        add_action('init', function () {
            /**
             * @var \ImportWP\Common\Importer\ImporterManager $importer_manager
             */
            $importer_manager = Container::getInstance()->get('importer_manager');
            $this->_migrate(new \ImportWP\Common\Addon\AddonMigration(), $importer_manager);
        });
    }

    /**
     * @param \ImportWP\Common\Addon\AddonMigration $migration
     * @param \ImportWP\Common\Importer\ImporterManager $importer_manager
     */
    protected function _migrate($migration, $importer_manager)
    {
        if (!is_null($this->_migrations_callback) && is_callable($this->_migrations_callback)) {
            call_user_func($this->_migrations_callback, $migration);
            $this->_migrations = $migration->data();

            if (empty($this->_migrations)) {
                return;
            }

            $option_key = 'migrate_' . $this->get_id();
            $max_migrations = count($this->_migrations);

            $importers = $importer_manager->get_importers();
            if (!empty($importers)) {
                foreach ($importers as $importer_id) {


                    $importer = $importer_manager->get_importer($importer_id);
                    $version = $importer->getSetting($option_key);
                    $version = intval($version);

                    if ($max_migrations > $version) {
                        for ($i = $version + 1; $i <= $max_migrations; $i++) {
                            call_user_func($this->_migrations[$i - 1], $importer);

                            $importer = $importer_manager->get_importer($importer_id);
                            $importer->setSetting($option_key, $i);
                            $importer->save();
                        }
                    }
                }
            }

            // clear callback so its not triggered twice
            $this->_migrations_callback = null;
        }
    }
}
