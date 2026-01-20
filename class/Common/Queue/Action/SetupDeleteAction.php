<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Queue\QueueTaskResult;
use ImportWP\Common\Queue\Type\QueueType;
use ImportWP\Common\Util\DB;

class SetupDeleteAction implements ActionInterface
{
    public $chunk;
    public $mapper;

    /**
     * @param QueueType $chunk 
     * @param mixed $mapper 
     * @return void 
     */
    public function __construct($chunk, $mapper)
    {
        $this->chunk = $chunk;
        $this->mapper = $mapper;
    }

    public function handle()
    {
        if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

            // generate list of items to be deleted
            $object_ids = $this->mapper->get_objects_for_removal();
            $object_ids = array_values(array_unique($object_ids));
            if (!empty($object_ids)) {

                /**
                 * @var \WPDB $wpdb
                 */
                global $wpdb;

                $table_name = DB::get_table_name('queue');
                $base_query = "INSERT INTO {$table_name} (`import_id`,`record`, `pos`,`type`) VALUES ";
                $query_placeholders = [];
                $query_values = [];
                $rollback = false;

                $wpdb->query('START TRANSACTION');

                foreach ($object_ids as $i => $row) {
                    $query_placeholders[] = "(%d,%d,%d,%s)";
                    $query_values[] = $this->chunk->import_id;
                    $query_values[] = $row;
                    $query_values[] = $i;
                    $query_values[] = 'R';

                    if (count($query_values) > 1000) {

                        if (!$wpdb->query(
                            $wpdb->prepare(
                                $base_query . implode(',', $query_placeholders),
                                $query_values
                            )
                        )) {
                            $rollback = true;
                            break;
                        }

                        $query_placeholders = [];
                        $query_values = [];
                    }
                }

                if (!$rollback && !empty($query_values)) {
                    if (!$wpdb->query(
                        $wpdb->prepare(
                            $base_query . implode(',', $query_placeholders),
                            $query_values
                        )
                    )) {
                        $rollback = true;
                    }
                }

                if ($rollback) {
                    $wpdb->query('ROLLBACK');
                } else {
                    $wpdb->query('COMMIT');
                }
            }
        }

        // TODO: process which items need to be queued for deleting records.
        return new QueueTaskResult(0, 'Y');
    }
}
