<?php

namespace ImportWP\Common\Rest;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Http\Http;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Importer\ImporterStatus;
use ImportWP\Common\Importer\ImporterStatusManager;
use ImportWP\Common\Importer\Preview\CSVPreview;
use ImportWP\Common\Importer\Preview\XMLPreview;
use ImportWP\Common\Migration\Migrations;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Util\Logger;

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
     * @var ImporterStatusManager
     */
    protected $importer_status_manager;

    public function __construct(ImporterManager $importer_manager, ImporterStatusManager $importer_status_manager, Properties $properties, Http $http, Filesystem $filesystem)
    {
        $this->importer_manager = $importer_manager;
        $this->importer_status_manager = $importer_status_manager;
        $this->properties = $properties;
        $this->http = $http;
        $this->filesystem = $filesystem;
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

        register_rest_route($namespace, '/importer/(?P<id>\d+)/errors', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'get_errors'),
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

        $importer = new ImporterModel($id);

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
            if (isset($post_data['template_options'])) {
                foreach ($post_data['template_options'] as $key => $val) {
                    $importer->setSetting($key, $val);
                }
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

                if (in_array($value, ['true', 'false'], true)) {
                    if ($value === 'true') {
                        $value = true;
                    } else {
                        $value = false;
                    }
                }

                $importer->setSetting($matches['key'], $value);
            }
        }

        $parser = $importer->getParser();

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
                $importer->setPermission('remove', ['enabled' => $post_data['permissions']['remove'] === 'true']);
            }
        }

        $result = $importer->save();
        if (is_wp_error($result)) {
            return $this->http->end_rest_error($result->get_error_message());
        }
        return $this->http->end_rest_success($importer->data());
    }

    public function get_status(\WP_REST_Request $request)
    {
        // $this->http->set_stream_headers();
        ob_start();

        $importer_ids = $request->get_param('ids');

        $default_time = 5;
        $paused_time = 2;
        $importing_time = 1;

        $startedAt = time();
        do {

            $update_time = $default_time;

            if ((time() - $startedAt) > 10) {
                if (ob_get_contents()) {
                    ob_end_flush();
                }
                die();
            }

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

                $importer_model = new ImporterModel($importer_id);
                $status = new ImporterStatus($importer_model->getId(), $importer_model->getStatus());
                $output = $status->output();
                $output['id'] = $importer_id;

                if ($output['b'] !== 'complete' && in_array($output['s'], ['running', 'paused'])) {
                    if ($output['s'] === 'running') {
                        $update_time = $importing_time;
                    } elseif ($output['s'] === 'paused') {
                        $update_time = $paused_time;
                    }
                }

                $result[] = $output;
            }

            echo json_encode($result) . "\n";
            ob_flush();
            flush();

            sleep($update_time);
        } while (true);
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

        $importer = new ImporterModel($id);

        switch ($action) {
            case 'file_upload':
                $attachment_id = $this->importer_manager->upload_file($importer, $_FILES['file']);
                break;
            case 'file_remote':
                $source = $post_data['remote_url'];

                $filetype = $importer->getParser();
                if (is_null($filetype)) {
                    $filetype = $post_data['filetype'];
                }

                $attachment_id = $this->importer_manager->remote_file($importer, $source, $filetype);
                break;
            case 'file_local':
                $source = $post_data['local_url'];
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
                $importer->setFileSetting('count', $count);
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

            $record_index = $post_data['record'];
            if (is_null($record_index)) {
                $record_index = 0;
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
            } else {

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
            }
        } catch (\Exception $error) {
            return $this->http->end_rest_error($error->getMessage());
        }
    }

    public function get_preview(\WP_REST_Request $request)
    {
        // TODO: if no fields have been posted send all previews
        try {
            $id = intval($request->get_param('id'));
            $importer = $this->importer_manager->get_importer($id);
            $parser = $importer->getParser();

            $fields = $this->sanitize($request->get_body_params());
            if (is_null($fields) || empty($fields)) {
                $fields = $importer->getMap();
            }

            if ('xml' === $parser) {
                $result = $this->importer_manager->preview_xml_file($importer, $fields);
            } elseif ('csv' === $parser) {
                $result = $this->importer_manager->preview_csv_file($importer, $fields);
            }

            return $this->http->end_rest_success($result);
        } catch (\Exception $error) {
            return $this->http->end_rest_error($error->getMessage());
        }
    }

    /**
     * Trigger a new import.
     *
     * Generate new ImporterStatus.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function init_import(\WP_REST_Request $request)
    {
        // new
        try {
            $id = intval($request->get_param('id'));
            Logger::write(__CLASS__  . '::init_import -id=' . intval($id));

            $importer_data = $this->importer_manager->get_importer($id);

            Logger::write(__CLASS__  . '::init_import -id=' . intval($id) . ' -importer_data=' . print_r($importer_data, true));

            $status = $this->importer_status_manager->new($importer_data);

            Logger::write(__CLASS__  . '::init_import -id=' . intval($id) . ' -status=' . print_r($status, true));

            if (!$status) {
                Logger::write(__CLASS__  . '::init_import -id=' . intval($id) . ' -error=Error: Unable to generate new import session.');
                return $this->http->end_rest_error('Error: Unable to generate new import session.');
            }
        } catch (\Exception $e) {
            Logger::write(__CLASS__  . '::init_import -id=' . intval($id) . ' -error=Error Generating Importer Session: ' . $e->getMessage());
            return $this->http->end_rest_error('Error Generating Importer Session: ' . $e->getMessage());
        }


        // continue
        // $status = $this->importer_status_manager->get_importer_status($importer_data, $importer_data->getStatusId());
        Logger::write(__CLASS__  . '::init_import -id=' . intval($id) . ' -session=' . $status->get_session_id());

        return $this->http->end_rest_success([
            'session' => $status->get_session_id()
        ]);
    }

    public function run_import(\WP_REST_Request $request)
    {
        // $this->http->set_stream_headers();

        $session = $request->get_param('session');

        add_action('iwp/importer/status/save', array($this, 'render_import_status_update'));

        $id = intval($request->get_param('id'));
        Logger::write(__CLASS__  . '::run_import -id=' . intval($id) . ' -session=' . print_r($session, true));

        $importer_data = $this->importer_manager->get_importer($id);
        Logger::write(__CLASS__  . '::run_import -id=' . intval($id) . ' -importer_model=' . print_r($importer_data, true));

        $status = $this->importer_manager->import($importer_data, $session);
        Logger::write(__CLASS__  . '::run_import -id=' . intval($id) . ' -status=' . print_r($status, true));

        echo json_encode($status->output()) . "\n";
        die();
    }

    public function render_import_status_update(ImporterStatus $status)
    {
        echo json_encode($status->output()) . "\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    public function pause_import(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $session = $this->sanitize($request->get_param('session'), 'session');
        $paused = $this->sanitize($request->get_param('paused'), 'paused');

        $importer_data = $this->importer_manager->get_importer($id);
        $status = $this->importer_status_manager->get_importer_status($importer_data, $session);

        if ($paused === 'no') {
            $status->resume();
        } else {
            $status->pause();
        }
        return $this->http->end_rest_success($status->output());
    }

    public function stop_import(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $session = $this->sanitize($request->get_param('session'), 'session');

        $importer_data = $this->importer_manager->get_importer($id);
        $status = $this->importer_status_manager->get_importer_status($importer_data, $session);
        if (!$status) {
            return $this->http->end_rest_error('Unable to get importer status in stop_import.');
        }
        $status->stop();

        return $this->http->end_rest_success($status->output());
    }

    /**
     * Get list of importer errors for the current session
     *
     * @param \WP_REST_Request $request
     * @return json
     */
    public function get_errors(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $session = $this->sanitize($request->get_param('session'), 'session');
        $importer_data = $this->importer_manager->get_importer($id);
        $status = $this->importer_status_manager->get_importer_status($importer_data, $session);
        return $this->http->end_rest_success($status->get_errors());
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
        $logs = $this->importer_status_manager->get_importer_logs($importer_data);
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
        $log = $this->importer_status_manager->get_importer_log($importer_data, $session, $page, 100);
        $status = $this->importer_status_manager->get_importer_status_report($importer_data, $session);
        return $this->http->end_rest_success(['logs' => $log, 'status' => $status]);
    }
}
