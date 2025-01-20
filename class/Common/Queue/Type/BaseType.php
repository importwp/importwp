<?php

namespace ImportWP\Common\Queue\Type;

class BaseType
{
    public function __construct($object = null)
    {
        if (is_array($object) || is_object($object)) {
            foreach ($object as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}
