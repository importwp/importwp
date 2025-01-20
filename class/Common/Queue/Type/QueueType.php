<?php

namespace ImportWP\Common\Queue\Type;

class QueueType extends BaseType
{

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $claim_id;

    /**
     * @var int
     */
    public $import_id;

    /**
     * @var int
     */
    public $record;

    /**
     * @var int
     */
    public $pos;

    /**
     * @var int
     */
    public $len;

    /**
     * @var string
     */
    public $data;

    /**
     * @var string
     */
    public $message;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $status;

    /**
     * @var int
     */
    public $attempts;

    /**
     * @var string
     */
    public $attempted_at;

    /**
     * @var string
     */
    public $created_at;
}
