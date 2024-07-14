<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
class AddonMigration
{
    protected $_migrations = [];

    public function up($callback)
    {
        $this->_migrations[] = $callback;
        return $this;
    }

    public function data()
    {
        return $this->_migrations;
    }
}
