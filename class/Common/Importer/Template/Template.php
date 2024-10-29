<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\Mapper\AbstractMapper;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;
use ImportWP\EventHandler;

class Template extends AbstractTemplate
{
    /**
     * Template name
     *
     * @var string
     */
    protected $name;
    /**
     * Mapper name
     *
     * @var string
     */
    protected $mapper;
    /**
     * @var ImporterModel
     */
    protected $importer;

    /**
     * Registered data groups
     *
     * @var string[]
     */
    protected $groups;

    /**
     * List of field option callbacks
     * 
     * @var array
     */
    protected $field_options = [];

    protected $default_template_options = [];

    /**
     * 
     * List of optional fields that are enabled by default
     * @var string[]
     */
    protected $default_enabled_fields = [];

    /**
     * @var \WP_Error[] $errors
     */
    protected $errors = [];

    /**
     * @var EventHandler $event_handler
     */
    private $event_handler;

    /**
     * @var boolean
     */
    private $featured_set = false;

    public function __construct(EventHandler $event_handler)
    {
        $this->event_handler = $event_handler;
        $this->event_handler->run('template.init', [$this]);

        $this->groups = $this->event_handler->run('template.data_groups', [$this->groups, $this]);

        // Register field options callbacks
        $this->field_options = [
            '*.row_base' => [$this, 'get_row_base']
        ];
        $this->field_options = $this->event_handler->run('template.field_option_callbacks', [$this->field_options, $this]);
    }

