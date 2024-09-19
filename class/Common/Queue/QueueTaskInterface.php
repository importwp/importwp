<?php

namespace ImportWP\Common\Queue;

interface QueueTaskInterface
{
    /**
     * @return QueueTaskResult 
     */
    public function process($import_id, $chunk);
}
