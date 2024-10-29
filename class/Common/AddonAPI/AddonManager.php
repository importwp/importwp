<?php

namespace ImportWP\Common\AddonAPI;

class AddonManager
{
    /**
     * @var Addon[]
     */
    private $_addons = [];

    /**
     * @var AddonManager
     */
    private static $instance;

    /**
     * @return AddonManager Instance
     */
    public static function instance()
    {
        if (!isset(self::$instance) && !(self::$instance instanceof AddonManager)) {
            self::$instance = new AddonManager();
        }

        return self::$instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * class via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
    }

    /**
     * As this class is a singleton it should not be clone-able
     */
    protected function __clone()
    {
    }

    /**
     * As this class is a singleton it should not be able to be unserialized
     */
    public function __wakeup()
    {
    }

    /**
     * Register Addon
     * 
     * @param Addon $addon 
     * @return void 
     */
    public function register($addon)
    {
        $this->_addons[] = $addon;
    }
}
