<?php

namespace ImportWP\Common\Importer\State;

use ImportWP\Common\Util\Logger;

class ImporterState
{
    private $data = [];
    private $importer_id;
    protected static $object_type = 'importer';

    public function __construct($importer_id, $user = '')
    {
        $this->importer_id = $importer_id;
    }

    protected function default($session_id)
    {
        return [
            'id' => $session_id,
            'status' => 'init',
            'version' => 2,
            'message' => '',
            'timestamp' => time(),
            'duration' => 0,
            'section' => 'import',
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
        ];
    }

    public function init($session_id)
    {
        // get current or set default to new session_id
        $state = self::get_state($this->importer_id);
        if (!$state) {
            $state = $this->default($session_id);
            $this->set_state($this->importer_id, $state);
        }

        if (!$state || !isset($state['status'])) {
            throw new \Exception(__("Invalid state", 'jc-importer'));
        }

        $this->populate($state);

        if (!$this->validate($session_id)) {
            throw new \Exception(__("Session has changed", 'jc-importer'));
        }
    }


    public function populate($state_data)
    {
        foreach ($state_data as $name => $value) {
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

    public function get_session()
    {
        return isset($this->data['id']) ? $this->data['id'] : false;
    }

    public function update($callback)
    {
        $raw = [];
        if (is_callable($callback)) {
            $raw = call_user_func($callback, $this->data);
        }

        $this->populate($raw);

        $this->set_state($this->importer_id, $this->data);

        return $this;
    }

    public function error($error)
    {
        $this->update(function ($state) use ($error) {
            $state['status'] = 'error';
            if (is_wp_error($error)) {
                /**
                 * @var \WP_Error $error
                 */
                $state['message'] = $error->get_error_message();
            } else if ($error instanceof \Exception) {
                /**
                 * @var \Exception $error
                 */
                $state['message'] = $error->getMessage();
            }
            return $state;
        });
        return $this;
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

    public static function wait_for_lock($importer_id, $user, $callback)
    {
        $result = null;
        if (is_callable($callback)) {
            $result = call_user_func($callback);
        }

        return $result;
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
        do_action('iwp/' . static::$object_type . '/status/save', $state);
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

    public static function get_option($key, $default = false)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if (is_multisite()) {
            $query = $wpdb->prepare("SELECT meta_id as id, meta_value as `data` FROM {$wpdb->sitemeta} WHERE site_id=%s AND meta_key=%s LIMIT 1", [$wpdb->siteid, $key]);
        } else {
            $query = $wpdb->prepare("SELECT option_id as id, option_value as `data` FROM {$wpdb->options} WHERE option_name=%s LIMIT 1", [$key]);
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

        if (is_multisite()) {

            $result = $wpdb->update($wpdb->sitemeta, ['meta_value' => $value], ['meta_key' => $key, 'site_id' => $wpdb->siteid], ['%s'], ['%s', '%s']);
            if (intval($result) < 1) {
                $result = $wpdb->insert($wpdb->sitemeta, ['meta_value' => $value, 'meta_key' => $key, 'site_id' => $wpdb->siteid], ['%s', '%s', '%s']);
            }
        } else {
            $result = $wpdb->update($wpdb->options, ['option_value' => $value], ['option_name' => $key], ['%s'], ['%s']);
            if (intval($result) < 1) {
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
        delete_site_option('iwp_' . static::$object_type . '_flag_' . $id);

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

    function update_importer_stats($stats)
    {
        if (!isset($this->data['stats'])) {
            $this->data['stats'] = [
                'inserts' => 0,
                'updates' => 0,
                'deletes' => 0,
                'skips' => 0,
                'errors' => 0,
            ];
        }

        $this->data['stats']['inserts'] += $stats['inserts'];
        $this->data['stats']['updates'] += $stats['updates'];
        $this->data['stats']['deletes'] += $stats['deletes'];
        $this->data['stats']['skips'] += $stats['skips'];
        $this->data['stats']['errors'] += $stats['errors'];
    }

    function get_stats()
    {
        if (!isset($this->data['stats'])) {
            $this->data['stats'] = [
                'inserts' => 0,
                'updates' => 0,
                'deletes' => 0,
                'skips' => 0,
                'errors' => 0,
            ];
        }

        return $this->data['stats'];
    }

    function increment_current_row($section = null)
    {
        if (is_null($section)) {
            $section = $this->get_section();
        }
        $this->data['progress'][$section]['current_row']++;
    }

    static function get_flag($id)
    {
        return self::get_option('iwp_' . static::$object_type . '_flag_' . $id);
    }

    static function is_paused($flag)
    {
        return $flag == 'paused';
    }

    static function is_cancelled($flag)
    {
        return $flag == 'cancelled';
    }

    static function set_paused($id)
    {
        self::update_option('iwp_' . static::$object_type . '_flag_' . $id, 'paused');
    }

    static function set_cancelled($id)
    {
        self::update_option('iwp_' . static::$object_type . '_flag_' . $id, 'cancelled');
    }

    static function clear_flag($id)
    {
        self::update_option('iwp_' . static::$object_type . '_flag_' . $id, '');
    }
}
