<?php

namespace ImportWP\Common\Queue;

interface QueueTasksInterface
{
    /**
     * @return array 
     */
    public function getFileIndex();
}
