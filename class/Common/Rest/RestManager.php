<?php

namespace ImportWP\Common\Rest;

use ImportWP\Common\Exporter\ExporterManager;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Http\Http;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Importer\Preview\CSVPreview;
use ImportWP\Common\Importer\Preview\XMLPreview;
use ImportWP\Common\Importer\State\ImporterState;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Migration\Migrations;
use ImportWP\Common\Model\ExporterModel;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Util\Logger;
use ImportWP\Common\Util\Util;
use ImportWP\Container;
use ImportWP\EventHandler;

class RestManager extends \WP_REST_Controller
{
    /**
     * @var Properties
     */
    protected $properties;

    /**
     * @var Http
     */
    protected $http;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var ImporterManager
     */
    protected $importer_manager;

    /**
     * @var ExporterManager
     */
    protected $exporter_manager;

    /**
     * @var TemplateManager $template_manager
     */
    protected $template_manager;

    /**
     * @var EventHandler
     */
    protected $event_handler;

    public function __construct(ImporterManager $importer_manager, ExporterManager $exporter_manager, Properties $properties, Http $http, Filesystem $filesystem, TemplateManager $template_manager, $event_handler)
    {
        $this->importer_manager = $importer_manager;
        $this->exporter_manager = $exporter_manager;
        $this->properties = $properties;
        $this->http = $http;
        $this->filesystem = $filesystem;
        $this->template_manager = $template_manager;
        $this->event_handler = $event_handler;
    }

    public function register()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        $namespace = $this->properties->rest_namespace . '/' . $this->properties->rest_version;

