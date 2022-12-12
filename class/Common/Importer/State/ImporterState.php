<?php

namespace ImportWP\Common\Importer\State;

use ImportWP\Common\Util\Logger;

class ImporterState
{
    private $importer_id;
    private $user;
    protected $data;

    public function __construct($importer_id, $user)
    {
        $this->importer_id = $importer_id;
        $this->user = $user;
    }

    public function init($session)
    {
        $state = self::wait_for_lock_and_get_state($this->importer_id, $this->user, [
            'version' => 2,
            'status' => 'init',
            'section' => 'import',
            'message' => '',
            'progress' => [
                'import' => [
                    'start' => 0,
                    'end' => 0,
                    'current_row' => 0,
                ],
                'delete' => [
                    'start' => 0,
                    'end' => 0,
                    'current_row' => 0,
                ]
            ],
            'updated' => time(),
            'timestamp' => time(),
            'duration' => 0
        ]);

        if (!$state || !isset($state['status'])) {
            throw new \Exception("Invalid importer state");
        }

        $this->populate($state);

        if (!$this->validate($session)) {
            throw new \Exception("Importer session has changed");
        }
    }

    public function populate($state)
    {
        foreach ($state as $name => $value) {
            $this->data[$name] = $value;
        }
    }

    public function has_status($status)
    {
        if (!isset($this->data['status'])) {
            return false;
        }

        if (is_array($status)) {
            return in_array($this->data['status'], $status);
        }

        return $this->data['status'] == $status;
    }

    public function has_section($section)
    {
        if (!isset($this->data['section'])) {
            return false;
        }

        if (is_array($section)) {
            return in_array($this->data['section'], $section);
        }

        return $this->data['section'] == $section;
    }

    public function get_session()
    {
        return isset($this->data['id']) ? $this->data['id'] : false;
    }

    public function get_status()
    {
        return isset($this->data['status']) ? $this->data['status'] : false;
    }

    public function validate($session_id)
    {
        return $this->has_status('init') || $this->get_session() == $session_id;
    }

    public function get_raw()
    {
        return $this->data;
    }

    public function get_importer_id()
    {
        return $this->importer_id;
    }

    public function get_section()
    {
        if (!isset($this->data['section'])) {
            return false;
        }

        return $this->data['section'];
    }

    public function get_progress($section = null)
    {
        if (is_null($section)) {
            $section = $this->get_section();
        }

        return $this->data['progress'][$section];
    }

    /**
     * Update live state data using callback
     * 
     * @param Closure[array]:array $state
     * 
     * @return $this
     */
    public function update($callback)
    {
        $raw = self::wait_for_lock($this->importer_id, $this->user, function () use ($callback) {
            $state = self::get_state($this->importer_id);

            if (is_callable($callback)) {
                $state = call_user_func($callback, $state);
            }

            self::set_state($this->importer_id, $state);
            do_action('iwp/importer/status/save', $state);
            return $state;
        });

        $this->populate($raw);

        return $this;
    }

    /**
     * Log fatal error
     *
     * @param \Exception $error
     * @return $this
     */
    public function error($error)
    {
        return $this->update(function ($data) use ($error) {

            $data['status'] = 'error';
            $data['message'] = $error->getMessage();
            $data['duration'] = floatval($data['duration']) + Logger::timer();

            return $data;
        });
    }

    public static function wait_for_lock($id, $user, $callback)
    {

        $start = microtime(true);

        do {
            list($has_lock, $result) = self::try_get_lock($id, $user, $callback);
        } while (!$has_lock && (microtime(true) - $start < 30));

        if (!$has_lock) {
            throw new \Exception("Unable to get lock");
        }

        return $result;
    }

    public static function wait_for_lock_and_get_state($id, $user, $default = false)
    {
        return self::wait_for_lock($id, $user, function () use ($id, $default) {
            return self::get_state($id, $default);
        });
    }

    /**
     * Get importer lock to update state
     * 
     * @param mixed $user current id
     * @param mixed $callback Callback triggered when we have a lock.
     * @param int $wait_in_microseconds Time to wait in microseconds before retrying
     * 
     * @return false|array 
     */
    public static function try_get_lock($id, $user, $callback)
    {
        $has_lock = self::get_lock($id, $user);
        if ($has_lock) {

            $result = $has_lock;

            if (is_callable($callback)) {
                $result = call_user_func($callback);
            }

            self::reset_lock($id);

            return [true, $result];
        } else {

            return [false, null];
        }
    }

    /**
     * Update importer state
     * 
     * @param mixed $state State to overwrite
     * 
     * @return void 
     */
    public static function set_state($id, $state)
    {
        $GLOBALS['wp_object_cache']->delete('iwp_importer_state_' . $id, 'options');
        $state['updated'] = time();
        update_site_option('iwp_importer_state_' . $id, $state);
    }

    /**
     * Get last importer state
     * 
     * @return array Importer state
     */
    public static function get_state($id, $default = false)
    {
        $GLOBALS['wp_object_cache']->delete('iwp_importer_state_' . $id, 'options');

        $state = get_site_option('iwp_importer_state_' . $id);
        if (!$state) {
            $state = $default;
            update_site_option('iwp_importer_state_' . $id, $state);
        }

        return $state;
    }


    public static function get_lock($id, $user, $timeout_in_seconds = 10)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $result = $wpdb->query("UPDATE {$wpdb->options} SET option_value='{$user}' WHERE option_name='iwp_importer_lock_{$id}' AND option_value=''");

        if ($result) {

            self::touch_lock($id);
        } else {

            // Clear lock if timestamp is greater than timeout.
            $last_locked_time = get_site_option('iwp_importer_lock_timestamp_' . $id, 0);
            if (current_time('timestamp') >  $last_locked_time + $timeout_in_seconds) {
                self::reset_lock($id);
            }
        }

        return $result;
    }

    public static function reset_lock($id)
    {
        $GLOBALS['wp_object_cache']->delete('iwp_importer_lock_' . $id, 'options');
        update_site_option('iwp_importer_lock_' . $id, '');
        self::touch_lock($id);
    }

    public static function touch_lock($id)
    {
        $GLOBALS['wp_object_cache']->delete('iwp_importer_lock_timestamp_' . $id, 'options');
        update_site_option('iwp_importer_lock_timestamp_' . $id, current_time('timestamp'));
    }
}
