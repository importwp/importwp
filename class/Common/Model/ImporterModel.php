<?php

namespace ImportWP\Common\Model;

use ImportWP\Common\Importer\ImporterStatus;
use ImportWP\Common\Util\Logger;

class ImporterModel
{
    /**
     * @var int $id
     */
    protected $id;

    /**
     * @var string $name
     */
    protected $name;

    /**
     * @var string $template
     */
    protected $template;

    /**
     * @var string $template_type
     */
    protected $template_type;

    /**
     * @var string $parser
     */
    protected $parser;

    /**
     * @var string $mapper
     */
    protected $mapper;

    /**
     * @var string $datasource
     */
    protected $datasource;

    /**
     * @var array $datasource_settings
     */
    protected $datasource_settings;

    /**
     * @var string $file
     */
    protected $file;

    /**
     * @var int $file_id
     */
    protected $file_id;

    /**
     * @var array $file_settings
     */
    protected $file_settings;

    /**
     * @var array $map
     */
    protected $map;

    /**
     * @var array $enabled
     */
    protected $enabled;

    /**
     * @var array $permissions
     */
    protected $permissions;

    /**
     * @var int $max_row
     */
    protected $max_row;

    /**
     * @var int $start_row
     */
    protected $start_row;

    /**
     * Importer general settings
     *
     * @var array
     */
    protected $settings = [];

    /**
     * @var bool
     */
    protected $debug;

    public function __construct($data = null, $debug = false)
    {
        $this->debug = $debug;
        $this->setup_data($data);
    }

    private function setup_data($data)
    {

        if (is_array($data)) {

            // fetch data from array
            $this->id = isset($data['id']) && intval($data['id']) > 0 ? intval($data['id']) : null;
            $this->name = $data['name'];
            $this->template = isset($data['template']) ? $data['template'] : null;
            $this->template_type = isset($data['template_type']) ? $data['template_type'] : null;
            $this->parser = isset($data['parser']) ? $data['parser'] : null;
            $this->mapper = isset($data['mapper']) ? $data['mapper'] : null;
            $this->map = isset($data['map']) ? $data['map'] : null;
            $this->enabled = isset($data['enabled']) ? $data['enabled'] : null;
            $this->file_id = isset($data['file'], $data['file']['id']) ? $data['file']['id'] : null;
            if ($this->file_id) {
                $this->file = $this->get_attached_file($this->file_id);
            }
            $this->file_settings = array_merge($this->getDetaultFileSettings(), isset($data['file'], $data['file']['settings']) ? $data['file']['settings'] : array());
            $this->datasource = isset($data['datasource'], $data['datasource']['type']) ? $data['datasource']['type'] : null;
            $this->datasource_settings = isset($data['datasource'], $data['datasource']['settings']) ? $data['datasource']['settings'] : null;
            $this->permissions = isset($data['permissions']) ? $data['permissions'] : null;
            $this->start_row = isset($data['settings'], $data['settings']['start_row']) ? $data['settings']['start_row'] : null;
            $this->max_row = isset($data['settings'], $data['settings']['max_row']) ? $data['settings']['max_row'] : null;
            $this->settings = isset($data['settings']) ? $data['settings'] : [];
        } elseif (!is_null($data)) {

            $post = false;

            if ($data instanceof \WP_Post) {

                // fetch data from post
                $post = $data;
            } elseif (intval($data) > 0) {

                // fetch data from id
                $this->id = intval($data);
                $post = get_post($this->id);
            }

            if ($post && $post->post_type === IWP_POST_TYPE) {

                $json = maybe_unserialize($post->post_content, true);
                $this->id = $post->ID;
                $this->name = $post->post_title;

                $this->template = isset($json['template']) ? $json['template'] : null;
                $this->template_type = isset($json['template_type']) ? $json['template_type'] : null;
                $this->parser = isset($json['parser']) ? $json['parser'] : null;
                $this->mapper = isset($json['mapper']) ? $json['mapper'] : null;
                $this->map = isset($json['map']) ? $json['map'] : [];
                $this->enabled = isset($json['enabled']) ? $json['enabled'] : [];
                $this->file_id = intval($json['file']['id']);
                if ($this->file_id > 0) {
                    $this->file = $this->get_attached_file($this->file_id);
                }

                if (!isset($json['file']['settings']) || !is_array($json['file']['settings'])) {
                    $json['file']['settings'] = [];
                }

                $this->file_settings = array_merge($this->getDetaultFileSettings(), $json['file']['settings']);

                $this->datasource = isset($json['datasource'], $json['datasource']['type']) ? $json['datasource']['type'] : null;
                $this->datasource_settings = isset($json['datasource'], $json['datasource']['settings']) ? $json['datasource']['settings'] : [];

                $this->permissions = isset($json['permissions']) ? $json['permissions'] : [];

                $this->start_row = isset($json['settings'], $json['settings']['start_row']) ? $json['settings']['start_row'] : null;
                $this->max_row = isset($json['settings'], $json['settings']['max_row']) ? $json['settings']['max_row'] : null;
                $this->settings = isset($json['settings']) ? $json['settings'] : [];
            }
        }

        Logger::setId($this->getId());
    }

