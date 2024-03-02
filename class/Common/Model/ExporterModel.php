<?php

namespace ImportWP\Common\Model;

use ImportWP\Common\Util\Logger;

class ExporterModel
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
     * @var string $type
     */
    protected $type;

    /**
     * @var string $file_type
     */
    protected $file_type;

    /**
     * @var string $file_settings
     */
    protected $file_settings;

    /**
     * @var string $unique_identifier
     */
    protected $unique_identifier;

    /**
     * @var array $fields
     */
    protected $fields;

    /**
     * @var array $filters
     */
    protected $filters;

    /**
     * @var array $cron
     */
    protected $cron;

    /**
     * @var string
     */
    protected $export_method = 'run';

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
            $this->type      = $data['type'];
            $this->file_type = $data['file_type'];
            $this->file_settings = isset($data['file_settings']) ? $data['file_settings'] : [];
            $this->fields    = $data['fields'];
            $this->filters    = $data['filters'];
            $this->unique_identifier = $data['unique_identifier'];
            $this->export_method = isset($data['export_method']) ? $data['export_method'] : 'run';
            $this->cron = isset($data['cron']) ? $data['cron'] : [];
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

            if ($post && $post->post_type === EWP_POST_TYPE) {

                $json = maybe_unserialize($post->post_content, true);
                $this->id = $post->ID;
                $this->name = $post->post_title;
                $this->type      = $json['type'];
                $this->fields    = (array) $json['fields'];
                $this->file_type = $json['file_type'];
                $this->file_settings = isset($json['file_settings']) ? $json['file_settings'] : [];
                $this->filters = $json['filters'];
                $this->unique_identifier = $json['unique_identifier'];
                $this->export_method = isset($json['export_method']) ? $json['export_method'] : 'run';
                $this->cron = isset($json['cron']) ? $json['cron'] : [];
            }
        }

        Logger::setId($this->getId());
    }

    public function data($view = 'public')
    {
        $result = array(
            'id' => $this->id,
            'name' => '' . $this->getName(),
            'type'      => '' . $this->type,
            'file_type' => '' . $this->file_type,
            'file_settings' => $this->file_settings,
            'fields'    => $this->fields,
            'filters'    => $this->filters,
            'unique_identifier' => '' . $this->unique_identifier,
            'export_method' => '' . $this->export_method,
            'cron' => $this->cron
        );

        if (true === $this->debug) {
            global $wpdb;
            $content = $wpdb->get_var($wpdb->prepare("SELECT post_content from {$wpdb->posts} WHERE post_type='%s' AND ID=%d", EWP_POST_TYPE, $this->getId()));
            $result['debug'] = [
                'settings' => base64_encode($content),
            ];
        }

        return $result;
    }

    public function save()
    {
        // Match what happens in wp-rest.
        remove_filter('content_save_pre', 'wp_filter_post_kses');

        $postarr = array(
            'post_title' => $this->name,
            'post_content' => wp_slash(serialize(array(
                'type'      => $this->type,
                'fields'    => (array) $this->fields,
                'file_type' => $this->file_type,
                'file_settings' => $this->file_settings,
                'filters' => (array)$this->filters,
                'unique_identifier' => '' . $this->unique_identifier,
                'export_method' => '' . $this->export_method,
                'cron' => $this->cron
            ))),
        );

        if (is_null($this->id)) {
            $postarr['post_type'] = EWP_POST_TYPE;
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
        if (get_post_type($this->getId()) === EWP_POST_TYPE) {
            wp_delete_post($this->getId(), true);
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getFileType()
    {
        return $this->file_type;
    }

    /**
     * @param string $file_type
     */
    public function setFileType($file_type)
    {
        $this->file_type = $file_type;
    }

    /**
     * @return array
     */
    public function getFields($public = false)
    {
        $output = $this->fields;

        if (!$public) {
            // nest files, make sure there is a main loop
            if ($this->getFileType() !== 'csv' && !empty($output)) {
                $found = false;
                foreach ($output as $row) {
                    if (isset($row['loop']) && $row['loop'] == true && $row['loop'] == 'true' && $row['selection'] == 'main') {
                        $found = true;
                    }
                }

                if (!$found) {
                    foreach ($output as $i => $row) {
                        if ($output[$i]['parent'] == 0) {
                            $output[$i]['parent'] = 2;
                        } else {
                            $output[$i]['parent'] = intval($row['id']) + 2;
                        }

                        $output[$i]['id'] = intval($row['id']) + 2;
                    }

                    if ($this->getFileType() === 'xml') {
                        $output[] = ['id' => 2, 'parent' => 1, 'selection' => 'main', 'loop' => true, 'label' => 'record'];
                        $output[] = ['id' => 1, 'parent' => 0, 'selection' => '', 'loop' => false, 'label' => 'records'];
                    } elseif ($this->getFileType() === 'json') {
                        $output[] = ['id' => 2, 'parent' => 0, 'selection' => 'main', 'loop' => true, 'label' => 'records'];
                    }
                }
            }

            // make sure there is unique identifier within the main loop / flat file

            $unique_identifier = $this->getUniqueIdentifier();

            $found = array_reduce($output, function ($carry, $item) use ($unique_identifier) {

                if (isset($item['selection']) && $item['selection'] == $unique_identifier) {
                    return true;
                }

                return $carry;
            }, false);

            if (!$found) {
                switch ($this->getFileType()) {
                    case 'csv':
                        $output[] = ['id' => -1, 'parent' => 0, 'selection' => $unique_identifier];
                        break;
                    default:
                        // search fields for main loop and insert.
                        $tmp = false;
                        foreach ($output as $row) {
                            if (isset($row['loop']) && $row['loop'] == true && $row['loop'] == 'true' && $row['selection'] == 'main') {
                                $tmp = ['id' => -1, 'parent' => $row['id'], 'selection' => $unique_identifier];
                                break;
                            }
                        }

                        if ($tmp) {
                            array_unshift($output, $tmp);
                        }
                        break;
                }
            }
        }

        return $output;
    }

    /**
     * @param array $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return string
     */
    public function getUniqueIdentifier()
    {
        return $this->unique_identifier;
    }

    /**
     * @param string $unique_identifier
     */
    public function setUniqueIdentifier($unique_identifier)
    {
        $this->unique_identifier = $unique_identifier;
    }

    /**
     * @param array $filters
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
    }

    public function getFilters()
    {
        $output = [];
        $filter_data = $this->filters;
        if (!is_array($filter_data)) {
            return $output;
        }

        $max_groups = isset($filter_data['filters._index']) ? intval($filter_data['filters._index']) : 0;
        if ($max_groups <= 0) {
            return $output;
        }

        for ($i = 0; $i < $max_groups; $i++) {
            $group_data = [];
            $max_rows = isset($filter_data["filters.{$i}._index"]) ? intval($filter_data["filters.{$i}._index"]) : 0;
            if ($max_groups <= 0) {
                continue;
            }

            for ($j = 0; $j < $max_rows; $j++) {

                $group_data[] = [
                    'left' => isset($filter_data["filters.{$i}.{$j}.left"]) ? $filter_data["filters.{$i}.{$j}.left"] : "",
                    'condition' => isset($filter_data["filters.{$i}.{$j}.condition"]) ? $filter_data["filters.{$i}.{$j}.condition"] : "equal",
                    'right' => isset($filter_data["filters.{$i}.{$j}.right"]) ? $filter_data["filters.{$i}.{$j}.right"] : "",
                ];
            }

            $output[] = $group_data;
        }

        return $output;
    }

    public function get_status()
    {
        clean_post_cache($this->getId());
        $status = get_post_meta($this->getId(), '_ewp_status', true);
        if (!$status) {
            $status = $this->set_status('created');
        }
        return $status;
    }

    public function set_status($status, $counter = 0, $total = 0)
    {

        $progress = $total > 0 ? round(($counter / $total) * 100, 2) : 0;
        $data = array('status' => $status, 'date' => date('Y-m-d H:i:s'), 'progress' => $progress, 'count' => $counter, 'total' => $total);
        update_post_meta($this->getId(), '_ewp_status', $data);

        return $data;
    }

    public function setExportMethod($method)
    {
        $this->export_method = $method;
    }

    public function getExportMethod()
    {
        return $this->export_method;
    }

    public function setCron($settings)
    {
        $this->cron = $settings;
    }

    public function getCron()
    {
        return $this->cron;
    }

    public function getFileSettings()
    {
        return $this->file_settings;
    }

    public function setFileSettings($settings)
    {
        $this->file_settings = $settings;
    }

    public function setFileSetting($name, $value)
    {
        $this->file_settings[$name] = $value;
    }

    public function getFileSetting($name, $default = null)
    {
        return isset($this->file_settings[$name]) ? $this->file_settings[$name] : $default;
    }
}
