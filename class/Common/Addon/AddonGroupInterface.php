<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
interface AddonGroupInterface
{
    function get_id();
    function save($callback);
    function enabled($callback);
}
