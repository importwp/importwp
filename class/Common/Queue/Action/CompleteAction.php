<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Queue\QueueTaskResult;
use ImportWP\Common\Util\DB;
use ImportWP\Container;

class CompleteAction implements ActionInterface
{
    public $import_id;
    public $importer_data;
    public $importer_manager;

    public function __construct($import_id, $importer_data, $importer_manager)
    {
        $this->import_id = $import_id;
        $this->importer_data = $importer_data;
        $this->importer_manager = $importer_manager;
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

        /**
         * @var Properties $properties
         */
        $properties = Container::getInstance()->get('properties');

        // Rotate files to not fill up server
        $this->importer_data->limit_importer_files($properties->file_rotation);

        // Prune import logs
        // TODO: Modify to also cleanup database tables
        $this->importer_manager->prune_importer_logs($this->importer_data, $properties->log_rotation);

        return new QueueTaskResult(0, 'Y');
    }
}
