<?php

namespace ImportWP\Common\Importer\Template;

use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Importer\File\XMLFile;
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
                [$this->register_field('Repeater Node', 'row_base', [
                    'type' => 'select',
                    'options' => 'callback',
                    'tooltip' => 'Select the path to the parent node containing values for this record, The preview when selecting data for each record should show a single record. Please note changing this will require updating references in this record.'
                ])],
                $fields
            );
        }

        return [
            'id' => $key,
            'heading' => $label,
            'type' => isset($args['type']) ? $args['type'] : 'group',
            'fields' => $fields
        ];
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

    public function register_attachment_fields($label = 'Attachments', $name = 'attachments', $field_label = 'Location', $group_args = null)
    {
        if (is_null($group_args)) {
            $group_args = ['type' => 'repeatable', 'row_base' => true];
        }
        return $this->register_group($label, $name, [
            $this->register_field($field_label, 'location', [
                'tooltip' => __('The source location of the file being attached.', 'importwp')
            ]),
            $this->register_field('Is Featured?', '_featured', [
                'default' => 'no',
                'options' => [
                    ['value' => 'no', 'label' => 'No'],
                    ['value' => 'yes', 'label' => 'Yes'],
                ],
                'tooltip' => __('Is the attachment the featured image for the current post.', 'importwp')
            ]),
            $this->register_field('Download', '_download', [
                'default' => 'remote',
                'options' => [
                    ['value' => 'remote', 'label' => 'Remote URL'],
                    ['value' => 'ftp', 'label' => 'FTP'],
                    ['value' => 'local', 'label' => 'Local Filesystem'],
                ],
                'tooltip' => __('Select how the attachment is being downloaded.', 'importwp')
            ]),
            $this->register_field('Host', '_ftp_host', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP hostname', 'importwp')
            ]),
            $this->register_field('Username', '_ftp_user', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP username', 'importwp')
            ]),
            $this->register_field('Password', '_ftp_pass', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP password', 'importwp')
            ]),
            $this->register_field('Path', '_ftp_path', [
                'condition' => ['_download', '==', 'ftp'],
                'tooltip' => __('Enter the FTP base path, this is prefixed onto the Location field, leave empty to be ignore', 'importwp')
            ]),
            $this->register_field('Base URL', '_remote_url', [
                'condition' => ['_download', '==', 'remote'],
                'tooltip' => __('Enter the base url, this is prefixed onto the Location field, leave empty to be ignore', 'importwp')
            ]),
            $this->register_field('Base URL', '_local_url', [
                'condition' => ['_download', '==', 'local'],
                'tooltip' => __('Enter the base path from this servers root file system, this is prefixed onto the Location field, leave empty to be ignore', 'importwp')
            ]),
            $this->register_group('Attachment Meta', '_meta', [
                $this->register_field('Enable Meta', '_enabled', [
                    'default' => 'no',
                    'options' => [
                        ['value' => 'no', 'label' => 'No'],
                        ['value' => 'yes', 'label' => 'Yes'],
                    ],
                    'type' => 'select',
                    'tooltip' => __('Enable/Disable the fields to import attachment meta data.', 'importwp')
                ]),
                $this->register_field('Alt Text', '_alt', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Image attachment alt text.', 'importwp'),
                ]),
                $this->register_field('Title Text', '_title', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Attachments title text.', 'importwp')
                ]),
                $this->register_field('Caption Text', '_caption', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Image attachments caption text.', 'importwp')
                ]),
                $this->register_field('Description Text', '_description', [
                    'condition' => ['_enabled', '==', 'yes'],
                    'tooltip' => __('Attachments description text.', 'importwp')
                ])
            ]),
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
        $meta_delimiter = apply_filters('iwp/attachment/meta_delimiter', $delimiter);

        $locations = isset($row[$row_prefix . 'location']) ? $row[$row_prefix . 'location'] : null;
        $location_parts = explode($delimiter, $locations);
        $location_parts = array_filter(array_map('trim', $location_parts));

        $attachment_titles = isset($row[$row_prefix . '_meta._title']) ? explode($meta_delimiter, $row[$row_prefix . '_meta._title']) : null;
        $attachment_alts = isset($row[$row_prefix . '_meta._alt']) ? explode($meta_delimiter, $row[$row_prefix . '_meta._alt']) : null;
        $attachment_captions = isset($row[$row_prefix . '_meta._caption']) ? explode($meta_delimiter, $row[$row_prefix . '_meta._caption']) : null;
        $attachment_descriptions = isset($row[$row_prefix . '_meta._description']) ? explode($meta_delimiter, $row[$row_prefix . '_meta._description']) : null;

        $attachment_ids = [];
        $location_counter = 0;
        foreach ($location_parts as $location) {

            if (empty($location)) {
                continue;
            }

            $download = isset($row[$row_prefix . '_download']) ? $row[$row_prefix . '_download'] : null;
            $featured = isset($row[$row_prefix . '_featured']) ? $row[$row_prefix . '_featured'] : null;
            $source = null;
            $result = false;
            $attachment_id = null;
            $attachment_salt = '';

            $location = trim($location);

            switch ($download) {
                case 'remote':
                    $base_url = isset($row[$row_prefix . '_remote_url']) ? $row[$row_prefix . '_remote_url'] : null;

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    if (empty($source)) {
                        continue 2;
                    }

                    $custom_filename = apply_filters('iwp/attachment/filename', null, $source);

                    $attachment_id = $attachment->get_attachment_by_hash($source);
                    if ($attachment_id <= 0) {
                        Logger::write(__CLASS__ . '::process__attachments -remote=' . $source . ' -filename=' . $custom_filename);
                        $result = $filesystem->download_file($source, null, null, $custom_filename);
                    }
                    break;
                case 'ftp':
                    $ftp_user = isset($row[$row_prefix . '_ftp_user']) ? $row[$row_prefix . '_ftp_user'] : null;
                    $ftp_host = isset($row[$row_prefix . '_ftp_host']) ? $row[$row_prefix . '_ftp_host'] : null;
                    $ftp_pass = isset($row[$row_prefix . '_ftp_pass']) ? $row[$row_prefix . '_ftp_pass'] : null;
                    $base_url = isset($row[$row_prefix . '_ftp_path']) ? $row[$row_prefix . '_ftp_path'] : null;

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    if (empty($source)) {
                        continue 2;
                    }

                    $custom_filename = apply_filters('iwp/attachment/filename', null, $source);

                    $attachment_id = $attachment->get_attachment_by_hash($source);
                    if ($attachment_id <= 0) {
                        Logger::write(__CLASS__ . '::process__attachments -ftp=' . $source . ' -filename=' . $custom_filename);
                        $result = $ftp->download_file($source, $ftp_host, $ftp_user, $ftp_pass, $custom_filename);
                    }
                    break;
                case 'local':
                    $base_url = isset($row[$row_prefix . '_local_url']) ? $row[$row_prefix . '_local_url'] : null;

                    // check if file hash is already stored
                    $source = $base_url . $location;
                    if (empty($source)) {
                        continue 2;
                    }

                    $custom_filename = apply_filters('iwp/attachment/filename', null, $source);
                    $attachment_salt = file_exists($source) ? md5_file($source) : '';
                    $attachment_id = $attachment->get_attachment_by_hash($source, $attachment_salt);
                    if ($attachment_id <= 0) {
                        Logger::write(__CLASS__ . '::process__attachments -local=' . $source . ' -filename=' . $custom_filename);
                        $result = $filesystem->copy_file($source, null, $custom_filename);
                    }
                    break;
            }

            $meta_enabled = isset($row[$row_prefix . '_meta._enabled']) && $row[$row_prefix . '_meta._enabled'] === 'yes' ? true : false;

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
}
