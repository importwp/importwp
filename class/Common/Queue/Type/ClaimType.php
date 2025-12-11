<?php

namespace ImportWP\Common\Queue\Type;

class ClaimType extends BaseType
{

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $created_at;
}
