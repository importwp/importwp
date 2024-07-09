<?php

namespace ImportWP\Common\AddonAPI;

class Addon
{
    public function __construct()
    {
        // Register addon
        \ImportWP\Common\AddonAPI\AddonManager::instance()->register($this);
    }
}
