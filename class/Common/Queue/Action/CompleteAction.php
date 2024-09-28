<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Queue\QueueTaskResult;
use ImportWP\Common\Util\DB;

class CompleteAction implements ActionInterface
{
    public $import_id;

    public function __construct($import_id)
    {
        $this->import_id = $import_id;
    }

    public function handle()
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table_name = DB::get_table_name('import')) {
            $wpdb->update($table_name, ['status' => 'Y'], ['id' => $this->import_id]);
        }

        return new QueueTaskResult(0, 'Y');
    }
}
