<?php

namespace ImportWP\Common\Addon;

/**
 * @deprecated 2.14.0
 */
interface AddonFieldInterface
{
    function save($callback);
    function enabled($callback);
    function options($options);
    function default($value);
    function tooltip($message);
}
