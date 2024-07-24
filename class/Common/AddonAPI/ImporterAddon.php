<?php

namespace ImportWP\Common\AddonAPI;

use ImportWP\Common\AddonAPI\Importer\ImporterData;
use ImportWP\Common\AddonAPI\Importer\ImporterRecordData;
use ImportWP\Common\AddonAPI\Importer\Template\Template;

class ImporterAddon extends Addon
{
    /**
     * @var  \ImportWP\Common\Importer\Template\Template
     */
    private $_template;

    /**
     * @var \ImportWP\Common\Model\ImporterModel
     */
    private $_template_importer_model;

    /**
     * @var \ImportWP\Common\Importer\Importer
     */
    private $_importer;

    /**
     * @var \ImportWP\EventHandler
     */
    private $event_handler;

    /**
     * @var Template
     */
    private $_addon_template;

    /**
     * @var \ImportWP\Common\AddonAPI\Importer\ImporterRecordData
     */
    private $_record_data;

    public function __construct()
    {
        parent::__construct();

        // capture for template registration
        add_action('iwp/register_events', function ($event_handler) {

            $this->event_handler = $event_handler;

            $this->event_handler->listen('template.fields', function ($fields, $template, $importer_model) {

                /**
                 * @var array $fields
                 * @var \ImportWP\Common\Importer\Template\Template $template
                 * @var \ImportWP\Common\Model\ImporterModel $importer_model
                 */

                $this->_template = $template;
                $this->_template_importer_model = $importer_model;

                if ($this->init()) {
                    $fields = $this->merge_fields($fields);
                }

                return $fields;
            });

            // Register custom fields for display
            $this->event_handler->listen('importer.custom_fields.get_fields', function ($fields, $importer_model) {

                $this->_template_importer_model = $importer_model;
                if (!$this->init()) {
                    return $fields;
                }

                $custom_fields = $this->_addon_template->get_custom_fields();
                foreach ($custom_fields as $custom_fields) {
                    $fields = array_merge($custom_fields->get_dropdown_fields(), $fields);
                }

                return $fields;
            });

            $this->event_handler->listen('importer_manager.import', function ($importer_model) {

                /**
                 * @var \ImportWP\Common\Model\ImporterModel $importer_model
                 */
                $this->_template_importer_model = $importer_model;

                /**
                 * @var \ImportWP\Common\Importer\ImporterManager $importer_manager
                 */
                $importer_manager = \ImportWP\Container::getInstance()->get('importer_manager');

                // NOTE: currently this is a string
                $this->_template = $importer_manager->get_template($importer_model->getTemplate());

                $this->init();

                return $importer_model;
            });
        }, 10);

        // capture for import registration
    }

    private function merge_fields($field_data)
    {
        // register panels
        $panels = $this->_addon_template->get_panels();
        if (!empty($panels)) {
            foreach ($panels as $panel) {

                $field_data = array_merge($field_data, [
                    $this->_template->register_group(
                        $panel->get_name(),
                        $panel->get_id(),
                        $panel->get_template_data($this->_template),
                        $panel->get_args()
                    )
                ]);
            }
        }

        return $field_data;
    }

    final protected function init()
    {
        // should we continue
        if (!$this->can_run()) {
            return false;
        }

        $this->install_hooks();

        $this->_addon_template = new Template();
        $this->register($this->_addon_template);

        // register permission fields for ui
        add_filter('iwp/template/permission_fields', function ($permission_fields) {

            $panels = $this->_addon_template->get_panels();
            foreach ($panels as $panel) {

                $section = $panel->get_name();
                if (!isset($permission_fields[$section])) {
                    $permission_fields[$section] = [];
                }

                foreach ($panel->get_fields() as $field) {
                    $permission_fields[$section][$panel->get_id() . '.' . $field->get_id()] = $field->get_name();
                }
            }

            return $permission_fields;
        });

        $this->event_handler->listen('template.pre_process_groups', function ($groups, $data, $template) {

            $new_groups = $this->_addon_template->get_panel_ids();
            if (!empty($new_groups)) {
                return array_merge((array) $groups, $new_groups);
            }

            return $groups;
        });

        /**
         * Change custom field key to acf field key
         */
        add_filter('iwp/custom_field_key', function ($key) {

            foreach ($this->_addon_template->get_custom_fields() as $custom_fields) {

                if (strpos($key, $custom_fields->get_prefix() . "::") === 0) {

                    $field_key = substr($key, strrpos($key, '::') + strlen('::'));
                    if ($field = $custom_fields->get_field($field_key)) {
                        return $field['id'];
                    }

                    break;
                }
            }
            return $key;
        }, 10);

        return true;
    }

