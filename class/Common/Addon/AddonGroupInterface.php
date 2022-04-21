<?php

namespace ImportWP\Common\Addon;

interface AddonGroupInterface
{
    function get_id();
    function save($callback);
    function enabled($callback);
}