        register_rest_route($namespace, '/system/check', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'check_rest_status'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));
        register_rest_route($namespace, '/system/migrate', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'migrate'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'save_importer'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importers', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_importers'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));

        register_rest_route($namespace, '/status', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_status'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_importer'),
                'permission_callback' => array($this, 'get_permission')
            ),
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'save_importer'),
                'permission_callback' => array($this, 'get_permission')
            ),
            array(
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_importer'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/upload', array(
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'attach_file'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/file-process', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'get_file_process'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/file-preview', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'get_file_preview'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/preview', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'get_preview'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/init', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'init_import'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/run', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'run_import'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/pause', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'pause_import'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/stop', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'stop_import'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/field', array(
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array($this, 'get_template_field_options'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/logs/(?P<session>\S+)', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_log'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/logs', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_logs'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/settings', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'save_settings'),
                'permission_callback' => array($this, 'get_permission')
            ),
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_settings'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));

        register_rest_route($namespace, '/import-export', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'import_exporters'),
                'permission_callback' => array($this, 'get_permission')
            ),
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'export_importers'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/debug_log', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_debug_log'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/(?P<id>\d+)/template', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_template'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        // Exporter

        register_rest_route($namespace, '/exporter', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'save_exporter'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/exporters', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_exporters'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));

        register_rest_route($namespace, '/exporter/(?P<id>\d+)', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_exporter'),
                'permission_callback' => array($this, 'get_permission')
            ),
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'save_exporter'),
                'permission_callback' => array($this, 'get_permission')
            ),
            array(
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_exporter'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));

        register_rest_route($namespace, '/exporter/(?P<id>\d+)/run', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'run_exporter'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));

        register_rest_route($namespace, '/exporter/status', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_exporter_status'),
                'permission_callback' => array($this, 'get_permission')
            ),
        ));
    }

    public function get_permission()
    {

        if (!current_user_can('manage_options')) {
            return new \WP_Error('rest_forbidden', esc_html__('You do not have permissions.', $this->properties->plugin_domain), array('status' => 401));
        }

        return true;
    }

    public function sanitize($data, $name = null, $path = [])
    {
        $path[] = $name;

        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->sanitize($v, $k, $path);
            }

            return $data;
        }

        $full_path = implode('.', array_filter($path));
        if (!is_null($full_path)) {

            if (preg_match('/^map\.\S+/', $full_path) !== false) {
                return $data;
            }
        }

        if (!is_null($name)) {
            switch ($name) {
                case 'id':
                    return intval($data);
            }
        }

        return sanitize_text_field($data);
    }

    public function check_rest_status(\WP_REST_Request $request)
    {
        $result = [];
        // find required php ext: https://github.com/llaville/php-compat-info

        $tmp_writable = wp_is_writable($this->filesystem->get_temp_directory());


        $result = [
            'tmp_writable' => [
                'status' => $tmp_writable ? 'yes' : 'no',
                'message' => $tmp_writable ? '' : 'WordPress is unable to write to directory: ' . $this->filesystem->get_temp_directory() . ', make sure this directory is writable.',
            ],
            'rest_enabled' => [
                'status' => 'yes',
                'message' => '',
            ],
            'ext_simplexml' => [
                'status' => extension_loaded('SimpleXML') ? 'yes' : 'no',
                'message' => '',
            ],
            'ext_mbstring' => [
                'status' => extension_loaded('mbstring') ? 'yes' : 'no',
                'message' => '',
            ],
            'ext_xmlreader' => [
                'status' => extension_loaded('xmlreader') ? 'yes' : 'no',
                'message' => '',
            ],
            'php_version' => [
                'status' => version_compare(phpversion(), '5.5.0') ? 'yes' : 'no',
                'message' => '',
            ],
        ];
        return $this->http->end_rest_success($result);
    }

    public function migrate(\WP_REST_Request $request)
    {
        $migration = new Migrations();
        $result = $migration->migrate();

        return $this->http->end_rest_success("Migration Complete.");
    }

    public function get_importers(\WP_REST_Request $request)
    {
        $importers = $this->importer_manager->get_importers();
        $result = [];

        foreach ($importers as $importer) {
            $result[] = $importer->data();
        }

        return $this->http->end_rest_success($result);
    }

    public function get_importer(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $importer_data = $this->importer_manager->get_importer($id);
        $response = $importer_data->data('public');
        return $this->http->end_rest_success($response);
    }

    public function save_importer(\WP_REST_Request $request)
    {
        $post_data = $request->get_body_params();
        $id = isset($post_data['id']) ? intval($post_data['id']) : null;

        $temp_post_data = [
            'create' => [],
            'update' => []
        ];

        if (isset($post_data['permissions'])) {
            // TODO: update sanitize method to keep track of hierarchy
            $temp_post_data['create'] = explode("\n", $post_data['permissions']['create']['fields']);
            $temp_post_data['update'] = explode("\n", $post_data['permissions']['update']['fields']);
        }

        $post_data = $this->sanitize($post_data);

        if (isset($post_data['permissions'])) {
            // TODO: Method to bypass sanitization not needed once sanitize method has been upgraded
            foreach (['create', 'update'] as $permission_method) {
                $permission_fields = $temp_post_data[$permission_method];
                if (!empty($permission_fields)) {
                    $post_data['permissions'][$permission_method]['fields'] = array_values(array_filter(array_map([$this, 'sanitize'], $permission_fields, array_fill(0, count($permission_fields), 'permission_' . $permission_method . '_fields'))));
                } else {
                    $post_data['permissions'][$permission_method]['fields'] = [];
                }
            }
        }



        $importer = new ImporterModel($id, $this->importer_manager->is_debug());

        if (isset($post_data['datasource'])) {
            $importer->setDatasource($post_data['datasource']);
        }

        if (isset($post_data['remote_url'])) {
            $importer->setDatasourceSetting('remote_url', $post_data['remote_url']);
        }

        if (isset($post_data['local_url'])) {
            $importer->setDatasourceSetting('local_url', $post_data['local_url']);
        }

        if (isset($post_data['existing_id'])) {
            $importer->setFileId(intval($post_data['existing_id']));
        }

        if (isset($post_data['template'], $post_data['template_type'])) {
            $importer->setTemplate($post_data['template'], $post_data['template_type']);

            // Get default template options
            $template_class = $this->importer_manager->get_template($post_data['template']);

            /**
             * @var Template $template
             */
            $template = $this->template_manager->load_template($template_class);
            $template_options = $template->get_default_template_options();

            if (isset($post_data['template_options'])) {
                $template_options = array_merge($template_options, $post_data['template_options']);
            }

            foreach ($template_options as $key => $val) {
                $importer->setSetting($key, $val);
            }
        }

        if (isset($post_data['name'])) {
            $importer->setName($post_data['name']);
        }

        if (isset($post_data['setting_start_row'])) {
            $importer->setStartRow($post_data['setting_start_row']);
        }

        if (isset($post_data['setting_max_row'])) {
            $importer->setMaxRow($post_data['setting_max_row']);
        }

        // save settings
        foreach ($post_data as $key => $value) {
            if (1 === preg_match('/^setting_(?<key>.*)/', $key, $matches)) {

                $value = $this->sanitize_setting($value);
                $importer->setSetting($matches['key'], $value);
            }
        }

        $parser = $importer->getParser();

        if (isset($post_data['file_settings_encoding'])) {
            $importer->setFileSetting('file_encoding', $post_data['file_settings_encoding']);
        }

        if ($parser === 'csv') {
            if (isset($post_data['file_settings_delimiter'])) {
                $importer->setFileSetting('delimiter', $post_data['file_settings_delimiter']);
            }
            if (isset($post_data['file_settings_enclosure'])) {
                $importer->setFileSetting('enclosure', $post_data['file_settings_enclosure']);
            }
            if (isset($post_data['file_settings_show_headings'])) {
                $importer->setFileSetting('show_headings', $post_data['file_settings_show_headings'] === 'true' || $post_data['file_settings_show_headings'] === true ? true : false);
            }
        } elseif ($parser === 'xml') {
            if (isset($post_data['file_settings_base_path'])) {
                $importer->setFileSetting('base_path', $post_data['file_settings_base_path']);
            }
        }

        if (isset($post_data['file_settings_setup'])) {
            $importer->setFileSetting('setup', $post_data['file_settings_setup'] === 'true' ? true : false);
        }

        if (isset($post_data['map']) && is_array($post_data['map'])) {
            foreach ($post_data['map'] as $key => $value) {

                if (1 === preg_match('/\._index$/', $key)) {
                    $value = intval($value);
                }

                $importer->setMap($key, $value);
            }
        }

        if (isset($post_data['enabled']) && is_array($post_data['enabled'])) {
            foreach ($post_data['enabled'] as $key => $value) {
                if ($value === 'true') {
                    $importer->setEnabled($key);
                } else {
                    $importer->setEnabled($key, false);
                }
            }
        }

        if (isset($post_data['permissions']) && is_array($post_data['permissions'])) {

            // create
            if (isset($post_data['permissions']['create'])) {
                $create = $post_data['permissions']['create'];
                $importer->setPermission('create', [
                    'enabled' => isset($create['enabled']) && $create['enabled'] === 'true' ? true : false,
                    'type' => isset($create['type']) && in_array($create['type'], ['include', 'exclude'], true) ? $create['type'] : null,
                    'fields' => isset($create['fields']) ? $create['fields'] : [],
                ]);
            }

            // update
            if (isset($post_data['permissions']['update'])) {
                $update = $post_data['permissions']['update'];
                $importer->setPermission('update', [
                    'enabled' => isset($update['enabled']) && $update['enabled'] === 'true' ? true : false,
                    'type' => isset($update['type']) && in_array($update['type'], ['include', 'exclude'], true) ? $update['type'] : null,
                    'fields' => isset($update['fields']) ? $update['fields'] : [],
                ]);
            }

            // remove
            if (isset($post_data['permissions']['remove'])) {
                $importer->setPermission('remove', [
                    'enabled' => $post_data['permissions']['remove']['enabled'] === 'true',
                    'trash' => $post_data['permissions']['remove']['trash'] === 'true',
                ]);
            }
        }

        $result = $importer->save();
        if (is_wp_error($result)) {
            return $this->http->end_rest_error($result->get_error_message());
        }
        return $this->http->end_rest_success($importer->data());
    }

    private function sanitize_setting($value)
    {
        if (is_array($value)) {
            foreach ($value as &$tmp) {
                $tmp = $this->sanitize_setting($tmp);
            }
            return $value;
        } else {
            if (in_array($value, ['true', 'false'], true)) {
                if ($value === 'true') {
                    $value = true;
                } else {
                    $value = false;
                }
            }
        }

        return $value;
    }

    public function get_status(\WP_REST_Request $request)
    {
        $this->http->set_stream_headers();

        Logger::setRequestType('rest::get_status');

        $importer_ids = $request->get_param('ids');

        $result = [];

        $query_data = array(
            'post_type'      => IWP_POST_TYPE,
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        if (!empty($importer_ids)) {
            $query_data['post__in'] = $importer_ids;
        }

        $query  = new \WP_Query($query_data);

        foreach ($query->posts as $importer_id) {

            Logger::write('-start', $importer_id);

            $importer_model = $this->importer_manager->get_importer($importer_id);

            $output = ImporterState::get_state($importer_id);
            if (!$output) {
                // TODO: What should happen if there is no state.
                $output['version'] = 2;
            }
            $output['message'] = $this->generate_status_message($output);
            $output['importer'] = $importer_id;

            $result[] = $this->event_handler->run('iwp/importer/status/output', [$output, $importer_model]);

            Logger::write('-end', $importer_id);
        }

        Logger::clearRequestType();

        echo json_encode($result) . "\n";
        die();
    }

    /**
     * @param array $data
     * @return string
     */
    protected function generate_status_message($data)
    {
        $output = '';

        if (!$data || !isset($data['status']) || is_null($data['status']) || empty($data['status'])) {
            return '';
        }

        if (isset($data['status'])) {
            switch ($data['status']) {
                case 'cancelled':
                    $output .= 'Import cancelled';
                    break;
                case 'complete':
                    $output .= 'Import complete';
                    break;
                case 'error':
                    $output = $data['message'];
                    return $output;
                    break;
            }
        }

        if (isset($data['status']) && $data['status'] === 'running' && isset($data['section'])) {
            switch ($data['section']) {
                case 'import':
                case 'delete':
                    $output = $data['section'] === 'import' ? 'Importing: ' : 'Deleting: ';
                    $output .= $data['progress'][$data['section']]['current_row'] . ' / ' . ($data['progress'][$data['section']]['end'] - $data['progress'][$data['section']]['start']);

                    break;
            }
        }

        if (isset($data['stats'])) {
            if ($data['stats']['inserts'] > 0) {
                $output .= sprintf(', Inserts: %d', $data['stats']['inserts']);
            }
            if ($data['stats']['updates'] > 0) {
                $output .= sprintf(', Updates: %d', $data['stats']['updates']);
            }
            if ($data['stats']['deletes'] > 0) {
                $output .= sprintf(', Deletes: %d', $data['stats']['deletes']);
            }
            if ($data['stats']['skips'] > 0) {
                $output .= sprintf(', Skips: %d', $data['stats']['skips']);
            }
            if ($data['stats']['errors'] > 0) {
                $output .= sprintf(', Errors: %d', $data['stats']['errors']);
            }
        }

        if (isset($data['timestamp'], $data['updated'])) {
            /**
             * @var Util $util
             */
            $util = Container::getInstance()->get('util');
            $output .= ', Run Time: ' . $util->format_time($data['updated'] - $data['timestamp']);
        } elseif (isset($data['duration'])) {
            /**
             * @var Util $util
             */
            $util = Container::getInstance()->get('util');
            $output .= ', Run Time: ' . $util->format_time($data['duration']);
        }

        return $output;
    }

    public function delete_importer(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $this->importer_manager->delete_importer($id);
        return $this->http->end_rest_success(true);
    }

    public function attach_file(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $post_data = $this->sanitize($request->get_body_params());

        $action = $post_data['action'];

        $importer = $this->importer_manager->get_importer($id);

        // TODO: Remove duplicate code, found in cron manager

        switch ($action) {
            case 'file_upload':
                $attachment_id = $this->importer_manager->upload_file($importer, $_FILES['file']);
                break;
            case 'file_remote':
                $raw_source = $post_data['remote_url'];
                $source = apply_filters('iwp/importer/datasource', $raw_source, $raw_source, $importer);
                $source = apply_filters('iwp/importer/datasource/remote', $source, $raw_source, $importer);

                $filetype = $importer->getParser();
                if (is_null($filetype)) {
                    $filetype = $post_data['filetype'];
                }

                $attachment_id = $this->importer_manager->remote_file($importer, $source, $filetype, null, $importer->getId() . '-');
                break;
            case 'file_local':
                $raw_source = $post_data['local_url'];
                $source = apply_filters('iwp/importer/datasource', $raw_source, $raw_source, $importer);
                $source = apply_filters('iwp/importer/datasource/local', $source, $raw_source, $importer);
                $attachment_id = $this->importer_manager->local_file($importer, $source);
                break;
            default:
                $attachment_id = new \WP_Error('IWP_RM_1', 'Invalid form action');
                break;
        }

        if (is_wp_error($attachment_id)) {
            return $this->http->end_rest_error($attachment_id->get_error_message());
        }

        return $this->http->end_rest_success($importer->data());
    }

    public function get_file_process(\WP_REST_Request $request)
    {
        try {
            $id = intval($request->get_param('id'));
            $post_data = $this->sanitize($request->get_body_params());

            $importer = $this->importer_manager->get_importer($id);
            if (!$importer) {
                return $this->http->end_rest_error('Invalid importer');
            }

            $this->importer_manager->clear_config_files($id, true);
            $parser = $importer->getParser();

            if ('xml' === $parser) {

                $nodes = $this->importer_manager->process_xml_file($id, true);
                $importer->setFileSetting('nodes', $nodes);
            } elseif ('csv' === $parser) {

                $delimiter = isset($post_data['delimiter']) ? $post_data['delimiter'] : null;
                if (is_null($delimiter)) {
                    $delimiter = ',';
                }

                $enclosure = isset($post_data['enclosure']) ? $post_data['enclosure'] : null;
                if (is_null($enclosure)) {
                    $enclosure = '"';
                }

                $count = $this->importer_manager->process_csv_file($id, $delimiter, $enclosure, true);

                // Need fresh importer, due to process_csv_file importer modifications might get overwritten.
                $importer = $this->importer_manager->get_importer($id);
                $importer->setFileSetting('count', $count);
            } else {

                do_action('iwp/file-process/' . $parser, $importer, $post_data);
            }

            $importer->setFileSetting('processed', true);
            $importer->save();

            return $this->http->end_rest_success($importer->data());
        } catch (\Exception $e) {
            return $this->http->end_rest_error($e->getMessage());
        }
    }

    public function get_file_preview(\WP_REST_Request $request)
    {
        try {
            $id = intval($request->get_param('id'));
            $post_data = $this->sanitize($request->get_body_params());
            $importer = $this->importer_manager->get_importer($id);

            $import_file_exists = $this->filesystem->file_exists($importer->getFile());
            if (is_wp_error($import_file_exists)) {
                throw new \Exception("Unable to load preview, " . $import_file_exists->get_error_message());
            }

            $record_index = isset($post_data['record']) ? $post_data['record'] : null;
            if (is_null($record_index)) {
                $record_index = 0;
            }

            // Temp set file_encoding
            if (isset($post_data['file_encoding'])) {
                $importer->setFileSetting('file_encoding', $post_data['file_encoding']);
            }

            if ($importer->getParser() === 'xml') {

                $config = $this->importer_manager->get_config($importer, true);
                $file = $this->importer_manager->get_xml_file($importer, $config);

                $base_path = $post_data['base_path'];
                if (!is_null($base_path)) {
                    $file->setRecordPath($base_path);
                }

                $preview = new XMLPreview($file, $base_path);
                $result = $preview->data();
                if (is_wp_error($result)) {
                    return $this->http->end_rest_error($result);
                }

                return $this->http->end_rest_success($result[0]);
            } elseif ($importer->getParser() === 'csv') {

                $config = $this->importer_manager->get_config($importer, true);
                $file = $this->importer_manager->get_csv_file($importer, $config);

                $delimiter = $post_data['delimiter'];
                if (!is_null($delimiter)) {
                    $file->setDelimiter($delimiter);
                }

                $enclosure = $post_data['enclosure'];
                if (!is_null($enclosure)) {
                    $file->setEnclosure($enclosure);
                }

                // parse bool param from string
                $show_headings = $post_data['show_headings'];
                if (in_array($show_headings, ['true', 'false'])) {
                    $show_headings = $show_headings === 'true' ? true : false;
                }

                if (is_null($show_headings)) {
                    $show_headings = $importer->getFileSetting('show_headings');
                }

                $preview = new CSVPreview($file);
                $result = $preview->data($record_index, $show_headings);
                if (is_wp_error($result)) {
                    return $this->http->end_rest_error($result);
                }

                return $this->http->end_rest_success($result);
            } else {

                $result = apply_filters('iwp/file-preview/' . $importer->getParser(), null, $importer);
                if (is_wp_error($result)) {
                    return $this->http->end_rest_error($result);
                }

                return $this->http->end_rest_success($result);
            }
        } catch (\Exception $error) {
            return $this->http->end_rest_error($error->getMessage());
        }
    }

    public function get_preview(\WP_REST_Request $request)
    {
        // if no fields have been posted send all previews
        try {
            $id = intval($request->get_param('id'));
            $importer = $this->importer_manager->get_importer($id);
            $import_file_exists = $this->filesystem->file_exists($importer->getFile());
            if (is_wp_error($import_file_exists)) {
                throw new \Exception("Unable to load preview, " . $import_file_exists->get_error_message());
            }

            $parser = $importer->getParser();

            $fields = $this->sanitize($request->get_body_params());
            if (is_null($fields) || empty($fields)) {
                $fields = $importer->getMap();
            }

            if ('xml' === $parser) {
                $result = $this->importer_manager->preview_xml_file($importer, $fields);
            } elseif ('csv' === $parser) {
                $row = 0;
                if ($importer->getFileSetting('show_headings') === true) {
                    $row = 1;
                }
                $result = $this->importer_manager->preview_csv_file($importer, $fields, $row);
            } else {

                $result = apply_filters('iwp/record-preview/' . $parser, [], $importer, $fields);
                if (is_wp_error($result)) {
                    return $this->http->end_rest_error($result);
                }

                return $this->http->end_rest_success($result);
            }

            return $this->http->end_rest_success($result);
        } catch (\Exception $error) {
            return $this->http->end_rest_error($error->getMessage());
        }
    }

    /**
     * Trigger a new import.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function init_import(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));

        Logger::setRequestType('rest::init_import');

        Logger::write('init_import -start', $id);

        $importer_data = $this->importer_manager->get_importer($id);

        // clear existing
        ImporterState::clear_options($importer_data->getId());

        // This is used for storing version on imported records
        $session_id = md5($importer_data->getId() . time());
        update_post_meta($importer_data->getId(), '_iwp_session', $session_id);

        Logger::clearRequestType();

        return $this->http->end_rest_success([
            'session' => $session_id
        ]);
    }

    public function run_import(\WP_REST_Request $request)
    {
        $this->http->set_stream_headers();

        Logger::setRequestType('rest::run_import');

        $session = $request->get_param('session');
        $user = uniqid('iwp', true);
        $id = intval($request->get_param('id'));
        Logger::write('run_import -session=' . $session, $id);

        $importer_data = $this->importer_manager->get_importer($id);
        Logger::write('run_import -import=start', $id);

        $state = $this->importer_manager->import($importer_data->getId(), $user, $session);
        $state['message'] = $this->generate_status_message($state);

        Logger::clearRequestType();

        return $this->http->end_rest_success($state);
    }

    public function pause_import(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $paused = $this->sanitize($request->get_param('paused'), 'paused');

        $importer_data = $this->importer_manager->get_importer($id);
        $user = uniqid('iwp', true);

        $state = ImporterState::wait_for_lock($importer_data->getId(), $user, function () use ($importer_data, $paused) {
            $state = ImporterState::get_state($importer_data->getId());


            if ($paused === 'no') {
                $state['status'] = 'running';
            } else {
                $state['status'] = 'paused';
            }

            ImporterState::set_state($importer_data->getId(), $state);
            return $state;
        });

        return $this->http->end_rest_success($state);
    }

    public function stop_import(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));

        $importer_data = $this->importer_manager->get_importer($id);

        $user = uniqid('iwp');

        $state = ImporterState::wait_for_lock($importer_data->getId(), $user, function () use ($importer_data) {
            $state = ImporterState::get_state($importer_data->getId());
            $state['status'] = 'cancelled';
            ImporterState::set_state($importer_data->getId(), $state);
            return $state;
        });

        return $this->http->end_rest_success($state);
    }

    /**
     * Get field options for current template
     *
     * @param \WP_REST_Request $request
     * @return void
     */
    public function get_template_field_options(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $field = $this->sanitize($request->get_param('field'), 'field');

        $importer_data = $this->importer_manager->get_importer($id);
        $template = $this->importer_manager->get_importer_template($id);

        return $this->http->end_rest_success($template->get_field_options($field, $importer_data));
    }

    public function get_logs(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $importer_data = $this->importer_manager->get_importer($id);
        $logs = $this->importer_manager->get_importer_logs($importer_data);
        return $this->http->end_rest_success(array_reverse($logs));
    }

    public function get_log(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $session = $this->sanitize($request->get_param('session'), 'session');
        $page = intval($request->get_param('page'));
        if ($page < 1) {
            $page = 1;
        }
        $importer_data = $this->importer_manager->get_importer($id);
        $log = $this->importer_manager->get_importer_log($importer_data, $session, $page, 500);
        $status = $this->importer_manager->get_importer_status_report($importer_data, $session);
        return $this->http->end_rest_success(['logs' => $log, 'status' => $status]);
    }

    public function get_debug_log(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $page = intval($request->get_param('page'));
        if ($page < 1) {
            $page = 1;
        }
        $importer_data = $this->importer_manager->get_importer($id);
        $log = $this->importer_manager->get_importer_debug_log($importer_data, $page, 100);

        $download = Logger::getLogFile($importer_data->getId(), true);
        return $this->http->end_rest_success(['log' => $log, 'download' => $download]);
    }

    public function save_settings(\WP_REST_Request $request)
    {
        $post_data = $request->get_body_params();
        $post_data = $this->sanitize($post_data);

        $defaults = $this->properties->_default_settings();

        $output = [];

        foreach ($defaults as $setting => $default) {
            if (isset($post_data[$setting])) {
                if ('bool' === $default['type']) {
                    // Handle Boolean
                    if (is_bool($post_data[$setting])) {
                        $output[$setting] = $post_data[$setting];
                    } elseif (in_array($post_data[$setting], ['true', 'false'])) {
                        $output[$setting] = $post_data[$setting] === 'true' ? true : false;
                    } else {
                        $output[$setting] = boolval($post_data[$setting]);
                    }
                } elseif ('number' === $default['type']) {
                    $output[$setting] = intval($post_data[$setting]);
                } else {
                    $output[$setting] = $post_data[$setting];
                }
            } else {
                $output[$setting] = $default['value'];
            }
        }

        update_site_option('iwp_settings', $output);

        return $this->http->end_rest_success($this->properties->get_settings());
    }

    public function get_settings(\WP_REST_Request $request = null)
    {
        $output = $this->properties->get_settings();
        return $this->http->end_rest_success($output);
    }

    public function get_template(\WP_REST_Request $request = null)
    {
        $importer_id = $request->get_param('id');
        $importer_model = $this->importer_manager->get_importer($importer_id);
        $importer_template = $importer_model->getTemplate();

        $templates = $this->importer_manager->get_templates();

        if (!isset($templates[$importer_template])) {
            return $this->http->end_rest_error("Invalid template \"" . $importer_template . "\"");
        }

        $template_id = $templates[$importer_template];
        $template_class = $this->template_manager->load_template($template_id);

        $output = [
            'id' => $template_id,
            'label' => $template_class->get_name(),
            'map' => $template_class->get_fields($importer_model),
            'settings' => $template_class->register_settings(),
            'options' => $template_class->register_options(),
        ];
        return $this->http->end_rest_success($output);
    }

    public function export_importers(\WP_REST_Request $request = null)
    {
        $importer_ids = $request->get_param('ids');

        $sql_ids = array_map(function ($v) {
            return "'" . esc_sql($v) . "'";
        }, $importer_ids);
        $sql_ids = implode(',', $sql_ids);

        $output = [];

        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;
        $results = $wpdb->get_results("SELECT post_title, post_content from {$wpdb->posts} WHERE post_type='" . IWP_POST_TYPE . "' AND ID IN (" . $sql_ids . ")", ARRAY_A);
        foreach ($results as $result) {

            $data = unserialize($result['post_content']);

            unset($data['file']['id']);
            unset($data['file']['settings']['count']);
            unset($data['file']['settings']['processed']);


            $row = [
                'name' => $result['post_title'],
                'data' => $data
            ];

            if ($this->importer_manager->is_debug()) {
                $row['raw'] = base64_encode($result['post_content']);
            }

            $output[] = $row;
        }

        $json = json_encode($output);

        header('Content-disposition: attachment; filename="ImportWP-export-' . date('Y-m-d') . '.json"');
        header('Content-type: "application/json"; charset="utf8"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($json));

        echo $json;
        die();
    }

    public function import_exporters(\WP_REST_Request $request = null)
    {
        $upload = $this->filesystem->upload_file($_FILES['file']);
        if (is_wp_error($upload)) {
            return $this->http->end_rest_error($upload->get_error_message());
        }

        $json_contents = file_get_contents($upload['dest']);
        @unlink($upload['dest']);

        $contents = json_decode($json_contents, true);
        $counter = 0;
        $errors = [];
        if (!empty($contents)) {
            foreach ($contents as $importer) {

                $result = wp_insert_post([
                    'post_type' => IWP_POST_TYPE,
                    'post_title' => $importer['name'],
                    'post_status'   => 'publish',
                    'post_author' => get_current_user_id(),
                    'post_content' => serialize($importer['data'])
                ], true);

                if (is_wp_error($result)) {
                    $errors[] =  $result->get_error_message();
                    continue;
                }

                $counter++;
            }
        }

        if (empty($counter)) {
            return $this->http->end_rest_error('No Importers found.');
        }

        return $this->http->end_rest_success("Import Complete, {$counter} Importer" . ($counter > 1 ? 's' : '') . '.');
    }

    public function save_exporter(\WP_REST_Request $request = null)
    {
        $post_data = $request->get_body_params();
        $id = isset($post_data['id']) ? intval($post_data['id']) : null;


        $exporter = new ExporterModel($id, $this->importer_manager->is_debug());

        if (isset($post_data['name'])) {
            $exporter->setName($post_data['name']);
        }

        if (isset($post_data['type'])) {
            $exporter->setType($post_data['type']);
        }

        if (isset($post_data['file_type'])) {
            $exporter->setFileType($post_data['file_type']);
        }

        if (isset($post_data['fields']) || $id > 0) {
            $exporter->setFields(isset($post_data['fields']) ? $post_data['fields'] : []);
        }

        if (isset($post_data['filters']) || $id > 0) {
            $exporter->setFilters(isset($post_data['filters']) ? $post_data['filters'] : []);
        }

        if (isset($post_data['unique_identifier'])) {
            $exporter->setUniqueIdentifier($post_data['unique_identifier']);
        }

        $result = $exporter->save();
        if (is_wp_error($result)) {
            return $this->http->end_rest_error($result->get_error_message());
        }
        return $this->http->end_rest_success($exporter->data());
    }

    public function get_exporters(\WP_REST_Request $request = null)
    {
        $exporters = $this->exporter_manager->get_exporters();
        $result = [];

        foreach ($exporters as $exporter) {
            $result[] = $exporter->data();
        }

        return $this->http->end_rest_success($result);
    }

    public function get_exporter(\WP_REST_Request $request = null)
    {
        $id = intval($request->get_param('id'));
        $exporter_data = $this->exporter_manager->get_exporter($id);
        $response = $exporter_data->data('public');
        return $this->http->end_rest_success($response);
    }

    public function delete_exporter(\WP_REST_Request $request = null)
    {
        $id = intval($request->get_param('id'));
        $this->exporter_manager->delete_exporter($id);
        return $this->http->end_rest_success(true);
    }

    public function run_exporter(\WP_REST_Request $request)
    {
        $this->http->set_stream_headers();

        $id       = intval($request->get_param('id'));
        $session = $request->get_param('session');

        $exporter_data = $this->exporter_manager->get_exporter($id);

        $status = $this->exporter_manager->export($exporter_data, $session);
        echo json_encode($status);
        // Logger::write(__CLASS__  . '::run_import -import=end -status=' . $status->get_status(), $id);

        // if (!empty($this->output_cache)) {
        //     echo $this->output_cache;
        //     $this->output_cache = '';
        // }

        // $output = $status->output();
        // $output['message'] = $this->generate_exporter_status_message($status);
        // echo json_encode($output) . "\n";
        die();
    }

    public function get_exporter_status(\WP_REST_Request $request)
    {
        $exporter_ids = $request->get_param('ids');
        $result = array();
        $query_data = array(
            'post_type'      => EWP_POST_TYPE,
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        if (!empty($exporter_ids)) {
            $query_data['post__in'] = $exporter_ids;
        }

        $query  = new \WP_Query($query_data);

        if (!empty($query->posts)) {
            foreach ($query->posts as $post_id) {

                $exporter = $this->exporter_manager->get_exporter($post_id);
                $status = $exporter->get_status();
                $status['id'] = $post_id;

                $result[] = $status;
            }
        }

        echo json_encode($result) . "\n";
        die();
    }
}
