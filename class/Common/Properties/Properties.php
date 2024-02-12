<?php

namespace ImportWP\Common\Properties;

use ImportWP\Common\Util\Singleton;

#[\AllowDynamicProperties]
class Properties
{
    use Singleton;

    public $plugin_dir_path;
    public $plugin_folder_name;
    public $plugin_basename;
    public $view_dir;
    public $plugin_domain;
    public $plugin_version;
    public $plugin_file_path;
    public $encodings;
    public $chunk_timeout;
    public $chunk_limit;
    public $chunk_size;
    public $file_rotation;
    public $log_rotation;
    public $timeout;
    public $is_pro;

    public $mu_plugin_version;
    public $mu_plugin_dir;
    public $mu_plugin_source;
    public $mu_plugin_dest;

    public $rest_version;
    public $rest_namespace;

    public function __construct()
    {
        $this->plugin_file_path = realpath(dirname(__DIR__) . '/../../jc-importer.php');
        $this->plugin_dir_path = plugin_dir_path($this->plugin_file_path);
        $this->plugin_folder_name = basename($this->plugin_dir_path);
        $this->plugin_basename = plugin_basename($this->plugin_file_path);
        $this->plugin_domain = 'importwp';
        $this->plugin_version = IWP_VERSION;
        $this->is_pro = false;
        $this->encodings = $this->get_available_encodings();

        $this->view_dir = $this->plugin_dir_path . trailingslashit('views');

        $this->rest_namespace = 'iwp';
        $this->rest_version = 'v1';

        $this->file_rotation = intval(apply_filters('iwp/file_rotation', $this->get_setting('file_rotation')));
        $this->log_rotation = intval(apply_filters('iwp/log_rotation', $this->get_setting('log_rotation')));
        $this->timeout = intval(apply_filters('iwp/timeout', $this->get_setting('timeout')));
    }

    protected function get_available_encodings()
    {
        $encodings = mb_list_encodings();
        $output = [];
        foreach ($encodings as $encoding) {
            $output[$encoding] = $encoding;
        }
        return $output;
    }

    public function _default_settings()
    {
        return [
            'debug' => [
                'type' => 'bool',
                'value' => false,
            ],
            'cleanup' => [
                'type' => 'bool',
                'value' => false
            ],
            'file_rotation' => [
                'type' => 'number',
                'value' => 5
            ],
            'log_rotation' => [
                'type' => 'number',
                'value' => -1
            ],
            'timeout' => [
                'type' => 'number',
                'value' => 30
            ],
        ];
    }

    public function get_settings()
    {
        $defaults = $this->_default_settings();
        $settings = get_site_option('iwp_settings', []);
        $output = [];

        foreach ($defaults as $setting => $default) {
            $output[$setting] = isset($settings[$setting]) ? $settings[$setting] : $default['value'];
        }

        return $output;
    }

    public function get_setting($key)
    {
        $defaults = $this->_default_settings();
        $settings = get_site_option('iwp_settings', []);

        if (!isset($defaults[$key])) {
            return false;
        }

        return isset($settings[$key]) ? $settings[$key] : $defaults[$key]['value'];
    }
}
