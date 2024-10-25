<?php

namespace ImportWP\Common\Queue;

use ImportWP\Common\Util\DB;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;

class Queue
{
    /**
     * @var int
     */
    protected $memory_limit;

    public static function is_enabled($importer_id)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $table_name = DB::get_table_name('import');
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_name}` WHERE importer_id=%d",
                [$importer_id]
            )
        );

        return $found > 0;
    }

    /**
     * Create new Import queue
     * @param int $importer_id Importer id
     * @return int 
     */
    public static function create($importer_id)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table = DB::get_table_name('import')) {

            // cancel previous imports
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET `status`='C' WHERE `status`='R' AND `importer_id`=%d",
                    [$importer_id]
                )
            );

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO `{$table}` (`importer_id`,`status`,`step`) VALUES (%d, 'R', 'S')",
                    [$importer_id]
                )
            );
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
        $query_placeholders = [];
        $query_values = [];
        $rollback = false;

        $wpdb->query('START TRANSACTION');

        foreach ($tasks->getFileIndex() as $row) {
            $query_placeholders[] = "(%d,%d,%d,%s)";
            $query_values[] = $queue_id;
            $query_values[] = $row['start'];
            $query_values[] = $row['length'];
            $query_values[] = 'I';

            if (count($query_placeholders) > 1000) {

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

        if (!$rollback && !empty($query_placeholders)) {
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
        if (!$import_id) {
            exit;
        }

        /**
         * @var Properties $properties
         */
        $properties = Container::getInstance()->get('properties');
        $time_limit = $properties->get_setting('timeout');
        Logger::info('time_limit ' . $time_limit . 's');

        $start = microtime(true);
        $max_record_time = 0;
        $memory_max_usage = 0;

        $claim_id = $this->make_claim();

        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
            }
        );

        $i = 0;

        do {
            $record_time = microtime(true);
            $chunk = null;

            try {

                do {
                    $cleanup = false;
                    $chunk = $this->claim_chunk($claim_id, $import_id, ['Q', 'E']);
                    if (!$chunk) {
                        $cleanup = $this->cleanup($import_id);
                    }
                } while (!$chunk && $cleanup);

                if (!$chunk) {
                    break;
                }

                $action = $task->process($import_id, $chunk);
                $result = $action->handle();

                /**
                 * Importer action result type
                 * 
                 * I = Insert
                 * U = Update
                 * D = Delete
                 * N = Importer Error
                 * E = Retry Fatal Error
                 */
                $import_type = $result->type;
                $record_id = $result->id;

                if ($import_type != 'E') {

                    /**
                     * @var \WPDB $wpdb
                     */
                    global $wpdb;
                    $table_name = DB::get_table_name('queue');
                    $wpdb->update($table_name, ['status' => 'Y', 'data' => $import_type, 'message' => $result->message, 'claim_id' => 0, 'record' => $record_id], ['id' => $chunk['id']]);
                } else {
                    $this->log_error($chunk, $result->message);
                }
            } catch (\Error $e) {

                $this->log_error($chunk, $e);
            } catch (\ErrorException $e) {
                $this->log_error($chunk, $e);
            }
            $i++;
            $max_record_time = max($max_record_time, microtime(true) - $record_time);
        } while (
            $chunk && (
                $time_limit === 0 || $this->has_enough_time($start, $time_limit, $max_record_time)
            )
            && $this->has_enough_memory($memory_max_usage)
        ); // && $i < 20);

        $this->release_claim($claim_id);

        $this->cleanup($import_id);

        restore_error_handler();
    }

    function has_enough_time($start, $time_limit, $max_record_time)
    {
        return (microtime(true) - $start) < $time_limit - $max_record_time;
    }

    function has_enough_memory($memory_max_usage)
    {
        $limit = $this->get_memory_limit();

        // Has unlimited memory
        if ($limit == '-1') {
            return true;
        }

        $limit *= 0.9;
        $current_usage = $this->get_memory_usage();

        if ($current_usage + $memory_max_usage < $limit) {
            return true;
        }

        Logger::error(sprintf("Not Enough Memory left to use %s,  %s/%s", Logger::formatBytes($memory_max_usage, 2), Logger::formatBytes($current_usage, 2), Logger::formatBytes($limit, 2)));

        return false;
    }

    function get_memory_usage()
    {
        return memory_get_usage(true);
    }

    function get_memory_limit($force = false)
    {
        if ($force || is_null($this->memory_limit)) {

            $memory_limit = ini_get('memory_limit');
            if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
                if ($matches[2] == 'G') {
                    $memory_limit = $matches[1] * 1024 * 1024 * 1024; // nnnM -> nnn MB
                } elseif ($matches[2] == 'M') {
                    $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
                } else if ($matches[2] == 'K') {
                    $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
                }
            }

            $this->memory_limit = $memory_limit;

            Logger::info('memory_limit ' . $this->memory_limit . ' bytes');
        }

        return $this->memory_limit;
    }

    /**
     * Check to see there are any more actions in the queue 
     * with the current import status, if not move to the next step
     * 
     * @param int $import_id
     * @return bool 
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
        $results = $wpdb->query($wpdb->prepare(
            "UPDATE {$import_table_name} AS `i`
SET `i`.`step` = CASE
	WHEN `i`.`step` = 'S' THEN 'I'
    WHEN `i`.`step` = 'I' THEN 'D'
	WHEN `i`.`step` = 'D' THEN 'R'
    WHEN `i`.`step` = 'R' THEN 'P'
END
WHERE 
	`i`.`id` = %d 
	AND NOT EXISTS (
		SELECT * FROM {$queue_table_name} as `q` WHERE `q`.`import_id` = `i`.`id` AND `q`.`type` = `i`.`step` AND (`q`.`status` = 'Q' || (`q`.`status` = 'E' AND `q`.`attempts` < 3) )
	);",
            [$import_id]
        ));

        if ($results > 0) {
            return true;
        }

        // Set importer (E) Error status if an error happens on (S) Importer Setup or (D) Delete Setup
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$import_table_name}` AS `i`
INNER JOIN `{$queue_table_name}` AS `q` ON `i`.id = `q`.import_id
SET `i`.`status` = 'E' 
WHERE `i`.id = %d AND `i`.`status` = 'R' AND (`q`.`type` IN ('S','D') AND `q`.`status` = 'E' AND `q`.`attempts` >= 3)",
                [$import_id]
            )
        );

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
        $where_values = [];
        if (!empty($status_list)) {

            $status_queries = [];

            foreach ($status_list as $status) {

                switch ($status) {
                    case 'E':
                        $status_queries[] = "(q.`status` = 'E' AND q.`attempts` < 3)";
                        break;
                    default:
                        $status_queries[] = "(q.`status`=%s)";
                        $where_values[] = $status;
                        break;
                }
            }

            if (!empty($status_queries)) {
                $status_where = " AND (" . implode(' OR ', $status_queries) . ")";
            }
        }

        // make sure importer has not been paused / cancelled
        $join = "INNER JOIN `{$import_table_name}` as i ON q.`import_id` = i.`id` AND i.`status`='R' AND i.`step` = q.`type`";

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$table_name}` as q {$join} SET q.`claim_id`=%d, q.`attempted_at`= NOW() WHERE q.`claim_id`=0 AND q.`import_id`=%d {$status_where} LIMIT 1",
                array_merge([$claim_id, $import_id], $where_values)
            )
        );

        if (intval($updated) <= 0) {
            return false;
        }

        $queue_item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT q.* FROM {$table_name} as q {$join} WHERE q.`claim_id`=%d AND q.`import_id`=%d {$status_where} LIMIT 1",
                array_merge([$claim_id, $import_id], $where_values)
            ),
            ARRAY_A
        );
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
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table_name} SET `claim_id`=0, `attempted_at`= NOW(), `status`='E', `attempts`=%d  WHERE `id`= %d ",
                    [$attempts, $chunk['id']]
                )
            );
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

    public static function get_section($import_id, $raw = false)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table_name = DB::get_table_name('import')) {
            $status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT `step` FROM {$table_name} WHERE id=%d",
                    [$import_id]
                )
            );

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

    public static function get_status($import_id, $raw = false)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table_name = DB::get_table_name('import')) {
            $status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT `status` FROM {$table_name} WHERE id=%d",
                    [$import_id]
                )
            );

            if ($raw) {
                return $status;
            }

            // processing
            switch ($status) {
                case 'S':
                    return 'processing';
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

    public static function get_stats($import_id)
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
            'error' => 0,
            'skips' => 0
        ];

        if ($table_name = DB::get_table_name('queue')) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT `data`, `type`, COUNT(*) as `count` FROM {$table_name} WHERE import_id=%d AND `type` IN ('I', 'R') GROUP BY `data`, `type`",
                    [$import_id]
                ),
                ARRAY_A
            );

            foreach ($rows as $row) {

                if ($row['type'] == 'I') {
                    $stats['import_total'] += $row['count'];
                } elseif ($row['type'] == 'R') {
                    $stats['delete_total'] += $row['count'];
                }

                if (!empty($row['data'])) {

                    if (in_array($row['data'], ['I', 'U'])) {
                        $stats['import'] += $row['count'];
                    } elseif (in_array($row['data'], ['N', 'E'])) {
                        $stats['error'] += $row['count'];
                    } elseif ($row['data'] == 'S') {
                        $stats['skips'] += $row['count'];
                    } else {
                        $stats['delete'] += $row['count'];
                    }

                    $stats[$row['data']] = $row['count'];
                }
            }
        }

        return $stats;
    }

    public static function get_status_message($import_id, $output = [])
    {
        if (!$output) {
            $output = [];
        }

        $output['id'] = $import_id;
        $output['version'] = 2;
        $output['section'] = self::get_section($import_id);
        $section = $output['section'] == 'import' ? 'import' : 'delete';
        $output['status'] = self::get_status($import_id);

        $stats = self::get_stats($import_id);

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

        $output['stats'] = [
            'inserts' => $stats['I'] ?? 0,
            'updates' => $stats['U'] ?? 0,
            'deletes' => $stats['R'] ?? 0,
            'skips' => $stats['S'] ?? 0,
            'errors' => $stats['N'] ?? 0,
        ];

        if ($output['status'] == 'complete') {
            $output['message'] = 'Import complete.';
        } else {

            $message_stats = [];
            if (!empty($output['stats']['inserts'])) {
                $message_stats[] = $output['stats']['inserts'] . ' Inserts';
            }
            if (!empty($output['stats']['updates'])) {
                $message_stats[] = $output['stats']['updates'] . ' Updates';
            }
            if (!empty($output['stats']['deletes'])) {
                $message_stats[] = $output['stats']['deletes'] . ' Deletes';
            }
            if (!empty($output['stats']['skips'])) {
                $message_stats[] = $output['stats']['skips'] . ' Skips';
            }
            if (!empty($output['stats']['errors'])) {
                $message_stats[] = $output['stats']['errors'] . ' Errors';
            }

            if (!empty($message_stats)) {
                $output['message'] .= ' (' . implode(', ', $message_stats) . ')';

                $total = array_sum($output['stats']);
                if ($total > 0) {
                    /**
                     * @var \WPDB $wpdb
                     */
                    global $wpdb;

                    if ($table_name = DB::get_table_name('import')) {
                        $seconds = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT TIME_TO_SEC(TIMEDIFF(NOW(), `created_at`)) FROM {$table_name} WHERE id=%d",
                                [$import_id]
                            )
                        );
                        $output['message'] .= ' @ ' . floor($total / $seconds) . ' records per second';
                    }
                }
            }
        }

        $output['progress']['import']['start'] = 1;
        $output['progress']['import']['end'] = $stats['import_total'];
        $output['progress']['import']['current_row'] = $stats['import'];

        $output['progress']['delete']['start'] = 1;
        $output['progress']['delete']['end'] = $stats['delete_total'];
        $output['progress']['delete']['current_row'] = $stats['delete'];


        if ($table_name = DB::get_table_name('import')) {
            /**
             * @var \WPDB $wpdb
             */
            global $wpdb;
            if ($value = $wpdb->get_var("SELECT `created_at` FROM {$table_name} WHERE id={$import_id}")) {
                $output['timestamp'] = strtotime($value);
            }
        }

        return $output;
    }
}
