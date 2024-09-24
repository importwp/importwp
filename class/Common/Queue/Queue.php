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

            $wpdb->query("INSERT INTO `{$table}` (`importer_id`,`status`,`step`) VALUES ('{$config}', 'R', 'S')");
            $import_session_id =  $wpdb->insert_id;

            $queue_table_name = DB::get_table_name('queue');
            $wpdb->insert($queue_table_name, ['import_id' => $import_session_id, 'type' => 'S']);

            return $import_session_id;
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
        $base_query = "INSERT INTO {$table_name} (`import_id`,`pos`,`len`,`type`) VALUES ";
        $query_values = [];
        $rollback = false;

        $wpdb->query('START TRANSACTION');

        foreach ($tasks->getFileIndex() as $row) {
            $query_values[] = "('{$queue_id}','{$row['start']}','{$row['length']}', 'I')";

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

            $wpdb->insert($table_name, ['import_id' => $queue_id, 'type' => 'P']);
            $wpdb->insert($table_name, ['import_id' => $queue_id, 'type' => 'D']);

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
                $chunk = $this->claim_chunk($claim_id, $import_id, ['Q', 'E']);
                if (!$chunk) {
                    break;
                }

                $result = $task->process($import_id, $chunk);
                $import_type = $result->type; // 'I' | 'U' | 'D'
                $record_id = $result->id;

                if ($import_type != 'E') {

                    /**
                     * @var \WPDB $wpdb
                     */
                    global $wpdb;
                    $table_name = DB::get_table_name('queue');
                    $wpdb->update($table_name, ['status' => 'Y', 'data' => $import_type, 'claim_id' => 0, 'record' => $record_id], ['id' => $chunk['id']]);
                } else {
                    $this->log_error($chunk, $result->message);
                }
            } catch (\Error $e) {

                $this->log_error($chunk, $e);
            } catch (\ErrorException $e) {
                $this->log_error($chunk, $e);
            }
            $i++;
        } while ($chunk && $i < 20);

        $this->release_claim($claim_id);

        $this->cleanup($import_id);

        restore_error_handler();
    }

    /**
     * Check to see there are any more actions in the queue 
     * with the current import status, if not move to the next step
     * 
     * @param int $import_id
     * @return void 
     */
    protected function cleanup($import_id)
    {

        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $import_table_name = DB::get_table_name('import');
        $queue_table_name = DB::get_table_name('queue');


        // progress to next queue step
        $results = $wpdb->query("UPDATE {$import_table_name} AS `i`
SET `i`.`step` = CASE
	WHEN `i`.`step` = 'S' THEN 'I'
    WHEN `i`.`step` = 'I' THEN 'D'
	WHEN `i`.`step` = 'D' THEN 'R'
    WHEN `i`.`step` = 'R' THEN 'P'
END
WHERE 
	`i`.`id` = {$import_id} 
	AND NOT EXISTS (
		SELECT * FROM {$queue_table_name} as `q` WHERE `q`.`import_id` = `i`.`id` AND `q`.`type` = `i`.`step` AND (`q`.`status` = 'Q' || (`q`.`status` = 'E' AND `q`.`attempts` < 3) )
	);");

        if ($results > 0) {
            return true;
        }

        return false;
    }

    protected function claim_chunk($claim_id, $import_id, $status_list = ['Q', 'E'])
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
        $join = "INNER JOIN `{$import_table_name}` as i ON q.`import_id` = i.`id` AND i.`status`='R' AND i.`step` = q.`type`";

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

            if (is_string($error)) {
                $message = $error;
            } else {
                $message = $error->getMessage() . " - " . $error->getTraceAsString();
            }

            $wpdb->insert($table_name, [
                'queue_id' => $chunk['id'],
                'message' => $message
            ]);
        }
    }

    public function get_section($import_id, $raw = false)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table_name = DB::get_table_name('import')) {
            $status = $wpdb->get_var("SELECT `step` FROM {$table_name} WHERE id={$import_id}");

            if ($raw) {
                return $status;
            }

            switch ($status) {
                case 'D':
                case 'R':
                    return 'delete';
                case 'I':
                    return 'import';
            }
        }

        return false;
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
                case 'D':
                case 'R':
                case 'I':
                    return 'running';
                case 'C':
                    return 'cancelled';
                case 'E':
                    return 'error';
                case 'Y':
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
            'import_total' => 0,
            'delete_total' => 0,
            'import' => 0,
            'delete' => 0,
        ];

        if ($table_name = DB::get_table_name('queue')) {
            $rows = $wpdb->get_results("SELECT `data`, `type`, COUNT(*) as `count` FROM {$table_name} WHERE import_id={$import_id} AND `type` IN ('I', 'R') GROUP BY `data`, `type`", ARRAY_A);

            foreach ($rows as $row) {

                if ($row['type'] == 'I') {
                    $stats['import_total'] += $row['count'];
                } elseif ($row['type'] == 'R') {
                    $stats['delete_total'] += $row['count'];
                }

                if (!empty($row['data'])) {

                    if (in_array($row['data'], ['I', 'U'])) {
                        $stats['import'] += $row['count'];
                    } else {
                        $stats['delete'] += $row['count'];
                    }

                    $stats[$row['data']] = $row['count'];
                }
            }
        }

        return $stats;
    }

    public function get_status_message($import_id, $output = [])
    {
        $output['id'] = $import_id;
        $output['version'] = 2;
        $output['section'] = $this->get_section($import_id);
        $section = $output['section'] == 'import' ? 'import' : 'delete';
        $output['status'] = $this->get_status($import_id);

        $stats = $this->get_stats($import_id);

        if ($section == 'import') {
            $current = $stats['import'];
            $output['message'] = "Importing ";
            $total = $stats['import_total'];
        } else {
            $current = $stats['delete'];
            $output['message'] = "Deleting ";
            $total = $stats['delete_total'];
        }

        $output['message'] .= "{$current}/{$total}.";

        if ($output['status'] == 'complete') {
            $output['message'] = 'Import complete.';
        }

        $output['progress']['import']['start'] = 1;
        $output['progress']['import']['end'] = $stats['import_total'];
        $output['progress']['import']['current_row'] = $stats['import'];

        $output['progress']['delete']['start'] = 1;
        $output['progress']['delete']['end'] = $stats['delete_total'];
        $output['progress']['delete']['current_row'] = $stats['delete'];

        return $output;
    }
}
