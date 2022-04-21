<?php

namespace ImportWP\Common\Addon;

interface AddonPanelInterface
{
    function get_id();
    function save($callback);
    function enabled($callback);
}