    /**
     * Get xml record base
     *
     * @param ImporterModel $importer_model
     * @return array
     */
    public function get_row_base($importer_model)
    {
        /**
         * @var ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');
        $tmp = false;
        $results = [];

        if ($importer_model->getParser() !== 'xml') {
            return $results;
        }

        $base_path = $importer_model->getFileSetting('base_path');
        $nodes = $importer_model->getFileSetting('nodes');

        $config = $importer_manager->get_config($importer_model->getId(), $tmp);

        $filePath = $importer_model->getFile();
        $file = new XMLFile($filePath, $config);
        $file->setRecordPath($base_path);
        $nodes = $file->get_node_list();

        foreach ($nodes as $node) {
            if (strpos($node, $base_path) !== 0) {
                continue;
            }

            $sub_node = substr($node, strlen($base_path));

            $results[] = [
                'value' => $sub_node,
                'label' => $sub_node
            ];
        }

        return $results;
    }

    public function get_name()
    {
        return $this->name;
    }

    public function get_mapper()
    {
        return $this->mapper;
    }

    public function get_importer()
    {
        return $this->importer;
    }

    public function get_fields(ImporterModel $importer = null)
    {
        $fields = $this->register();
        $fields = $this->event_handler->run('template.fields', [$fields, $this, $importer]);
        return $fields;
    }

    public function register()
    {
        return [];
    }

    public function register_hooks(ImporterModel $importer)
    {
        $this->importer = $importer;

        add_filter('iwp/importer/before_mapper', array($this, 'pre_process'));
        add_filter('iwp/status/record_inserted', [$this, 'display_record_info'], 10, 3);
        add_filter('iwp/status/record_updated', [$this, 'display_record_info'], 10, 3);
    }

    public function unregister_hooks()
    {
        remove_filter('iwp/importer/before_mapper', array($this, 'pre_process'));
        remove_filter('iwp/status/record_inserted', [$this, 'display_record_info'], 10, 3);
        remove_filter('iwp/status/record_updated', [$this, 'display_record_info'], 10, 3);
    }

    /**
     * @param string $message
     * @param int $id
     * @param ParsedData $data
     * @return $string
     */
    public function display_record_info($message, $id, $data)
    {

        $fields = $data->getData();
        if (!empty($fields)) {
            $message .= ' (' . implode(', ', array_keys($fields)) . ')';
        }

        if (!empty($this->errors)) {
            $errors = [];
            foreach ($this->errors as $error) {
                $errors[] = $error->get_error_message();
            }

            $message .= ', Errors: (' . implode(', ', $errors) . ')';
        }

        $this->errors = [];

        return $message;
    }

    public function register_group($label, $key, $fields, $args = [])
    {
        if (isset($args['row_base']) && true === $args['row_base']) {

            $fields = array_merge(
                [$this->register_field(__('Repeater Node', 'jc-importer'), 'row_base', [
                    'type' => 'text',
                    'options' => 'callback',
                    'tooltip' => __('Select the path to the parent node containing values for this record, The preview when selecting data for each record should show a single record. Please note changing this will require updating references in this record.', 'jc-importer')
                ])],
                $fields
            );
        }

        $tmp = [
            'id' => $key,
            'heading' => $label,
            'type' => isset($args['type']) ? $args['type'] : 'group',
            'fields' => $fields,
        ];

        if (isset($args['link']) && !empty($args['link'])) {
            $tmp['link'] = $args['link'];
        }

        if (isset($args['condition']) && !empty($args['condition'])) {
            $tmp['condition'] = $args['condition'];
        }

        // Allow for setting fields to be registered for the group
        // currently only supporting toggle fields.
        if (isset($args['settings']) && !empty($args['settings'])) {
            $tmp['settings'] = $args['settings'];
        }

        return $tmp;
    }

    public function register_core_field($label, $key, $args = [])
    {
        $args['core'] = true;
        return $this->register_field($label, $key, $args);
    }

    public function register_field($label, $key, $args = [])
    {
        $data = [
            'id' => $key,
            'label' => $label,
            'type' => isset($args['type']) ? $args['type'] : 'field',
            'core' => isset($args['core']) ? $args['core'] : false,
            'tooltip' => isset($args['tooltip']) ? $args['tooltip'] : false
        ];

        if (isset($args['options'])) {
            $data['options'] = $args['options'];
        }

        if (isset($args['default'])) {
            $data['default'] = $args['default'];
        }

        if (isset($args['condition'])) {
            $data['condition'] = $args['condition'];
        }

        return $data;
    }

    public function _register_attachment_setting_fields($display_conditions = [], $extra_fields = [], $disabled_fields = [])
    {

        $fields = [];

        if (!in_array('_featured', $disabled_fields)) {
            $fields[] = $this->register_field(__('Is Featured?', 'jc-importer'), '_featured', [
                'default' => 'no',
                'options' => [
                    ['value' => 'no', 'label' => __('No', 'jc-importer')],
                    ['value' => 'yes', 'label' => __('Yes', 'jc-importer')],
                ],
                'tooltip' => __('Is the attachment the featured image for the current post.', 'jc-importer')
            ]);
        }

        $fields = array_merge($fields, [
            $this->register_field(__('Download', 'jc-importer'), '_download', [
                'default' => 'remote',
                'options' => [
                    ['value' => 'remote', 'label' => __('Remote URL', 'jc-importer')],
                    ['value' => 'ftp', 'label' => __('FTP', 'jc-importer')],
                    ['value' => 'local', 'label' => __('Local Filesystem', 'jc-importer')],
                    ['value' => 'media', 'label' => __('Media Library', 'jc-importer')],
                ],
                'tooltip' => __('Select how the attachment is being downloaded.', 'jc-importer')
            ]),
            $this->register_field(__('Host', 'jc-importer'), '_ftp_host', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP hostname', 'jc-importer')
            ]),
            $this->register_field(__('Username', 'jc-importer'), '_ftp_user', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP username', 'jc-importer')
            ]),
            $this->register_field(__('Password', 'jc-importer'), '_ftp_pass', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP password', 'jc-importer')
            ]),
            $this->register_field(__('Path', 'jc-importer'), '_ftp_path', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP base path, this is prefixed onto the Location field, leave empty to be ignore', 'jc-importer')
            ]),
            $this->register_field(__('Base URL', 'jc-importer'), '_remote_url', [
                'condition' => ['_download', '==', 'remote'],
                'tooltip' => __('Enter the base url, this is prefixed onto the Location field, leave empty to be ignore', 'jc-importer')
            ]),
            $this->register_field(__('Base URL', 'jc-importer'), '_local_url', [
                'condition' => ['_download', '==', 'local'],
                'tooltip' => __('Enter the base path from this servers root file system, this is prefixed onto the Location field, leave empty to be ignore', 'jc-importer')
            ]),

