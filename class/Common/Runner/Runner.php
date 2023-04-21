<?php

namespace ImportWP\Common\Runner;

use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Util\Logger;

abstract class Runner
{
    /**
     * @var Properties
     */
    protected $properties;

    /**
     * @var int
     */
    protected $memory_limit;

    /**
     * @var boolean
     */
    protected $is_timeout = false;

    protected $object_type = 'importer';

    /**
     * @param Properties $properties
     */
    public function __construct($properties)
    {
        $this->properties = $properties;
    }

    abstract function process_row($id, $user, $state, $session, $section, $progress);
    abstract function setup($state, $state_data, $config, $user, $id);

    /**
     * @param int $id
     * @param string $user
     * @param RunnerState $state
     */
    public function process($id, $user, $state)
    {
        $time_limit = $this->properties->get_setting('timeout');
        Logger::info('time_limit ' . $time_limit . 's');

        $start = microtime(true);
        $max_record_time = 0;
        $memory_max_usage = 0;
        $i = 0;

        $this->try_process_dangling_state($id, $user, $state, $user);

        $config = get_site_option('iwp_' . $this->object_type . '_config_' . $id, []);

        while (
            ($i == 0 || (
                ($time_limit === 0 || $this->has_enough_time($start, $time_limit, $max_record_time))
                && $this->has_enough_memory($memory_max_usage))
            )
            && $state
            && $state->is_resumable()
        ) {

            $memory_usage = $this->get_memory_usage();

            $this->is_timeout = false;

            $state = $state->update(function ($state_data) use ($state, $config, $user, $id) {
                return $this->setup($state, $state_data, $config, $user, $id);
            });

            if (!$state || $this->is_timeout || !$state->is_running()) {
                break;
            }

            $record_time = microtime(true);
            $this->process_row($id, $user, $state, $state->get_session(), $state->get_section(), $state->get_progress());

            $max_record_time = max($max_record_time, microtime(true) - $record_time);

            if (!wp_using_ext_object_cache()) {
                wp_cache_flush();
            }

            // keep track of largest memory change
            $memory_delta = $this->get_memory_usage() - $memory_usage;
            if ($memory_delta > $memory_max_usage) {
                $memory_max_usage = $memory_delta;
            }

            $i++;
        }
    }

    /**
     * @param string $user
     * @param RunnerState $state
     */
    public function try_process_dangling_state($id, $user, $state, $user_to_check = null)
    {
        $dangling = $this->has_dangling_state($id, $user_to_check);
        if (!empty($dangling)) {
            $fixed = 0;
            foreach ($dangling as $dangling_id) {

                // TODO: Should the option be renamed to the current user first? to make sure its not ran multiple times.
                // TODO: status should not be overwritten like this.
                $GLOBALS['wp_object_cache']->delete($dangling_id, 'options');
                $status = get_site_option($dangling_id);
                if ($status && $status['last_modified'] < current_time('timestamp') - 30) {

                    Logger::write('try_import_dangling_rows -id=' . $id . ' -user=' . $user . ' -dangling=' . $dangling_id);

                    $GLOBALS['wp_object_cache']->delete($dangling_id, 'options');
                    $status['last_modified'] = current_time('timestamp');
                    update_site_option($dangling_id, $status);


                    $this->process_row($id, $user, $state, $state->get_session(), $status['section'], $status['progress'][$status['section']]);
                    delete_site_option($dangling_id);
                    $fixed++;
                }
            }

            if ($fixed !== count($dangling)) {
                // escape due to dangling records that have not timed out
                return false;
            }
        }

        return true;
    }

    function has_dangling_state($id, $user = null)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $key_prefix = 'iwp\_' . $this->object_type . '\_state';
        $query = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '{$key_prefix}\_{$id}\_";

        if (!empty($user)) {
            $query .= $user;
        } else {
            $query .= '%';
        }

        $query .= "'";

        $option_names = $wpdb->get_col($query);
        return $option_names;
    }



    function has_enough_time($start, $time_limit, $max_record_time)
    {
        return (microtime(true) - $start) < $time_limit - $max_record_time;
    }

    function get_memory_usage()
    {
        return memory_get_usage(true);
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
}
