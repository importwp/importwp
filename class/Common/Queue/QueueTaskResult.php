<?php

namespace ImportWP\Common\Queue;

class QueueTaskResult
{

    public $id;
    public $type;
    public $message = '';

    public function __construct($id, $type, $message = '')
    {
        $this->id = $id;
        $this->type = $type;
        $this->message = $message;
    }
}
