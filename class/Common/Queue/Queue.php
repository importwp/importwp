<?php

namespace ImportWP\Common\Queue;

use ImportWP\Common\Util\DB;

class Queue
{

    public static function is_enabled($importer_id)
    {
        $config_data = get_site_option('iwp_importer_config_' . $importer_id, []);
        return isset($config_data['features'], $config_data['features']['queue']) && $config_data['features']['queue'] === 1;
    }

    /**
     * Create new Import queue
     * @param string $config Importer config
     * @return int 
     */
    public function create($config)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table = DB::get_table_name('import')) {
            $wpdb->query("INSERT INTO `{$table}` (`file`,`status`) VALUES ('{$config}', 'I')");
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Populate import queue with tasks
     * @param int $queue_id
     * @param QueueTasksInterface $tasks
     * @return bool 
     */
    public function generate($queue_id, $tasks)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $table_name = DB::get_table_name('queue');
        $base_query = "INSERT INTO {$table_name} (`import_id`,`pos`,`len`) VALUES ";
        $query_values = [];
        $rollback = false;

        $wpdb->query('START TRANSACTION');

        foreach ($tasks->getFileIndex() as $row) {
            $query_values[] = "('{$queue_id}','{$row['start']}','{$row['length']}')";

            if (count($query_values) > 1000) {

                if (!$wpdb->query($base_query . implode(',', $query_values))) {
                    $rollback = true;
                    break;
                }

                $query_values = [];
            }
        }

        if (!$rollback && !empty($query_values)) {
            if (!$wpdb->query($base_query . implode(',', $query_values))) {
                $rollback = true;
            }
        }

        if ($rollback) {
            $wpdb->query('ROLLBACK');
            return false;
        } else {
            $wpdb->query('COMMIT');

            $wpdb->insert($table_name, ['import_id' => $queue_id, 'status' => 'P']);

            // change status to ready
            if ($table = DB::get_table_name('import')) {
                $wpdb->update($table, ['status' => 'R'], ['id' => $queue_id]);
                return $wpdb->insert_id;
            }

            return true;
        }
    }

    /**
     * Process items on teh queue
     * @param int $import_id
     * @param QueueTaskInterface $task
     * @return void 
     */
    public function process($import_id, $task)
    {
        $claim_id = $this->make_claim();

        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
            }
        );

        $i = 0;

        do {

            $chunk = null;

            try {
                $chunk = $this->claim_chunk($claim_id, $import_id);
                if (!$chunk) {
                    break;
                }

                $result = $task->process($import_id, $chunk);
                $import_type = $result->type; // 'I' | 'U' | 'D'
                $record_id = $result->id;

                /**
                 * @var \WPDB $wpdb
                 */
                global $wpdb;
                $table_name = DB::get_table_name('queue');
                $wpdb->update($table_name, ['status' => $import_type, 'claim_id' => 0, 'record' => $record_id], ['id' => $chunk['id']]);
            } catch (\Error $e) {

                $this->log_error($chunk, $e);
            } catch (\ErrorException $e) {
                $this->log_error($chunk, $e);
            }
            $i++;
        } while ($chunk && $i < 20);

        $this->release_claim($claim_id);

        restore_error_handler();
    }

    protected function claim_chunk($claim_id, $import_id, $status_list = ['Q', 'E', 'P'])
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $table_name = DB::get_table_name('queue');
        $import_table_name = DB::get_table_name('import');

        $status_where = "";
        if (!empty($status_list)) {

            $status_queries = [];

            foreach ($status_list as $status) {

                switch ($status) {
                        // case 'P':
                        //     $status_queries[] = "(q.`status` = 'P' AND 1 = ( SELECT COUNT(*) FROM {$table_name} WHERE import_id={$import_id} AND status NOT IN ('I','U') ) )";
                    case 'E':
                        $status_queries[] = "(q.`status` = 'E' AND q.`attempts` < 3)";
                        break;
                    default:
                        $status_queries[] = "(q.`status`='{$status}')";
                        break;
                }
            }

            if (!empty($status_queries)) {
                $status_where = " AND (" . implode(' OR ', $status_queries) . ")";
            }
        }



        // make sure importer has not been paused / cancelled
        $join = "INNER JOIN `{$import_table_name}` as i ON q.import_id = i.id AND i.status='R'";


        error_log("UPDATE `{$table_name}` as q {$join} SET q.`claim_id`={$claim_id}, q.`attempted_at`= NOW() WHERE q.`claim_id`=0 AND q.`import_id`={$import_id} {$status_where} LIMIT 1");

        $updated = $wpdb->query("UPDATE `{$table_name}` as q {$join} SET q.`claim_id`={$claim_id}, q.`attempted_at`= NOW() WHERE q.`claim_id`=0 AND q.`import_id`={$import_id} {$status_where} LIMIT 1");

        if (intval($updated) <= 0) {
            return false;
        }

        $queue_item = $wpdb->get_row("SELECT q.* FROM {$table_name} as q {$join} WHERE q.`claim_id`={$claim_id} AND q.`import_id`={$import_id} {$status_where} LIMIT 1", ARRAY_A);
        return $queue_item;
    }

    protected function make_claim()
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table = DB::get_table_name('claim')) {
            $wpdb->query("INSERT INTO {$table} VALUES ()");
            return $wpdb->insert_id;
        }

        return false;
    }

    protected function release_claim($claim_id)
    {
        if ($table = DB::get_table_name('claim')) {
            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;
            $wpdb->delete($table, ['id' => $claim_id]);
        }
    }


    protected function log_error($chunk, $error)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $attempts = intval($chunk['attempts']) + 1;
        if ($table_name = DB::get_table_name('queue')) {
            $wpdb->query("UPDATE {$table_name} SET `claim_id`=0, `attempted_at`= NOW(), `status`='E', `attempts`={$attempts}  WHERE `id`= {$chunk['id']} ");
        }

        if ($table_name = DB::get_table_name('queue_error')) {
            $wpdb->insert($table_name, [
                'queue_id' => $chunk['id'],
                'message' => $error->getMessage() . " - " . $error->getTraceAsString()
            ]);
        }
    }

    public function get_status($import_id, $raw = false)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table_name = DB::get_table_name('import')) {
            $status = $wpdb->get_var("SELECT `status` FROM {$table_name} WHERE id={$import_id}");

            if ($raw) {
                return $status;
            }

            switch ($status) {
                case 'R':
                    return 'running';
                case 'I':
                    return 'init';
                case 'C':
                    return 'cancelled';
                case 'E':
                    return 'error';
                case 'P':
                    return 'complete';
            }
        }

        return false;
    }

    public function get_status_string($status)
    {
        switch ($status) {
            case 'R':
                return 'running';
            case 'I':
                return 'init';
            case 'C':
                return 'cancelled';
            case 'E':
                return 'error';
            case 'P':
                return 'complete';
        }

        return false;
    }

    public function get_stats($import_id)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $stats = [
            'total' => 0,
        ];

        if ($table_name = DB::get_table_name('queue')) {
            $rows = $wpdb->get_results("SELECT `status`, COUNT(*) as `count` FROM {$table_name} WHERE import_id={$import_id} AND `status` IN ('Q','I','E','U') GROUP BY `status`", ARRAY_A);

            foreach ($rows as $row) {
                $stats['total'] += $row['count'];
                $stats[$row['status']] = $row['count'];
            }
        }

        return $stats;
    }
}
