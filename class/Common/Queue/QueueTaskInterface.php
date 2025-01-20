<?php

namespace ImportWP\Common\Queue;

use ImportWP\Common\Queue\Action\ActionInterface;
use ImportWP\Common\Queue\Type\QueueType;

interface QueueTaskInterface
{
    /**
     * @param int $import_id
     * @param QueueType $chunk
     * @return ActionInterface 
     */
    public function process($import_id, $chunk);
}
