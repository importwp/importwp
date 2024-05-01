<?php

namespace ImportWP\Common\Rest;

use ImportWP\Common\Exporter\ExporterManager;
use ImportWP\Common\Exporter\State\ExporterState;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Filesystem\ZipArchive;
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

        register_rest_route($namespace, '/compatibility', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'save_compatibility'),
                'permission_callback' => array($this, 'get_permission')
            ),
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_compatibility'),
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

        register_rest_route($namespace, '/importer/(?P<id>\d+)/template_unique_identifiers', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_importer_unique_identifier_options'),
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

        register_rest_route($namespace, '/exporter/(?P<id>\d+)/init', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'init_export'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/exporter/(?P<id>\d+)/download-config', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'download_exporter_config'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));

        register_rest_route($namespace, '/importer/read-config', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'read_exporter_config'),
                'permission_callback' => array($this, 'get_permission')
            )
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
            return new \WP_Error('rest_forbidden', __('You do not have permissions.', 'jc-importer'), array('status' => 401));
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
                'message' => $tmp_writable ? '' : sprintf(__('WordPress is unable to write to directory: %s, make sure this directory is writable.', 'jc-importer'), $this->filesystem->get_temp_directory()),
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
            'zip_archive' => [
                'status' => ZipArchive::has_requirements_met() ? 'yes' : 'no'
            ]
        ];
        return $this->http->end_rest_success($result);
    }

    public function migrate(\WP_REST_Request $request)
    {
        $migration = new Migrations();
        $result = $migration->migrate();

        return $this->http->end_rest_success(__("Migration Complete.", 'jc-importer'));
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
        Logger::setRequestType('get_importer');

        $id = intval($request->get_param('id'));
        $importer_data = $this->importer_manager->get_importer($id);
        $response = $importer_data->data('public');
        return $this->http->end_rest_success($response);
    }

    public function save_importer(\WP_REST_Request $request)
    {
        Logger::setRequestType('save_importer');

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
            $importer->setDatasourceSetting('local_url', wp_normalize_path($post_data['local_url']));
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

            // setup default enabled fields
            $default_enabled_fields = $template->get_default_enabled_fields();
            foreach ($default_enabled_fields as $default_enabled_field) {
                $importer->setEnabled($default_enabled_field);
            }

            foreach ($template_options as $key => $val) {
                $importer->setSetting($key, $val);
            }
        }

        if (isset($post_data['name'])) {
            $importer->setName($post_data['name']);
        }

        if (is_null($id)) {

            // NOTE: New importer, should we generate field map from exporter.
            if (isset($post_data['setup_type']) && in_array($post_data['setup_type'], ['upload', 'generate'])) {

                $setup_type = $post_data['setup_type'] === 'upload' ? 'upload' : 'generate';
                $file_type = null;

                if ($setup_type === 'upload') {
                    $config = json_decode($post_data['exporter_config_file'], true);
                    $file_type = $config['data']['file_type'];
                    $fields = $config['fields'];
                    $formatted_fields = $config['formatted_fields'];
                    $file_settings = $config['data']['file_settings'];
                } else {

                    /**
                     * @var \ImportWP\Common\Model\ExporterModel $exporter_data
                     */
                    $exporter_data = $this->exporter_manager->get_exporter($post_data['exporter']);
                    $fields = $this->exporter_manager->get_importer_map_fields($exporter_data);
                    $mapper = $this->exporter_manager->get_exporter_mapper($exporter_data);
                    $formatted_fields = $mapper->get_fields();
                    $file_type = $exporter_data->getFileType();
                    $file_settings = $exporter_data->getFileSettings();
                }

                if (is_null($file_type) || !in_array($file_type, ['xml', 'csv'])) {
                    return $this->http->end_rest_error('Invalid exporter file type');
                }

                $importer->setParser($file_type);

                $headings = [];
                switch ($file_type) {
                    case 'csv':
                        $headings = array_reduce($fields, function ($carry, $item) {
                            $carry[] = $item['selection'];
                            return $carry;
                        }, []);
                        $headings = array_map('trim', $headings);

                        if (isset($file_settings['delimiter']) && !empty($file_settings['delimiter']) && strlen($file_settings['delimiter']) === 1) {
                            $importer->setFileSetting('delimiter', $file_settings['delimiter']);
                        }
                        if (isset($file_settings['enclosure']) && !empty($file_settings['enclosure']) && strlen($file_settings['enclosure']) === 1) {
                            $importer->setFileSetting('enclosure', $file_settings['enclosure']);
                        }
                        if (isset($file_settings['escape']) && !empty($file_settings['escape']) && strlen($file_settings['escape']) === 1) {
                            $importer->setFileSetting('escape', $file_settings['escape']);
                        }

                        break;
                    case 'xml':

                        $main = false;
                        foreach ($fields as $field) {
                            if ($field['selection'] === 'main' && ($field['loop'] === true || $field['loop'] === "true")) {
                                $main = $field;
                                break;
                            }
                        }

                        if (!$main) {
                            return $this->http->end_rest_error("XML exporter is missing main loop.");
                        }

                        // generate base_path
                        $post_data['file_settings_base_path'] = '/' . implode('/', array_reverse(array_filter($this->generate_base_path($main, $fields))));

                        $allowed = [$main['id']];

                        $current_section = null;
                        $current_section_ancestors = [];
                        $current_section_map = [];

                        foreach ($fields as $field) {

                            if (in_array($field['parent'], $allowed)) {

                                $field_map = '/' . implode('/', array_reverse(array_filter($this->generate_base_path($field, $fields, [], $main['id']))));
                                $headings[$field_map] = $field['selection'];

                                if ($field['loop'] === true || $field['loop'] === 'true') {

                                    // we need to convert all sub fields to tax_category.id ....
                                    $current_section = $field['selection'];
                                    $current_section_ancestors = [$field['id']];
                                } else {

                                    if (!is_null($current_section) && in_array($field['parent'], $current_section_ancestors)) {

                                        $current_section_ancestors[] = $field['id'];
                                        $current_section_map[$field_map] = $current_section . '.' . $field['selection'];
                                    } else {
                                        $headings = $this->complete_section_map($headings, $current_section_map, $current_section, $formatted_fields);
                                        $current_section = null;
                                        $current_section_ancestors = [];
                                        $current_section_map = [];
                                    }
                                }

                                if (!in_array($field['id'], $allowed)) {
                                    $allowed[] = $field['id'];
                                }
                            }
                        }

                        $headings = $this->complete_section_map($headings, $current_section_map, $current_section, $formatted_fields);

                        $tmp = [];
                        foreach ($headings as $map => $heading) {
                            $tmp[$map] = $heading;
                        }

                        $headings = $tmp;
                        break;
                }

                if (!empty($headings)) {
                    $template = $this->importer_manager->get_importer_template($importer);
                    $field_map = $template->generate_field_map($headings, $importer);
                    $field_map = apply_filters('iwp/importer/generate_field_map', $field_map, $headings, $importer);

                    $post_data['map'] = $field_map['map'];
                    $post_data['enabled'] = $field_map['enabled'];
                }
            }
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

        if (isset($post_data['setting_import_method']) && $post_data['setting_import_method'] === 'background') {
            delete_post_meta($importer->getId(), '_iwp_cron_scheduled');
            delete_post_meta($importer->getId(), '_iwp_cron_last_ran');
        }

        $parser = $importer->getParser();

        if (isset($post_data['file_settings_encoding'])) {
            $importer->setFileSetting('file_encoding', $post_data['file_settings_encoding']);
        }

        if ($parser === 'csv') {

            $clear_config = false;
            if (isset($post_data['file_settings_delimiter'])) {

                $old_delimiter = $importer->getFileSetting('delimiter');
                if ($old_delimiter !== $post_data['file_settings_delimiter']) {
                    $clear_config = true;
                }
                $importer->setFileSetting('delimiter', $post_data['file_settings_delimiter']);
            }
            if (isset($post_data['file_settings_enclosure'])) {
                $old_enclosure = $importer->getFileSetting('enclosure');
                if ($old_enclosure !== $post_data['file_settings_enclosure']) {
                    $clear_config = true;
                }
                $importer->setFileSetting('enclosure', $post_data['file_settings_enclosure']);
            }

            if (isset($post_data['file_settings_escape'])) {
                $old_escape = $importer->getFileSetting('escape');
                if ($old_escape !== $post_data['file_settings_escape']) {
                    $clear_config = true;
                }
                $importer->setFileSetting('escape', $post_data['file_settings_escape']);
            }


            if (isset($post_data['file_settings_show_headings'])) {
                $importer->setFileSetting('show_headings', $post_data['file_settings_show_headings'] === 'true' || $post_data['file_settings_show_headings'] === true ? true : false);
            }

            if ($clear_config) {
                $this->importer_manager->clear_config_files($importer->getId(), true);
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

    public function generate_base_path($field, $fields, $output = [], $stop = 0)
    {
        $output[] = isset($field['label']) && !empty($field['label']) ? $field['label'] : $field['selection'];

        if ($field['parent'] !== $stop) {

            foreach ($fields as $sub_field) {
                if ($sub_field['id'] == $field['parent']) {

                    return $this->generate_base_path($sub_field, $fields, $output, $stop);
                }
            }
        }
        return $output;
    }

    public function complete_section_map($headings, $current_section_map, $current_section, $fields)
    {
        if (is_null($current_section)) {
            return $headings;
        }

        if ($current_section === 'custom_fields') {
            // $headings['/test/@value'] = 'custom_fields.{/test/@key}';

            $meta_key = false;
            $meta_val = false;

            if (count($current_section_map) == 2) {
                foreach ($current_section_map as $row_map => $row_value) {
                    if ($row_value == 'custom_fields.meta_key') {
                        $meta_key = $row_map;
                    } elseif ($row_value == 'custom_fields.meta_value') {
                        $meta_val = $row_map;
                    }
                }

                if ($meta_key && $meta_val) {

                    // Get full list of custom fields, that can be then be used to generate a full list
                    // /custom_fields_wrapper/custom_fields[meta_key="_edit_lock"]/meta_key
                    // /custom_fields_wrapper/custom_fields[meta_key="_edit_lock"]/meta_value

                    // Compare the two strings and get the matching parts
                    $meta_key_parts = array_values(array_filter(explode('/', $meta_key)));
                    $meta_val_parts = array_values(array_filter(explode('/', $meta_val)));

                    for ($i = 0; $i < count($meta_key_parts); $i++) {
                        if ($meta_key_parts[$i] !== $meta_val_parts[$i]) {
                            break;
                        }
                    }

                    $start = array_slice($meta_key_parts, 0, $i);
                    $end_key = array_slice($meta_key_parts, $i);
                    $end_val = array_slice($meta_val_parts, $i);

                    $start_path = implode('/', $start);
                    $end_key_path = implode('/', $end_key);
                    $end_val_path = implode('/', $end_val);

                    $custom_field_key_list = $fields['children']['custom_fields']['fields'];
                    foreach ($custom_field_key_list as $custom_field_key) {

                        // $tmp_field_key = '{/' . $start_path . sprintf('[%s="%s"]', $end_key_path, $custom_field_key) . '/' . $end_key_path . '}';
                        $tmp_field_val = '/' . $start_path . sprintf('[%s="%s"]', $end_key_path, $custom_field_key) . '/' . $end_val_path;

                        $headings[$tmp_field_val] = sprintf('custom_fields.%s', $custom_field_key);
                    }

                    return $headings;
                }
            }

            return array_merge($headings, $current_section_map);
        }

        return array_merge($headings, $current_section_map);
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

        Logger::disable();

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

            $importer_model = $this->importer_manager->get_importer($importer_id);
            $config = $this->importer_manager->get_config($importer_model);

            $output = ImporterState::get_state($importer_id);
            $output['version'] = 2;
            $output['message'] = $this->generate_status_message($output);
            $output['importer'] = $importer_id;
            $output['process'] = intval($config->get('process'));

            $result[] = $this->event_handler->run('iwp/importer/status/output', [$output, $importer_model]);
        }

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
                    $output .= __('Import cancelled', 'jc-importer');
                    break;
                case 'complete':
                    $output .= __('Import complete', 'jc-importer');
                    break;
                case 'error':
                    $output = $data['message'];
                    return $output;
                    break;
            }
        }

        if (isset($data['status']) && ($data['status'] === 'running' || $data['status'] === 'processing' || $data['status'] === 'timeout') && isset($data['section'])) {
            switch ($data['section']) {
                case 'import':
                case 'delete':
                    $progress = $data['progress'][$data['section']]['current_row'] . ' / ' . ($data['progress'][$data['section']]['end'] - $data['progress'][$data['section']]['start']);
                    $output = sprintf($data['section'] === 'import' ? __('Importing: %s', 'jc-importer') : __('Deleting: %s', 'jc-importer'), $progress);
                    break;
            }
        }

        if (isset($data['stats'])) {
            if ($data['stats']['inserts'] > 0) {
                $output .= sprintf(__(', Inserts: %d', 'jc-importer'), $data['stats']['inserts']);
            }
            if ($data['stats']['updates'] > 0) {
                $output .= sprintf(__(', Updates: %d', 'jc-importer'), $data['stats']['updates']);
            }
            if ($data['stats']['deletes'] > 0) {
                $output .= sprintf(__(', Deletes: %d', 'jc-importer'), $data['stats']['deletes']);
            }
            if ($data['stats']['skips'] > 0) {
                $output .= sprintf(__(', Skips: %d', 'jc-importer'), $data['stats']['skips']);
            }
            if ($data['stats']['errors'] > 0) {
                $output .= sprintf(__(', Errors: %d', 'jc-importer'), $data['stats']['errors']);
            }
        }

        if (isset($data['timestamp'], $data['updated'])) {
            /**
             * @var Util $util
             */
            $util = Container::getInstance()->get('util');
            $output .= sprintf(__(', Run Time: %s', 'jc-importer'), $util->format_time($data['updated'] - $data['timestamp']));
        } elseif (isset($data['duration'])) {
            /**
             * @var Util $util
             */
            $util = Container::getInstance()->get('util');
            $output .= sprintf(__(', Run Time: %s', 'jc-importer'), $util->format_time($data['duration']));
        }

        return $output;
    }

    public function delete_importer(\WP_REST_Request $request)
    {
        Logger::setRequestType('delete_importer');

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

                $filetype = $importer->getParser();
                if (is_null($filetype)) {
                    $filetype = $post_data['filetype'];
                }

                $attachment_id = $this->importer_manager->local_file($importer, $source, $filetype);
                break;
            default:
                $attachment_id = new \WP_Error('IWP_RM_1', __('Invalid form action', 'jc-importer'));
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
                return $this->http->end_rest_error(__('Invalid importer', 'jc-importer'));
            }

            $this->importer_manager->clear_config_files($id, true);
            $parser = $importer->getParser();

            if ('xml' === $parser) {

                $nodes = $this->importer_manager->process_xml_file($id, true);
                $importer->setFileSetting('nodes', $nodes);
            } elseif ('csv' === $parser) {

                $delimiter = isset($post_data['delimiter']) ? $post_data['delimiter'] : null;
                if (is_null($delimiter)) {
                    $delimiter = $importer->getFileSetting('delimiter', ',');
                }

                $enclosure = isset($post_data['enclosure']) ? $post_data['enclosure'] : null;
                if (is_null($enclosure)) {
                    $enclosure = $importer->getFileSetting('enclosure', '"');
                }

                $escape = isset($post_data['escape']) ? $post_data['escape'] : null;
                if (is_null($escape)) {
                    $escape = $importer->getFileSetting('escape', '\\');
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
                throw new \Exception(sprintf(__("Unable to load preview, %s", 'jc-importer'), $import_file_exists->get_error_message()));
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

                $clear_config = false;
                if (!is_null($post_data['delimiter']) && $post_data['delimiter'] !== $file->getDelimiter()) {
                    $clear_config = true;
                } elseif (!is_null($post_data['enclosure']) && $post_data['enclosure'] !== $file->getEnclosure()) {
                    $clear_config = true;
                } elseif (!is_null($post_data['escape']) && $post_data['escape'] !== $file->getEscape()) {
                    $clear_config = true;
                }

                if ($clear_config) {
                    $this->importer_manager->clear_config_files($importer->getId(), true);
                    $config = $this->importer_manager->get_config($importer, true);
                    $file = $this->importer_manager->get_csv_file($importer, $config);
                }

                $delimiter = $post_data['delimiter'];
                if (!is_null($delimiter)) {
                    $file->setDelimiter($delimiter);
                }

                $enclosure = $post_data['enclosure'];
                if (!is_null($enclosure)) {
                    $file->setEnclosure($enclosure);
                }

                $escape = $post_data['escape'];
                if (!is_null($escape)) {
                    $file->setEscape($escape);
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
                throw new \Exception(sprintf(__("Unable to load preview, %s", 'jc-importer'), $import_file_exists->get_error_message()));
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

        if ('background' === $importer_data->getSetting('import_method')) {
            update_post_meta($importer_data->getId(), '_iwp_cron_scheduled', current_time('timestamp'));
            delete_post_meta($importer_data->getId(), '_iwp_cron_last_ran');
        }

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

        $state = $this->importer_manager->pause_import($id, $paused);

        return $this->http->end_rest_success($state);
    }

    public function stop_import(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));

        $state = $this->importer_manager->stop_import($id);

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
        try {

            $id = intval($request->get_param('id'));
            $field = $this->sanitize($request->get_param('field'), 'field');

            $importer_data = $this->importer_manager->get_importer($id);
            $template = $this->importer_manager->get_importer_template($id);

            return $this->http->end_rest_success($template->get_field_options($field, $importer_data));
        } catch (\Exception $e) {
            return $this->http->end_rest_error($e->getMessage());
        }
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
        Logger::setRequestType('get_template');

        $importer_id = $request->get_param('id');
        $importer_model = $this->importer_manager->get_importer($importer_id);
        $importer_template = $importer_model->getTemplate();

        $templates = $this->importer_manager->get_templates();

        if (!isset($templates[$importer_template])) {
            return $this->http->end_rest_error(sprintf(__("Invalid template \"%s\"", 'jc-importer'), $importer_template));
        }

        $template_id = $templates[$importer_template];
        $template_class = $this->template_manager->load_template($template_id);

        $output = [
            'id' => $template_id,
            'label' => $template_class->get_name(),
            'map' => $template_class->get_fields($importer_model),
            'settings' => $template_class->register_settings(),
            'options' => $template_class->register_options(),
            'permission_fields' => apply_filters('iwp/template/permission_fields', $template_class->get_permission_fields($importer_model), $template_class)
        ];
        return $this->http->end_rest_success($output);
    }

    public function export_importers(\WP_REST_Request $request = null)
    {
        Logger::setRequestType('export_importers');

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

    /**
     * Download exporter config json file.
     * 
     * File is used when creating an importer to auto generate field mapping.
     * 
     * @param WP_REST_Request|null $request 
     * @return never 
     */
    public function download_exporter_config(\WP_REST_Request $request = null)
    {
        $exporter_id = $request->get_param('id');
        $exporter_data = $this->exporter_manager->get_exporter($exporter_id);
        $mapper = $this->exporter_manager->get_exporter_mapper($exporter_data);
        $fields = $mapper->get_fields();



        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;
        $result = $wpdb->get_row("SELECT post_title, post_content from {$wpdb->posts} WHERE post_type='" . EWP_POST_TYPE . "' AND ID=" . intval($exporter_id), ARRAY_A);

        $output = [
            'name' => $result['post_title'],
            'data' => unserialize($result['post_content']),
            'fields' => $this->exporter_manager->get_importer_map_fields($exporter_data),
            'formatted_fields' =>  $fields
        ];

        $json = json_encode($output);

        header('Content-disposition: attachment; filename="ImportWP-exporter-' . $exporter_id . '-' . date('Y-m-d') . '.json"');
        header('Content-type: "application/json"; charset="utf8"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($json));

        echo $json;
        die();
    }

    public function read_exporter_config(\WP_REST_Request $request = null)
    {
        $upload = $this->filesystem->upload_file($_FILES['file']);
        if (is_wp_error($upload)) {
            return $this->http->end_rest_error($upload->get_error_message());
        }

        $json_contents = file_get_contents($upload['dest']);
        @unlink($upload['dest']);

        $contents = json_decode($json_contents, true);

        if (!isset($contents['name'], $contents['fields'], $contents['data'], $contents['data']['file_type'])) {
            return $this->http->end_rest_error("Invalid exporter config file.");
        }

        if (!in_array($contents['data']['file_type'], ['xml', 'csv'])) {
            return $this->http->end_rest_error("Exporter file type is not supported.");
        }

        return $this->http->end_rest_success(['exporter' => $contents]);
    }

    public function get_compatibility_settings()
    {
        $output = [];

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $blacklisted = (array)get_option('iwp_compat_blacklist', []);
        $whitelisted = apply_filters('iwp/compat/whitelist', [
            'jc-importer/jc-importer.php'
        ]);

        $all_plugins = get_plugins();
        foreach ($all_plugins as $plugin_id => $plugin_data) {



            if (preg_match('/^importwp-/', $plugin_id, $matches) === 1 || in_array($plugin_id, $whitelisted)) {
                continue;
            }

            $output[$plugin_id] = [
                'id' => $plugin_id,
                'name' => $plugin_data['Name'],
                'enabled' => in_array($plugin_id, $blacklisted) ? 'yes' : 'no'
            ];
        }

        return $output;
    }

    public function get_compatibility(\WP_REST_Request $request = null)
    {
        $output = $this->get_compatibility_settings();

        return $this->http->end_rest_success($output);
    }

    public function save_compatibility(\WP_REST_Request $request)
    {
        $post_data = $request->get_body_params();
        $post_data = $this->sanitize($post_data);

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_keys = array_keys(get_plugins());


        $tmp = [];

        if (isset($post_data['plugins'])) {

            foreach ($post_data['plugins'] as $plugin_id) {
                if (!in_array($plugin_id, $plugin_keys)) {
                    continue;
                }

                $tmp[] = "" . $plugin_id;
            }
        }

        update_option('iwp_compat_blacklist', $tmp);

        $output = $this->get_compatibility_settings();
        return $this->http->end_rest_success($output);
    }

    public function import_exporters(\WP_REST_Request $request = null)
    {
        Logger::setRequestType('import_exporters');

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
                    'post_content' => wp_slash(serialize($importer['data']))
                ], true);

                if (is_wp_error($result)) {
                    $errors[] =  $result->get_error_message();
                    continue;
                }

                $counter++;
            }
        }

        if (empty($counter)) {
            return $this->http->end_rest_error(__('No Importers found.', 'jc-importer'));
        }

        return $this->http->end_rest_success(sprintf(_n("Import Complete, %d Importer", "Import Complete, %d Importers", $counter, 'jc-importer'), $counter));
    }

    public function save_exporter(\WP_REST_Request $request = null)
    {
        Logger::setRequestType('save_exporter');

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

        if (isset($post_data['file_settings'])) {
            $exporter->setFileSettings($post_data['file_settings']);
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

        if (isset($post_data['export_method'])) {
            $exporter->setExportMethod($post_data['export_method']);
        }

        if (isset($post_data['cron'])) {

            // TODO: Save cron settings

            $cron = $this->sanitize_setting($post_data['cron']);
            $exporter->setCron($cron);
        }

        $result = $exporter->save();
        if (is_wp_error($result)) {
            return $this->http->end_rest_error($result->get_error_message());
        }
        return $this->http->end_rest_success($exporter->data());
    }

    public function get_exporters(\WP_REST_Request $request = null)
    {
        Logger::setRequestType('get_exporters');

        $exporters = $this->exporter_manager->get_exporters();
        $result = [];

        foreach ($exporters as $exporter) {
            $result[] = $exporter->data();
        }

        return $this->http->end_rest_success($result);
    }

    public function get_exporter(\WP_REST_Request $request = null)
    {
        Logger::setRequestType('get_exporter');

        $id = intval($request->get_param('id'));
        $exporter_data = $this->exporter_manager->get_exporter($id);
        $response = $exporter_data->data('public');
        return $this->http->end_rest_success($response);
    }

    public function delete_exporter(\WP_REST_Request $request = null)
    {
        Logger::setRequestType('delete_exporter');

        $id = intval($request->get_param('id'));
        $this->exporter_manager->delete_exporter($id);
        return $this->http->end_rest_success(true);
    }

    /**
     * Trigger a new export.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function init_export(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));

        Logger::setRequestType('rest::init_export');

        Logger::write('init_import -start', $id);

        $exporter_data = $this->exporter_manager->get_exporter($id);

        // clear existing
        ExporterState::clear_options($exporter_data->getId());

        // This is used for storing version on imported records
        $session_id = md5($exporter_data->getId() . time());
        update_post_meta($exporter_data->getId(), '_iwp_session', $session_id);

        Logger::clearRequestType();

        return $this->http->end_rest_success([
            'session' => $session_id
        ]);
    }

    public function run_exporter(\WP_REST_Request $request)
    {
        Logger::setRequestType('run_exporter');

        $this->http->set_stream_headers();

        $id       = intval($request->get_param('id'));
        $session = $request->get_param('session');
        $user = uniqid('iwp', true);

        $exporter_data = $this->exporter_manager->get_exporter($id);

        $status = $this->exporter_manager->export($exporter_data, $user, $session);
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
        Logger::setRequestType('get_exporter_status');

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
                $status = ExporterState::get_state($post_id);
                $status['exporter'] = $post_id;
                $status['version'] = 2;

                if (isset($status['progress'])) {
                    $current = $status['progress']['export']['current_row'];
                    $end = $status['progress']['export']['end'] - $status['progress']['export']['start'];
                    $status['progress'] = $end > 0 ? ($current / $end) * 100 : 0;
                } else {
                    $status['progress'] = 0;
                }


                $output = '';

                if (isset($status['status'])) {
                    switch ($status['status']) {
                        case 'cancelled':
                            $output .= 'Export cancelled';
                            break;
                        case 'complete':
                            $output .= 'Export complete';
                            break;
                        case 'processing':
                        case 'timeout':
                        case 'running':
                            $output .= 'Exporting: ' . $current . '/' . $end;
                            break;
                    }
                }

                $status['message'] = $output;

                $result[] = $this->event_handler->run('iwp/exporter/status/output', [$status, $exporter]);
            }
        }

        echo json_encode($result) . "\n";
        die();
    }

    public function get_importer_unique_identifier_options(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $importer_model = $this->importer_manager->get_importer($id);
        if (!$importer_model) {
            return $this->http->end_rest_error(['msg' => 'Invalid importer']);
        }

        // Get default template options
        $template = $this->importer_manager->get_template($importer_model->getTemplate());
        if (is_wp_error($template)) {
            return $this->http->end_rest_error($template->get_error_message());
        }

        $template_class = $this->template_manager->load_template($template);
        $unique_fields = $this->template_manager->get_template_unique_fields($template_class);
        $options = $template_class->get_unique_identifier_options($importer_model, $unique_fields);

        // Only add in unqiue fields if they have not been found.
        // Allowing for old templates to continue to list unique identifiers.
        foreach ($unique_fields as $field_id) {
            if (!isset($options[$field_id])) {
                $options[$field_id] = [
                    'value' => $field_id,
                    'label' => $field_id,
                    'uid' => true,
                    'active' => true,
                ];
            }
        }

        $options = array_filter($options, function ($item) {
            return $item['active'];
        });

        return $this->http->end_rest_success(['options' => array_values($options), 'unique_fields' => array_values($unique_fields)]);
    }
}
