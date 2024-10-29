<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
class AddonCustomFieldsApi
{
    protected $_name;

    protected $_prefix;

    protected $_fields = [];

    protected $_init_callback;

    protected $_reg_fields_callback;

    protected $_save_callback;

    protected $_custom_fields;

    protected $_template;

    protected $_importer_model;

    public function __construct($name, $init_callback)
    {
        $this->_name = $name;
        $this->_init_callback = $init_callback;

        add_filter('iwp/custom_field_key', [$this, '_get_custom_field_key'], 10);
    }

    public function get_name()
    {
        return $this->_name;
    }

    public function set_prefix($prefix)
    {
        $this->_prefix = $prefix;
    }

    public function add_prefix($id)
    {
        return $this->_prefix !== false ? $this->_prefix . '::' . $id : $id;
    }

    /**
     * @param \ImportWP\Pro\Importer\Template\CustomFields $custom_fields
     * 
     * @return void
     */
    public function _init($custom_fields)
    {
        $this->_custom_fields = $custom_fields;
        $this->_template = $custom_fields->template();

        call_user_func($this->_init_callback, $this);
    }

    public function add_field($name, $id)
    {
        $value = $this->add_prefix($id);
        $this->_fields[] = ['value' => $value, 'label' => $this->get_name() . ' - ' . $name];
    }

    public function _get_fields()
    {
        return $this->_fields;
    }

    public function template()
    {
        return $this->_template;
    }

    public function _set_importer_model($importer_model)
    {
        $this->_importer_model = $importer_model;
    }

    public function register_fields($callback)
    {
        $this->_reg_fields_callback = $callback;
    }

    public function _register_fields($importer_model)
    {
        call_user_func($this->_reg_fields_callback, $importer_model);
    }

    public function save($callback)
    {
        $this->_save_callback = $callback;
    }

    public function _save($api, $post_id, $key, $value)
    {
        if (!$this->_key_contains_prefix($key)) {
            return false;
        }

        $field_key = $this->_get_custom_field_key($key);

        call_user_func_array($this->_save_callback, [$api, $post_id, $field_key, $value]);
    }

    public function _key_contains_prefix($key)
    {
        if ($this->_prefix && strpos($key, "{$this->_prefix}::") !== 0) {
            return false;
        }
        return true;
    }

    /**
     * @param string $key
     * @param TemplateInterface $template
     * @return string
     */
    public function _get_custom_field_key($key)
    {
        if (!$this->_key_contains_prefix($key)) {
            return $key;
        }

        if (!strpos($key, '::')) {
            return $key;
        }

        $field_key = substr($key, strrpos($key, '::') + strlen('::'));
        $matches = [];
        if (preg_match('/^([^-]+)-/', $field_key, $matches) === false) {
            return $key;
        }

        return $field_key;
    }
}