    public function data($view = 'public')
    {
        $files = $this->getFiles();
        $file = $this->getFile();
        if ($view === 'public' && false === $this->debug) {
            $file = basename($file);
            foreach ($files as &$tmp_file) {
                $tmp_file = basename($tmp_file) . ' - (Added: ' . date(get_option('date_format') . ' \a\t ' . get_option('time_format'), filemtime($tmp_file)) . ')';
            }
        } else {
            foreach ($files as &$tmp_file) {

                $tmp_file .= ' - (Added: ' . date(get_option('date_format') . ' \a\t ' . get_option('time_format'), filemtime($tmp_file)) . ')';
            }
        }

        $file_data = [
            'id' => $this->getFileId(),
            'src' => $file,
            'settings' => $this->file_settings
        ];

        $datasource_data = [
            'type' => $this->getDatasource(),
            'settings' => (object) $this->getDatasourceSettings()
        ];

        $settings = array_merge($this->settings, [
            'start_row' => $this->start_row,
            'max_row' => $this->max_row,
        ]);

        $result = array(
            'id' => $this->id,
            'name' => $this->getName(),
            'template' => $this->template,
            'template_type' => $this->template_type,
            'parser' => $this->getParser(),
            'cron' => [],
            'file' => $file_data,
            'files' => (object) $files,
            'datasource' => $datasource_data,
            'map' => (object) $this->map,
            'enabled' => (object) $this->enabled,
            'permissions' => (object) $this->getPermissions(),
            'settings' => (object) $settings
        );

        if (true === $this->debug) {
            global $wpdb;
            $content = $wpdb->get_var($wpdb->prepare("SELECT post_content from {$wpdb->posts} WHERE post_type='%s' AND ID=%d", IWP_POST_TYPE, $this->getId()));
            $result['debug'] = [
                'settings' => base64_encode($content),
                // 'log' => file_get_contents(Logger::getLogFile($this->getId()))
            ];
        }

        return $result;
    }

    public function save()
    {
        // Match what happens in wp-rest.
        remove_filter('content_save_pre', 'wp_filter_post_kses');

        $settings = array_merge($this->settings, [
            'start_row' => $this->start_row,
            'max_row' => $this->max_row,
        ]);

        $postarr = array(
            'post_title' => $this->name,
            'post_content' => serialize(array(
                'template' => $this->template,
                'template_type' => $this->template_type,
                'file' => [
                    'id' => $this->file_id,
                    'settings' => $this->file_settings
                ],
                'datasource' => [
                    'type' => $this->datasource,
                    'settings' => $this->getDatasourceSettings()
                ],
                'parser' => $this->parser,
                'map' => $this->map,
                'enabled' => $this->enabled,
                'permissions' => $this->permissions,

                'settings' => $settings
            )),
        );

        if (is_null($this->id)) {
            $postarr['post_type'] = IWP_POST_TYPE;
            $postarr['post_status'] = 'publish';

            $result = wp_insert_post($postarr, true);
        } else {
            $postarr['ID'] = $this->id;
            $result = wp_update_post($postarr, true);
        }

        // Match what happens in wp-rest.
        add_filter('content_save_pre', 'wp_filter_post_kses');


        if (!is_wp_error($result)) {
            $this->setup_data($result);
        }

        return $result;
    }

    public function delete()
    {
        if (get_post_type($this->getId()) === IWP_POST_TYPE) {
            wp_delete_post($this->getId(), true);
        }
    }

    public function getAllowedFileTypes()
    {

        $parser = $this->getParser();
        $allowed_file_types = ['xml', 'csv'];

        if (!is_null($parser)) {
            $allowed_file_types = [$parser];
        }

        return $allowed_file_types;
    }

    public function setDatasource($datasource)
    {
        $this->datasource = $datasource;
    }

    public function setDatasourceSetting($key, $value)
    {
        $this->datasource_settings[$key] = $value;
    }

