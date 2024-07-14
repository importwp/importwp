<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
class AddonBasePanel extends AddonBaseContainer implements AddonPanelInterface
{
    /**
     * @var callable
     */
    protected $_process_callback;

    /**
     * @var string
     */
    protected $_id;

    /**
     * @var callable
     */
    protected $_register_callback;

    /**
     * Stores ParsedData only during panel process
     * 
     * @var \ImportWP\Common\Importer\ParsedData
     */
    protected $_process_data;

    /**
     * If a panel field has permission, then the group also should have permission
     * 
     * @var bool
     */
    protected $_panel_has_permission = false;

    public function __construct($addon, $callback, $id, $data)
    {
        $this->_id = $id;
        $this->_register_callback = $callback;
        parent::__construct($addon, $data);
    }

    public function _register($importer_model)
    {
        call_user_func_array($this->_register_callback, [$this, $importer_model]);
    }

    public function _trigger_process_callback($object_id, $data, $importer_model, $template)
    {
        if (!is_null($this->_process_callback) && is_callable($this->_process_callback)) {
            call_user_func($this->_process_callback, new AddonPanelDataApi($this->addon(), $this, $object_id, $this->get_id(), $data, $importer_model, $template));
        }
    }

    public function save($callback)
    {
        $this->_process_callback = $callback;
        return $this;
    }

    public function get_id()
    {
        return $this->_id;
    }

    /**
     * @return AddonBaseField[]
     */
    public function fields()
    {
        return isset($this->_data['fields']) ? $this->_data['fields'] : [];
    }

    public function settings()
    {
        return isset($this->_data['settings']) ? $this->_data['settings'] : [];
    }

    public function is_repeatable()
    {
        return isset($this->_data['settings'], $this->_data['settings']['type']) && $this->_data['settings']['type'] == 'repeatable';
    }

    /**
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     *
     * @return array
     */
    public function _pre_process($data, $importer_model)
    {
        $section_id = $this->get_id();
        $group_fields = $this->fields();
        $section_data = $data->getData($section_id);

        if ($this->is_repeatable()) {
            $output = $this->_pre_process_repeatable_fields($group_fields, $data, $section_id, $section_data);
        } else {
            $output = $this->_pre_process_fields($group_fields, $data, $section_id, $section_data, $importer_model);
        }

        return $output;
    }

    /**
     * @param AddonBaseField[] $group_fields
     * @param ParsedData $data
     * @param string $section_id
     * @param array $data
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     * @param array $output
     * 
     * @return array
     */
    public function _pre_process_fields($group_fields, $data, $section_id, $section_data, $importer_model, $output = [], $enable_id = false)
    {
        if (empty($group_fields)) {
            return $output;
        }

        foreach ($group_fields as $group_field) {

            /**
             * @var AddonBaseField $group_field
             */

            $field = $group_field->data();

            // section.group_id or section.field_id
            $field_enable_id = false === $enable_id ? "{$section_id}.{$field['id']}" : $enable_id;

            if ($importer_model->isEnabledField($field_enable_id) || (isset($field['settings'], $field['settings']['core']) && $field['settings']['core'] === true)) {

                // Mark field for processing
                $group_field->_enable_processing();

                if ($field['type'] === 'group') {

                    /**
                     * @var AddonBaseGroup $group_field
                     */
                    $output = $this->_pre_process_fields($group_field->fields(), $data, "{$section_id}.{$field['id']}",  $section_data, $importer_model, $output, $field_enable_id);
                } else {

                    foreach ($section_data as $field_id => $field_value) {
                        if (preg_match('/^' . str_replace('.', '\.', $section_id) . '\.(' . $field['id'] . '(\.\S+)?)$/', $field_id, $matches) !== 1) {
                            continue;
                        }
                        $output[$matches[1]] = $field_value;
                    }
                }
            }
        }

        return $output;
    }

    /**
     * @param AddonBaseField[] $group_fields
     * @param ParsedData $data
     * @param string $section_id
     * @param array $data
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     * @param array $output
     * 
     * @return array
     */
    public function _pre_process_repeatable_fields($group_fields, $data, $section_id, $section_data)
    {
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

    /**
     * @param integer $id
     * @param \ImportWP\Common\Importer\ParsedData $data
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     * @param \ImportWP\Common\Importer\Template\Template $template
     */
    public function _process($id, $data, $importer_model, $template)
    {
        $section_id = $this->get_id();
        $group_fields = $this->fields();
        $repeatable = $this->is_repeatable();

        $this->_process_data = $data;
        $this->_panel_has_permission = false;

        $rows = $data->getData($section_id);
        if ($repeatable) {
            foreach ($rows as $i => $row) {
                $this->_process_fields($id, $group_fields, $section_id, $row, $importer_model, $template, $i);
            }
        } else {
            $this->_process_fields($id, $group_fields, $section_id, $rows, $importer_model, $template, false);
        }

        $is_allowed = $this->_panel_has_permission;

        // groups that do not process fields without the callback, can be allowed with this key
        if (!$is_allowed) {

            // check permissions before calling save
            $permission_key = $section_id;
            $allowed = $this->_process_data->permission()->validate([$permission_key => ''], $this->_process_data->getMethod(), $section_id);
            $is_allowed = isset($allowed[$permission_key]) ? true : false;
        }


        if ($is_allowed) {
            $this->_trigger_process_callback($id, $rows, $importer_model, $template);
        }

        $this->_process_data = null;
    }

    /**
     * @param integer $id
     * @param AddonBaseField[] $group_fields
     * @param string $section_id
     * @param mixed $data
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     * @param \ImportWP\Common\Importer\Template\Template $template
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

            $this->_panel_has_permission = true;

            $group_field->_process($this->addon(), $id, $section_id, $data, $importer_model, $template, $i);
        }
    }

    public function clear()
    {
        // Removed due to clearing setitngs and other important data.
        // unset($this->_data);
    }
}
