<?php

namespace ImportWP\Common\Queue;

use ImportWP\Common\Queue\Action\ActionInterface;

interface QueueTaskInterface
{
    /**
     * @return ActionInterface 
     */
    public function process($import_id, $chunk);
}