    public function getSetting($key)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : null;
    }

    public function setSetting($key, $value)
    {
        $this->settings[$key] = $value;
    }

    public function setFileId($file_id)
    {
        $this->file_id = $file_id;
        $this->file = $this->get_attached_file($this->file_id);

        if (is_null($this->file_settings)) {
            $this->file_settings = $this->getDetaultFileSettings();
        }
    }

    public function getDetaultFileSettings()
    {
        switch ($this->getParser()) {
            case 'xml':
                return [
                    'base_path' => null,
                    'setup' => false
                ];
                break;
            case 'csv':
                return [
                    'enclosure' => '"',
                    'delimiter' => ',',
                    'show_headings' => true,
                    'setup' => false
                ];
                break;
        }

        return [];
    }

    public function setFile($file_id, $settings)
    {
        $this->file_id = $file_id;
        $this->file = $this->get_attached_file($this->file_id);
        $this->file_settings = array_merge($this->getDetaultFileSettings(), $settings);
    }

    public function setMaxRow($max)
    {
        $this->max_row = $max;
    }

    public function setStartRow($start)
    {
        $this->start_row = $start;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setParser($parser)
    {
        $this->parser = $parser;
    }

    public function setTemplate($template, $template_type = '')
    {
        $this->template = $template;
        $this->template_type = $template_type;
    }

    public function getDatasource()
    {
        return $this->datasource;
    }

    public function getDatasourceSettings()
    {
        return $this->datasource_settings;
    }

    public function getDatasourceSetting($key)
    {
        return isset($this->datasource_settings[$key]) ? $this->datasource_settings[$key] : null;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getFileId()
    {
        return $this->file_id;
    }

    public function getFiles()
    {
        $files = [];

        if (!$this->getId()) {
            return $files;
        }

        global $wpdb;
        $query = "SELECT meta_key, meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id={$this->getId()} AND meta_key 
        LIKE '\_importer\_file\_%'";

        $results = $wpdb->get_results($query, ARRAY_A);

        foreach ($results as $result) {
            $key = $result['meta_key'];
            $id = intval(str_replace('_importer_file_', '', $key));
            $value = $result['meta_value'];
            if (file_exists($value)) {
                $files[$id] = $value;
            }
        }

        return $files;
    }

    public function get_attached_file($post_id)
    {
        $files = $this->getFiles();
        return isset($files[$post_id]) ? $files[$post_id] : false;
    }

    /**
     * Set the limit of importer files
     *
     * @param integer $limit The number of files to keep, 0 to keep all files. 
     * @return void
     */
    public function limit_importer_files($limit = 0)
    {

        $limit = intval($limit);
        if ($limit <= -1) {
            return;
        }

        $files = $this->getFiles();
        if (empty($files) || count($files) <= $limit) {
            return;
        }

        ksort($files);

        $file_ids = array_keys($files);

        for ($i = 0; $i < count($files) - $limit; $i++) {

            $id = $file_ids[$i];
            $path = $files[$id];

            if ($id === $this->getFileId()) {
                continue;
            }

            if (!file_exists($path) || unlink($path)) {
                global $wpdb;
                $query = "DELETE FROM {$wpdb->postmeta} WHERE post_id={$this->getId()} AND meta_key = '_importer_file_{$id}'";
                $result = $wpdb->query($query);
            }
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getMaxRow()
    {
        return $this->max_row;
    }

    public function getStartRow()
    {
        return $this->start_row;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getParser()
    {
        return $this->parser;
    }

    public function getStatus()
    {
        clean_post_cache($this->id);
        $post = get_post($this->id);
        return (array) maybe_unserialize($post->post_excerpt);
    }

    public function getStatusId()
    {
        return get_post_meta($this->getId(), '_iwp_session', true);
    }

    public function setStatus(ImporterStatus $status)
    {
        return $status->save(true);
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function getFileSettings()
    {
        return $this->file_settings;
    }

    public function getFileSetting($key)
    {
        return isset($this->file_settings[$key]) ? $this->file_settings[$key] : null;
    }

    public function setFileSetting($key, $value)
    {
        $this->file_settings[$key] = $value;
    }

    public function getMap()
    {
        return $this->map;
    }

    public function setMap($key, $value)
    {
        $this->map[$key] = $value;
    }

    public function getEnabled()
    {
        return $this->enabled;
    }

    public function setEnabled($key, $value = true)
    {
        if ($value === true) {
            $this->enabled[$key] = $value;
        } elseif (isset($this->enabled[$key])) {
            unset($this->enabled[$key]);
        }
    }

    public function isEnabledField($key)
    {
        return isset($this->enabled[$key]) && $this->enabled[$key] === true ? true : false;
    }

    public function getPermission($key)
    {
        return isset($this->permissions[$key]) ? $this->permissions[$key] : false;
    }

    public function getPermissions()
    {
        return $this->permissions;
    }

    public function setPermission($key, $settings)
    {
        $this->permissions[$key] = $settings;
    }
}
