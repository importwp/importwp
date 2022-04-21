<?php

namespace ImportWP\Common\Addon;

class AddonBasePanel extends AddonBaseContainer implements AddonPanelInterface
{
    /**
     * @var callable
     */
    private $_process_callback;

    private $_id;

    /**
     * @var callable
     */
    private $_register_callback;

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

    public function _process($object_id, $data, $importer_model, $template)
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
}
