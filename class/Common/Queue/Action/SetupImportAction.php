<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Queue\QueueTaskResult;

class SetupImportAction implements ActionInterface
{
    protected $import_session_id;

    public function __construct(
        $import_session_id
    ) {
        $this->import_session_id = $import_session_id;
    }

    public function handle()
    {
        // Main code is done in setup
        return new QueueTaskResult(null, 'Y');
    }
}
