<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
class AddonBaseData
{
    /**
     * @var callable
     */
    protected $_run_conditions;

    protected $_data;

    /**
     * @var AddonBase
     */
    protected $_addon;

    public function __construct($addon, $data)
    {
        $this->_data = $data;
        $this->_addon = $addon;
    }

    public function addon()
    {
        return $this->_addon;
    }

    public function data($key = false)
    {
        if ($key) {
            return isset($this->_data[$key]) ? $this->_data[$key] : false;
        }

        return $this->_data;
    }

    /**
     * When should the addon run?
     *
     * @param callable $callback
     */
    public function enabled($callback)
    {
        $this->_run_conditions = $callback;
        return $this;
    }

    /**
     * Is field allowed to be output?
     *
     * @param \ImportWP\Common\Importer\Template\Template $template
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     * @return bool
     */
    public function _is_allowed($template, $importer_model)
    {
        if (!is_null($this->_run_conditions) && is_callable($this->_run_conditions) && !call_user_func_array($this->_run_conditions, [$template, $importer_model])) {
            return false;
        }

        return true;
    }
}
