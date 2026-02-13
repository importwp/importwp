<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Queue\QueueTaskResult;
use ImportWP\Common\Queue\Type\QueueType;
use ImportWP\Common\Util\Logger;

class DeleteAction implements ActionInterface
{
    public $import_id;
    public $chunk;
    public $mapper;

    /**
     * 
     * @param mixed $import_id 
     * @param QueueType $chunk 
     * @param mixed $mapper 
     * @return void 
     */
    public function __construct($import_id, $chunk, $mapper)
    {
        $this->chunk = $chunk;
        $this->import_id = $import_id;
        $this->mapper = $mapper;
    }

    public function handle()
    {
        $i = $this->chunk->pos;
        $object_id = $this->chunk->record;

        if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

            try {

                if (apply_filters('iwp/importer/enable_custom_delete_action', false, $this->import_id)) {

                    Logger::write('custom_delete_action:' . $i . ' -object=' . $object_id);
                    do_action('iwp/importer/custom_delete_action', $this->import_id, $object_id);
                } else {

                    Logger::write('delete:' . $i . ' -object=' . $object_id);
                    $this->mapper->delete($object_id);
                }

                $message = apply_filters('iwp/status/record_deleted', 'Record Deleted: #' . $object_id, $object_id);
            } catch (MapperException $e) {

                Logger::error('delete:' . $i . ' -mapper-error=' . $e->getMessage());
                $message = 'Record Error: #' . $i . ' ' . $e->getMessage();
            }
        }

        return new QueueTaskResult($object_id, 'R', $message);
    }
}
