<?php

namespace ImportWP\Common\Addon;

interface AddonFieldInterface
{
    function save($callback);
    function enabled($callback);
    function options($options);
    function default($value);
    function tooltip($message);
}