            $this->register_field(__('Permissions', 'jc-importer'), '_enable_image_hash', [
                'condition' => ['_download', '!=', 'media'],
                'default' => 'yes',
                'options' => [
                    ['value' => 'no', 'label' => __('Always download new files', 'jc-importer')],
                    ['value' => 'yes', 'label' => __('Search media library before downloading new files', 'jc-importer')],
                ],
                'tooltip' => __('Enable to stop duplicate images by searching the media library before downloading new files.', 'jc-importer')
            ]),
            $this->register_field(__('Delimiter', 'jc-importer'), '_delimiter', [
                'type' => 'text',
                'tooltip' => sprintf(__('A single character used to seperate attachments when listing multiple, Leave empty to use the default: %s', 'jc-importer'), ',')
            ]),

        ]);

        if (!in_array('_meta', $disabled_fields)) {
            $fields[] = $this->register_group(__('Attachment Meta', 'jc-importer'), '_meta', [
                $this->register_field(__('Enable Meta', 'jc-importer'), '_enabled', [
                    'default' => 'no',
                    'options' => [
                        ['value' => 'no', 'label' => __('No', 'jc-importer')],
                        ['value' => 'yes', 'label' => __('Yes', 'jc-importer')],
                    ],
                    'type' => 'select',
                    'tooltip' => __('Enable/Disable the fields to import attachment meta data.', 'jc-importer')
                ]),
                $this->register_field(__('Alt Text', 'jc-importer'), '_alt', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Image attachment alt text.', 'jc-importer'),
                ]),
                $this->register_field(__('Title Text', 'jc-importer'), '_title', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Attachments title text.', 'jc-importer')
                ]),
                $this->register_field(__('Caption Text', 'jc-importer'), '_caption', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Image attachments caption text.', 'jc-importer')
                ]),
                $this->register_field(__('Description Text', 'jc-importer'), '_description', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Attachments description text.', 'jc-importer')
                ])
            ]);
        }

        return $this->register_group(__('Settings', 'jc-importer'), 'settings', array_merge($extra_fields, $fields), ['type' => 'settings', 'condition' => $display_conditions]);
    }

    public function register_attachment_fields($label = 'Images & Attachments', $name = 'attachments', $field_label = 'Location', $group_args = null, $attachment_args = [])
    {
        if (is_null($group_args)) {
            $group_args = ['type' => 'repeatable', 'row_base' => true, 'link' => 'https://www.importwp.com/docs/how-to-import-wordpress-attachments-onto-a-post-type/'];
        }

        $display_conditions = isset($attachment_args['conditions']) ? $attachment_args['conditions'] : [];
        $extra_fields = isset($attachment_args['extra_fields']) ? $attachment_args['extra_fields'] : [];
        $disabled_fields = isset($attachment_args['disabled_fields']) ? $attachment_args['disabled_fields'] : [];

        return $this->register_group($label, $name, [
            $this->register_field($field_label, 'location', [
                'tooltip' => __('The source location of the file being attached.', 'jc-importer')
            ]),
            $this->_register_attachment_setting_fields($display_conditions, $extra_fields, $disabled_fields)
        ], $group_args);
    }

    public function get_field_options($field_name, $id)
    {
        $callback = false;
        foreach ($this->field_options as $callback_field_name => $temp_callback) {
            if (strpos($callback_field_name, '*') !== false) {

                $callback_field_name = str_replace('.', '\.', $callback_field_name);

                $pattern = str_replace('*', '[\S]+', $callback_field_name);
                if (1 !== preg_match("/^{$pattern}/i", $field_name)) {
                    continue;
                }

                $callback = $temp_callback;
                break;
            }

            if ($field_name !== $callback_field_name) {
                continue;
            }

            $callback = $temp_callback;
            break;
        }

        if (!$callback) {
            return false;
        }

        if (!method_exists($callback[0], $callback[1])) {
            return false;
        }

        if (!is_callable($callback)) {
            return false;
        }

        return call_user_func_array($callback, [$id]);
    }

    public function get_default_template_options()
    {
        return $this->default_template_options;
    }

    /**
     * Alter fields before they are parsed
     *
     * @param array $fields
     * @return array
     */
    public function field_map($fields)
    {
        return $fields;
    }

    public function config_field_map($field_map)
    {
        $template_fields = $this->field_map($field_map);

        $field_groups = [
            'default' => [
                'id' => 'default',
                'fields' => []
            ]
        ];

        foreach ($template_fields as $key => $value) {

            if (empty($value) || preg_match('/\.row_base$/', $key) !== 1) {
                continue;
            }

            $group_id = substr($key, 0, strlen($key) - strlen('.row_base'));

            $field_groups[$group_id] = [
                'id' => $group_id,
                'base' => $value,
                'fields' => []
            ];
        }

        foreach ($template_fields as $field_id => $field_value) {

            $found = false;

            foreach ($field_groups as $group_prefix => $group_settings) {
                if (false === strpos($field_id, $group_prefix) || preg_match('/\.row_base$/', $field_id) === 1) {
                    continue;
                }

                $field_groups[$group_prefix]['fields'][$field_id] = $field_value;
                $found = true;
            }

            if (false === $found) {
                $field_groups['default']['fields'][$field_id] = $field_value;
            }
        }

        if ($this->importer->has_custom_unique_identifier()) {

            $ref = $this->importer->getSetting('unique_identifier_ref');

            $field_groups['iwp'] = [
                'id' => 'iwp',
                'fields' => [
                    $this->importer->get_iwp_reference_meta_key() => (!is_null($ref) ? $ref : '')
                ]
            ];
        }

        return $field_groups;
    }

    /**
     * Process data before record is importer.
     *
     * Alter data that is passed to the mapper.
     *
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data)
    {
        $data = $this->pre_process_groups($data);
        $this->event_handler->run('template.pre_process', [$data, $this->importer, $this]);
        return $data;
    }

    public function process($post_id, ParsedData $data, ImporterModel $importer_model)
    {
        $this->featured_set = false;
        $this->event_handler->run('template.process', [$post_id, $data, $importer_model, $this]);
    }

    /**
     * Process data after record is importer.
     *
     * Use data that is returned from the mapper.
     *
     * @param int $post_id
     * @param ParsedData $data
     * @return void
     */
    public function post_process($post_id, ParsedData $data)
    {
        $this->event_handler->run('template.post_process', [$post_id, $data, $this]);
    }

    public function pre_process_groups(ParsedData $data)
    {
        // Allows virtual groups to be registered
        $this->groups = $this->event_handler->run('template.pre_process_groups', [$this->groups, $data, $this]);

        if (is_array($this->groups) && !empty($this->groups)) {
            $map = $data->getData('default');
            foreach ($this->groups as $group) {
                $group_map = [];

                foreach ($map as $field_key => $fields_map) {
                    if (preg_match('/^' . $group . '\.(.*?)$/', $field_key) === 1) {
                        $group_map[$field_key] = $fields_map;
                    }
                }

                $data->replace($group_map, $group);
            }
        }

        return $data;
    }

    /**
     * Process attachment fields 
     * 
     * @param int $post_id
     * @param array $row
     * @param string $row_prefix
     * @param Filesystem $filesystem
     * @param Ftp $ftp
     * @param Attachment $attachment
     */
    public function process_attachment($post_id, $row, $row_prefix, $filesystem, $ftp, $attachment)
    {
        $delimiter = apply_filters('iwp/value_delimiter', ',');
        $delimiter = apply_filters('iwp/attachment/value_delimiter', $delimiter);

        if (isset($row[$row_prefix . 'settings._delimiter'])) {

            if (strlen(trim($row[$row_prefix . 'settings._delimiter'])) === 1) {
                $delimiter = trim($row[$row_prefix . 'settings._delimiter']);
            }
        } elseif (isset($row[$row_prefix . '_delimiter'])) {

            if (strlen(trim($row[$row_prefix . '_delimiter'])) === 1) {
                $delimiter = trim($row[$row_prefix . '_delimiter']);
            }
        }

        $meta_delimiter = apply_filters('iwp/attachment/meta_delimiter', $delimiter);

        $locations = isset($row[$row_prefix . 'location']) ? $row[$row_prefix . 'location'] : null;
        $location_parts = explode($delimiter, $locations);
        $location_parts = array_filter(array_map('trim', $location_parts));

        if (isset($row[$row_prefix . 'settings._meta._title'])) {
            $attachment_titles = explode($meta_delimiter, $row[$row_prefix . 'settings._meta._title']);
        } elseif ($row[$row_prefix . '_meta._title']) {
            $attachment_titles = explode($meta_delimiter, $row[$row_prefix . '_meta._title']);
        } else {
            $attachment_titles = null;
        }

        if (isset($row[$row_prefix . 'settings._meta._alt'])) {
            $attachment_alts = explode($meta_delimiter, $row[$row_prefix . 'settings._meta._alt']);
        } elseif (isset($row[$row_prefix . '_meta._alt'])) {
            $attachment_alts = explode($meta_delimiter, $row[$row_prefix . '_meta._alt']);
        } else {
            $attachment_alts = null;
        }

        if (isset($row[$row_prefix . 'settings._meta._caption'])) {
            $attachment_captions = explode($meta_delimiter, $row[$row_prefix . 'settings._meta._caption']);
        } elseif (isset($row[$row_prefix . '_meta._caption'])) {
            $attachment_captions = explode($meta_delimiter, $row[$row_prefix . '_meta._caption']);
        } else {
            $attachment_captions = null;
        }

        if (isset($row[$row_prefix . 'settings._meta._description'])) {
            $attachment_descriptions = explode($meta_delimiter, $row[$row_prefix . 'settings._meta._description']);
        } elseif (isset($row[$row_prefix . '_meta._description'])) {
            $attachment_descriptions = explode($meta_delimiter, $row[$row_prefix . '_meta._description']);
        } else {
            $attachment_descriptions = null;
        }

        if (isset($row[$row_prefix . 'settings._enable_image_hash'])) {
            $attachment_enable_image_hash = $row[$row_prefix . 'settings._enable_image_hash'];
        } elseif (isset($row[$row_prefix . '_enable_image_hash'])) {
            $attachment_enable_image_hash = $row[$row_prefix . '_enable_image_hash'];
        } else {
            $attachment_enable_image_hash = 'yes';
        }

        $attachment_ids = [];
        $location_counter = 0;
        foreach ($location_parts as $location) {

            if (empty($location)) {
                continue;
            }

            if (isset($row[$row_prefix . 'settings._download'])) {
                $download = $row[$row_prefix . 'settings._download'];
            } elseif (isset($row[$row_prefix . '_download'])) {
                $download = $row[$row_prefix . '_download'];
            } else {
                $download = null;
            }

            if (isset($row[$row_prefix . 'settings._featured'])) {
                $featured = $row[$row_prefix . 'settings._featured'];
            } elseif (isset($row[$row_prefix . '_featured'])) {
                $featured = $row[$row_prefix . '_featured'];
            } else {
                $featured = null;
            }


            $source = null;
            $result = false;
            $attachment_id = null;
            $attachment_salt = '';

            $location = trim($location);

            switch ($download) {
                case 'remote':

                    if (isset($row[$row_prefix . 'settings._remote_url'])) {
                        $base_url = $row[$row_prefix . 'settings._remote_url'];
                    } elseif (isset($row[$row_prefix . '_remote_url'])) {
                        $base_url = $row[$row_prefix . '_remote_url'];
                    } else {
                        $base_url = null;
                    }

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    if (empty($source)) {
                        continue 2;
                    }


                    $attachment_salt = file_exists($source) ? md5_file($source) : '';
                    $attachment_id = 0;
                    if ($attachment_enable_image_hash == 'yes') {
                        $attachment_id = $attachment->get_attachment_by_hash($source);
                    }

                    // check to see if remote url matches file existing in media library
                    if ($attachment_id <= 0) {
                        $dir  = wp_get_upload_dir();
                        if (str_starts_with($source, $dir['baseurl'] . '/')) {
                            $attachment_id = attachment_url_to_postid($source);
                        }
                    }

                    if ($attachment_id <= 0) {

                        $main_zip_file = false;
                        if ($base_url === 'iwp_zip') {
                            $main_zip = get_post_meta($this->importer->getId(), '_iwp_zip', true);
                            if ($main_zip && file_exists($main_zip)) {
                                $base_url .= '://' . $main_zip;
                                $main_zip_file = $main_zip;
                            }
                        }

                        $zip = $this->get_zip_source($base_url, $filesystem);
                        if ($zip !== false) {

                            if (!$main_zip_file && isset($zip['src'])) {

                                /**
                                 * @var \ImportWP\Common\Http\Http $http
                                 */
                                $http = Container::getInstance()->get('http');
                                $result = $http->download_file($zip['src'], $zip['name']);
                                if (is_wp_error($result)) {
                                    break;
                                }

                                if (!$result) {
                                    $result = new \WP_Error('IWP_HTTP_2', __('Error downloading zip file', 'jc-importer'));
                                    break;
                                }
                            }

                            $result = $this->get_file_from_zip($main_zip_file ? $main_zip_file : $zip['name'], $location, $filesystem);
                            if (!$result) {
                                continue 2;
                            }

                            break;
                        }
                    }

                    $custom_filename = apply_filters('iwp/attachment/filename', null, $source);
                    if ($attachment_id <= 0) {
                        Logger::write(__CLASS__ . '::process__attachments -remote=' . $source . ' -filename=' . $custom_filename);
                        $result = $filesystem->download_file($source, null, null, $custom_filename);
                    }
                    break;
                case 'ftp':

                    if (isset($row[$row_prefix . 'settings._ftp_user'])) {
                        $ftp_user = $row[$row_prefix . 'settings._ftp_user'];
                    } elseif (isset($row[$row_prefix . '_ftp_user'])) {
                        $ftp_user = $row[$row_prefix . '_ftp_user'];
                    } else {
                        $ftp_user = null;
                    }

                    if (isset($row[$row_prefix . 'settings._ftp_host'])) {
                        $ftp_host = $row[$row_prefix . 'settings._ftp_host'];
                    } elseif (isset($row[$row_prefix . '_ftp_host'])) {
                        $ftp_host = $row[$row_prefix . '_ftp_host'];
                    } else {
                        $ftp_host = null;
                    }

                    if (isset($row[$row_prefix . 'settings._ftp_pass'])) {
                        $ftp_pass = $row[$row_prefix . 'settings._ftp_pass'];
                    } elseif (isset($row[$row_prefix . '_ftp_pass'])) {
                        $ftp_pass = $row[$row_prefix . '_ftp_pass'];
                    } else {
                        $ftp_pass = null;
                    }

                    if (isset($row[$row_prefix . 'settings._ftp_path'])) {
                        $base_url = $row[$row_prefix . 'settings._ftp_path'];
                    } elseif (isset($row[$row_prefix . '_ftp_path'])) {
                        $base_url = $row[$row_prefix . '_ftp_path'];
                    } else {
                        $base_url = null;
                    }

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    if (empty($source)) {
                        continue 2;
                    }

                    $attachment_salt = file_exists($source) ? md5_file($source) : '';
                    $attachment_id = 0;
                    if ($attachment_enable_image_hash == 'yes') {
                        $attachment_id = $attachment->get_attachment_by_hash($source, $attachment_salt);
                    }

                    if ($attachment_id <= 0) {

                        $main_zip_file = false;
                        if ($base_url === 'iwp_zip') {
                            $main_zip = get_post_meta($this->importer->getId(), '_iwp_zip', true);
                            if ($main_zip && file_exists($main_zip)) {
                                $base_url .= '://' . $main_zip;
                                $main_zip_file = $main_zip;
                            }
                        }

                        $zip = $this->get_zip_source($base_url, $filesystem);
                        if ($zip !== false) {

                            if (!$main_zip_file && isset($zip['src'])) {
                                $result = $ftp->download_file($zip['src'], $ftp_host, $ftp_user, $ftp_pass, $zip['name']);
                                if (is_wp_error($result)) {
                                    break;
                                }
                            }

                            $result = $this->get_file_from_zip($main_zip_file ? $main_zip_file : $zip['name'], $location, $filesystem);
                            if (!$result) {
                                continue 2;
                            }

                            break;
                        }
                    }

                    $custom_filename = apply_filters('iwp/attachment/filename', null, $source);
                    if ($attachment_id <= 0) {
                        Logger::write(__CLASS__ . '::process__attachments -ftp=' . $source . ' -filename=' . $custom_filename);
                        $result = $ftp->download_file($source, $ftp_host, $ftp_user, $ftp_pass, $custom_filename);
                    }
                    break;
                case 'local':

                    if (isset($row[$row_prefix . 'settings._local_url'])) {
                        $base_url = $row[$row_prefix . 'settings._local_url'];
                    } elseif (isset($row[$row_prefix . '_local_url'])) {
                        $base_url = $row[$row_prefix . '_local_url'];
                    } else {
                        $base_url = null;
                    }

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    if (empty($source)) {
                        continue 2;
                    }

                    $attachment_salt = file_exists($source) ? md5_file($source) : '';
                    $attachment_id = 0;
                    if ($attachment_enable_image_hash == 'yes') {
                        $attachment_id = $attachment->get_attachment_by_hash($source, $attachment_salt);
                    }

                    if ($attachment_id <= 0) {

                        $main_zip_file = false;
                        if ($base_url === 'iwp_zip') {
                            $main_zip = get_post_meta($this->importer->getId(), '_iwp_zip', true);
                            if ($main_zip && file_exists($main_zip)) {
                                $base_url .= '://' . $main_zip;
                                $main_zip_file = $main_zip;
                            }
                        }

                        $zip = $this->get_zip_source($base_url, $filesystem);
                        if ($zip !== false) {

                            if (!$main_zip_file && isset($zip['src'])) {
                                $result = $filesystem->copy($zip['src'], $zip['name']);
                                if (is_wp_error($result)) {
                                    break;
                                }
                            }

                            $result = $this->get_file_from_zip($main_zip_file ? $main_zip_file : $zip['name'], $location, $filesystem);
                            if (!$result) {
                                continue 2;
                            }

                            break;
                        }
                    }

                    $custom_filename = apply_filters('iwp/attachment/filename', null, $source);
                    if ($attachment_id <= 0) {
                        Logger::write(__CLASS__ . '::process__attachments -local=' . $source . ' -filename=' . $custom_filename);
                        $result = $filesystem->copy_file($source, null, $custom_filename);
                    }
                    break;
                case 'media':
                    $source = $location;
                    if (empty($source)) {
                        continue 2;
                    }

                    $attachment_id = $attachment->attachment_partial_url_to_postid($source);
                    Logger::write(__CLASS__ . '::process__attachments -media=' . $source);

                    break;
            }

            if (isset($row[$row_prefix . 'settings._meta._enabled'])) {
                $meta_enabled = $row[$row_prefix . 'settings._meta._enabled'] === 'yes' ? true : false;
            } elseif (isset($row[$row_prefix . '_meta._enabled'])) {
                $meta_enabled = $row[$row_prefix . '_meta._enabled'] === 'yes' ? true : false;
            } else {
                $meta_enabled = false;
            }

            // insert attachment
            if ($attachment_id <= 0) {

                if (is_wp_error($result)) {
                    Logger::write(__CLASS__ . '::process__attachments -error=' . $result->get_error_message());
                    $this->errors[] = $result;
                    continue;
                }

                if (!$result) {
                    continue;
                }

                $attachment_args = [];
                if ($meta_enabled) {
                    $attachment_args['title'] = isset($attachment_titles[$location_counter]) ? $attachment_titles[$location_counter] : null;
                    $attachment_args['alt'] = isset($attachment_alts[$location_counter]) ? $attachment_alts[$location_counter] : null;
                    $attachment_args['caption'] = isset($attachment_captions[$location_counter]) ? $attachment_captions[$location_counter] : null;
                    $attachment_args['description'] = isset($attachment_descriptions[$location_counter]) ? $attachment_descriptions[$location_counter] : null;;
                }

                $attachment_id = $attachment->insert_attachment($post_id, $result['dest'], $result['mime'], $attachment_args);
                if (is_wp_error($attachment_id)) {
                    Logger::write(__CLASS__ . '::process__attachments -error=' . $attachment_id->get_error_message());
                    continue;
                }

                $attachment->generate_image_sizes($attachment_id, $result['dest']);
                $attachment->store_attachment_hash($attachment_id, $source, $attachment_salt);
            } else {
                // Update existing attachment meta
                if ($meta_enabled) {
                    $post_data = [];

                    if (isset($attachment_titles[$location_counter])) {
                        $post_data['post_title'] = $attachment_titles[$location_counter];
                    }

                    if (isset($attachment_descriptions[$location_counter])) {
                        $post_data['post_content'] = $attachment_descriptions[$location_counter];
                    }

                    if (isset($attachment_captions[$location_counter])) {
                        $post_data['post_excerpt'] = $attachment_captions[$location_counter];
                    }

                    if (!empty($post_data)) {
                        $post_data['ID'] = $attachment_id;
                        wp_update_post($post_data);
                    }

                    if (isset($attachment_alts[$location_counter])) {
                        update_post_meta($attachment_id, '_wp_attachment_image_alt', $attachment_alts[$location_counter]);
                    }
                }
            }

            $attachment_ids[] = $attachment_id;
            $attachment_url = wp_get_attachment_url($attachment_id);
            $this->_attachments[] = $attachment_url;

            Logger::write(__CLASS__ . '::process__attachments -id=' . $attachment_id . ' -url=' . $attachment_url);

            // set featured
            if ('yes' === $featured && false === $this->featured_set) {
                update_post_meta($post_id, '_thumbnail_id', $attachment_id);
                $this->featured_set = true;
            }

            $location_counter++;
        }

        return $attachment_ids;
    }

    /**
     * Add error message
     *
     * @param string|\WP_Error $error
     * @return void
     */
    public function add_error($error)
    {
        if (!is_wp_error($error)) {
            $error = new \WP_Error('IWP_TEMP_ERR', $error);
        }

        $this->errors[] = $error;
    }

    /**
     * Convert fields/headings to data map
     * 
     * @param mixed $fields
     * @param ImporterModel $importer
     * @return array 
     */
    public function generate_field_map($fields, $importer)
    {
        return ['enabled' => [], 'map' => []];
    }

    public function get_zip_source($base_url, $filesystem)
    {
        $zip_parts = [];
        if (preg_match('/^iwp_zip:\/\/(.*?)$/', $base_url, $zip_parts) === 1) {

            $iwp_tmp = $filesystem->get_temp_directory();

            $iwp_tmp .= DIRECTORY_SEPARATOR . 'zip';
            if (!is_dir($iwp_tmp)) {
                mkdir($iwp_tmp);
            }

            $hash = md5($base_url);
            $iwp_tmp .= DIRECTORY_SEPARATOR . $hash . '.zip';
            if (!file_exists($iwp_tmp)) {
                return ['src' => $zip_parts[1], 'name' => $iwp_tmp];
            }

            return ['name' => $iwp_tmp];
        }

        return false;
    }

    public function get_file_from_zip($zip_path, $filename, $filesystem)
    {
        $zip = new \ZipArchive();
        if ($zip->open($zip_path) === true) {
            $output_data = $zip->getFromName($filename);
            if ($output_data === false) {
                return false;
            }

            return $filesystem->string_to_file($output_data, $filename);
        }

        return false;
    }

    public function try_use_main_zip($base_url, $location)
    {
        if ($base_url === 'iwp_zip') {

            $main_zip = get_post_meta($this->importer->getId(), '_iwp_zip', true);
            $source = $main_zip . $location;
            $base_url .= $main_zip;
        }
    }

    public function get_permission_fields($importer_model)
    {
        return [];
    }

    public function register_settings()
    {
    }

    public function register_options()
    {
        return [];
    }

    public function get_default_enabled_fields()
    {
        return $this->default_enabled_fields;
    }


    public function get_unique_identifier_options($importer_model, $unique_fields = [])
    {
        $output = [];
        return $output;
    }

    public function get_unique_identifier_options_from_map($importer_model, $unique_fields, $field_map, $optional_fields)
    {
        $output = [];
        $mapped_data = $importer_model->getMap();

        foreach ($field_map as $field_id => $field_map_key) {

            if (isset($output[$field_id])) {
                continue;
            }

            $output[$field_id] = [
                'value' => $field_id,
                'label' => $field_id,
                'uid' => false,
                'active' => false,
            ];

            if (in_array($field_id, $unique_fields)) {
                $output[$field_id]['uid'] = true;
            }

            if (!isset($mapped_data[$field_map_key]) || empty($mapped_data[$field_map_key])) {
                continue;
            }

            if (in_array($field_id, $optional_fields) && true !== $importer_model->isEnabledField($field_map_key)) {
                continue;
            }

            $output[$field_id]['active'] = true;
        }

        return $output;
    }
}