    /**
     * @param Template $template_data 
     * @return Template 
     */
    public function register($template_data)
    {
        return $template_data;
    }

    protected function install_hooks()
    {
        // skip row
        add_filter('iwp/importer/skip_record', function ($result, $data, $importer) {

            // this is the earliest point we have access to the importer
            $this->_importer = $importer;

            return $this->filter_row($result, $data);
        }, 10, 3);

        // before import
        add_action('iwp/importer/init', function () {
            $this->before_import();
        });

        // after import
        $this->event_handler->listen('importer_manager.import_shutdown', function ($importer_model) {
            $state = \ImportWP\Common\Importer\State\ImporterState::get_state($importer_model->getId());
            if ($state['status'] != 'complete') {
                return $importer_model;
            }

            $this->after_import();

            return $importer_model;
        });

        // before row
        add_action('iwp/importer/before_row', function ($data) {

            /**
             * @var \ImportWP\Common\Importer\ParsedData $data
             */
            $record_data = new ImporterRecordData($data, $this->_addon_template, $this->_importer);

            $this->before_row($record_data);
        });

        // after row
        add_action('iwp/importer/after_row', [$this, 'after_row']);

        // save
        $this->event_handler->listen('template.process', function ($id, $data, $importer_model, $template) {

            /**
             * @var integer $id
             * @var \ImportWP\Common\Importer\ParsedData $data
             * @var \ImportWP\Common\Model\ImporterModel $importer_model
             * @var \ImportWP\Common\Importer\Template\Template $template
             */

            $addon_data = new ImporterData($id, $data, $this->_addon_template, $this->_importer, $template);
            $this->save($addon_data);

            add_filter('iwp/custom_fields/log_message', function ($message) use ($addon_data) {

                $logs = $addon_data->get_logs();
                foreach ($logs as $group_id => $field_ids) {
                    $message .= ', ' . $group_id . ' (' . implode(', ', $field_ids) . ')';
                }

                return $message;
            });

            return $id;
        });
    }

    public function before_import()
    {
    }

    /**
     * @param bool $result 
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @param \ImportWP\Common\Importer\Importer $importer
     * @return bool
     */
    public function filter_row($result, $data)
    {
        return $result;
    }

    /**
     * @param \ImportWP\Common\AddonAPI\Importer\ImporterRecordData $record 
     * @return void 
     */
    public function before_row($record)
    {
    }

    /**
     * @param ImporterData $data 
     * @return void 
     */
    public function save($data)
    {
    }

    public function after_row()
    {
    }

    public function after_import()
    {
    }

    protected function get_importer()
    {
        if (!is_null($this->_template_importer_model)) {
            return $this->_template_importer_model;
        }

        if (!is_null(iwp()->importer)) {
            return iwp()->importer;
        }

        return false;
    }

    protected function get_importer_id()
    {
        $importer = $this->get_importer();
        if (!$importer) {
            return false;
        }

        return $importer->getId();
    }

    protected function get_template_id()
    {
        $importer = $this->get_importer();
        if (!$importer) {
            return false;
        }

        return $importer->getTemplate();
    }

    protected function get_parser_id()
    {
        $importer = $this->get_importer();
        if (!$importer) {
            return false;
        }

        return $importer->getParser();
    }

    protected function get_mapper_id()
    {
        if (is_null($this->_template)) {
            return false;
        }

        return $this->_template->get_mapper();
    }

    protected function get_parser()
    {
        if (is_null($this->_importer)) {
            return false;
        }

        return $this->_importer->getParser();
    }

    protected function get_mapper()
    {
        if (is_null($this->_importer)) {
            return false;
        }

        return $this->_importer->getMapper();
    }

    protected function can_run()
    {
        return true;
    }
}
