<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Queue\QueueTaskResult;

interface ActionInterface
{
    /**
     * @return QueueTaskResult 
     */
    public function handle();
}
