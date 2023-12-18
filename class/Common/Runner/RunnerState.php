<?php

namespace ImportWP\Common\Runner;

use ImportWP\Common\Util\Logger;

abstract class RunnerState
{
    private $id;
    private $user;
    protected $data;
    protected static $object_type = '';

    public function __construct($id, $user)
    {
        $this->id = $id;
        $this->user = $user;
    }

    public function is_running()
    {
        return false;
    }

    public function is_resumable()
    {
        return false;
    }

    protected function default($session)
    {
        return [
            'id' => $session,
            'status' => 'init',
            'version' => 2,
            'message' => '',
            'timestamp' => time(),
            'duration' => 0
        ];
    }

    public function init($session)
    {
        $state = self::wait_for_lock_and_get_state($this->id, $this->user, $this->default($session));

        if (!$state || !isset($state['status'])) {
            throw new \Exception(__("Invalid state", 'jc-importer'));
        }

        $this->populate($state);

        if (!$this->validate($session)) {
            throw new \Exception(__("Session has changed", 'jc-importer'));
        }
    }


    public function populate($state)
    {
        foreach ($state as $name => $value) {
            $this->data[$name] = $value;
        }
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

    public function get_session()
    {
        return isset($this->data['id']) ? $this->data['id'] : false;
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

    public function validate($session_id)
    {
        $valid = $this->has_status('init') || $this->get_session() == $session_id;
        if (!$valid) {
            Logger::write("state -invalid -check={$session_id} -current={$this->get_session()}");
        }
        return $valid;
    }

    public function get_raw()
    {
        return $this->data;
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
        $raw = self::wait_for_lock($this->id, $this->user, function () use ($callback) {
            $state = self::get_state($this->id);

            if (is_callable($callback)) {
                $state = call_user_func($callback, $state);
            }

            self::set_state($this->id, $state);
            do_action('iwp/' . static::$object_type . '/status/save', $state);
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
            throw new \Exception(__("Unable to get lock", 'jc-importer'));
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
        $state['updated'] = time();
        self::update_option('iwp_' . static::$object_type . '_state_' . $id, maybe_serialize($state));
    }

    /**
     * Get last importer state
     * 
     * @return array Importer state
     */
    public static function get_state($id, $default = false)
    {
        $state = self::get_option('iwp_' . static::$object_type . '_state_' . $id);
        if (!$state) {
            $state = $default;
            if ($state !== false) {
                self::set_state($id, $state);
            }
        }

        return $state;
    }

    public static function get_lock($id, $user, $timeout_in_seconds = 10)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $lock_option_id = sprintf('iwp_' . static::$object_type . '_lock_%d', $id);
        $existing = self::get_option($lock_option_id, false);

        if ($existing !== false) {
            if (is_multisite()) {
                $query = $wpdb->prepare(
                    "UPDATE {$wpdb->sitemeta} SET meta_value=%s WHERE site_id=%d AND meta_key=%s AND meta_value=''",
                    $user,
                    $wpdb->siteid,
                    $lock_option_id
                );
            } else {
                $query = $wpdb->prepare(
                    "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=''",
                    $user,
                    $lock_option_id
                );
            }
        } else {
            return self::update_option($lock_option_id, '');
        }

        $result = $wpdb->query($query);

        if ($result) {

            self::touch_lock($id);
        } else {

            // Clear lock if timestamp is greater than timeout.
            $last_locked_time = (int)self::get_option('iwp_' . static::$object_type . '_lock_timestamp_' . $id, 0);
            if (current_time('timestamp') >  $last_locked_time + $timeout_in_seconds) {
                self::reset_lock($id);
            }
        }

        return $result;
    }

    public static function reset_lock($id)
    {
        self::update_option('iwp_' . static::$object_type . '_lock_' . $id);
        self::touch_lock($id);
    }

    public static function touch_lock($id)
    {
        self::update_option('iwp_' . static::$object_type . '_lock_timestamp_' . $id, current_time('timestamp'));
    }

    public static function get_option($key, $default = false)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if (is_multisite()) {
            $query = $wpdb->prepare("SELECT meta_id as id, meta_value as data FROM {$wpdb->sitemeta} WHERE site_id=%s AND meta_key=%s LIMIT 1", [$wpdb->siteid, $key]);
        } else {
            $query = $wpdb->prepare("SELECT option_id as id, option_value as data FROM {$wpdb->options} WHERE option_name=%s LIMIT 1", [$key]);
        }

        $result = $wpdb->get_row($query, ARRAY_A);
        if (!$result) {
            return $default;
        }

        if (is_serialized($result['data'])) {
            return unserialize($result['data']);
        }

        return $result['data'];
    }

    public static function update_option($key, $value = '')
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $result = self::get_option($key);

        if (is_multisite()) {

            if ($result !== false) {
                $result = $wpdb->update($wpdb->sitemeta, ['meta_value' => $value], ['meta_key' => $key, 'site_id' => $wpdb->siteid], ['%s'], ['%s', '%s']);
            } else {
                $result = $wpdb->insert($wpdb->sitemeta, ['meta_value' => $value, 'meta_key' => $key, 'site_id' => $wpdb->siteid], ['%s', '%s', '%s']);
            }
        } else {
            if ($result !== false) {
                $result = $wpdb->update($wpdb->options, ['option_value' => $value], ['option_name' => $key], ['%s'], ['%s']);
            } else {
                $result = $wpdb->insert($wpdb->options, ['option_value' => $value, 'option_name' => $key], ['%s', '%s']);
            }
        }

        return $result;
    }

    public static function clear_options($id)
    {

        // clear existing
        delete_site_option('iwp_' . static::$object_type . '_config_' . $id);
        delete_site_option('iwp_' . static::$object_type . '_state_' . $id);
        delete_site_option('iwp_' . static::$object_type . '_lock_' . $id);
        delete_site_option('iwp_' . static::$object_type . '_lock_timestamp_' . $id);

        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if (is_multisite()) {
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'iwp\_" . static::$object_type . "\_state\_" . $id . "\_%'");
        } else {
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'iwp\_" . static::$object_type . "\_state\_" . $id . "\_%'");
        }
    }
}
