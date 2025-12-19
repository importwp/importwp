<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Queue\QueueTaskResult;
use ImportWP\Common\Util\DB;
use ImportWP\Container;

class CompleteAction implements ActionInterface
{
    /**
     * @var int
     */
    public $import_id;

    /**
     * @var ImporterModel
     */
    public $importer_data;

    /**
     * @var ImporterManager
     */
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
         * @var Properties $properties
         */
        $properties = Container::getInstance()->get('properties');

        $this->importer_data->limit_importer_files($properties->file_rotation);
        $this->importer_manager->prune_importer_logs($this->importer_data, $properties->log_rotation);

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
