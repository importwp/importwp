<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
class AddonBaseGroup extends AddonBaseContainer implements AddonGroupInterface
{
    /**
     * @var callable
     */
    protected $_process_callback;

    protected $_is_field_processable = false;

    protected $_id;

    public function _enable_processing()
    {
        $this->_is_field_processable = true;
    }

    public function _is_processing_enabled()
    {
        return $this->_is_field_processable;
    }

    public function __construct($addon, $id, $data)
    {
        $this->_id = $id;
        parent::__construct($addon, array_merge($data, ['type' => 'group']));
    }

    public function _process($object_id, $data, $importer_model, $template)
    {
        if (!is_null($this->_process_callback) && is_callable($this->_process_callback)) {
            call_user_func($this->_process_callback, new AddonGroupDataApi($this->addon(), $this, $object_id, $this->get_id(), $data, $importer_model, $template));
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
}
