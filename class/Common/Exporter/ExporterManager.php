<?php

namespace ImportWP\Common\Exporter;

use ImportWP\Common\Exporter\File\CSVFile;
use ImportWP\Common\Exporter\File\JSONFile;
use ImportWP\Common\Exporter\File\XMLFile;
use ImportWP\Common\Exporter\Mapper\CommentMapper;
use ImportWP\Common\Exporter\Mapper\MapperData;
use ImportWP\Common\Exporter\Mapper\PostMapper;
use ImportWP\Common\Exporter\Mapper\TaxMapper;
use ImportWP\Common\Exporter\Mapper\UserMapper;
use ImportWP\Common\Model\ExporterModel;

class ExporterManager
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'download_file']);
    }

    /**
     * Download exporter file directly
     *
     * @return void
     */
    public function download_file()
    {

        if (!isset($_GET['page'], $_GET['exporter'], $_GET['download']) || $_GET['page'] !== 'importwp') {
            return;
        }

        $exporter_data = $this->get_exporter(intval($_GET['exporter']));
        $file = sanitize_key($_GET['download']);

        $download_data = get_post_meta($exporter_data->getId(), '_ewp_file_' . $file, true);
        if ($download_data) {

            delete_post_meta($exporter_data->getId(), '_ewp_file_' . $file, $download_data);

            header('Content-disposition: attachment; filename="' . basename($download_data['path']) . '"');
            header('Content-type: "text/' . $download_data['type'] . '"; charset="utf8"');
            readfile($download_data['path']);
            die();
        }
    }

    /**
     * Get Exporters
     *
     * @return ExporterModel[]
     */
    public function get_exporters()
    {

        $result = array();
        $query  = new \WP_Query(array(
            'post_type'      => EWP_POST_TYPE,
            'posts_per_page' => -1,
        ));

        foreach ($query->posts as $post) {
            $result[] = $this->get_exporter($post);
        }
        return $result;
    }

    /**
     * Get Exporter
     *
     * @param int $id
     * @return ExporterModel
     */
    public function get_exporter($id)
    {
        if ($id instanceof ExporterModel) {
            return $id;
        }

        if (EWP_POST_TYPE !== get_post_type($id)) {
            return false;
        }

        return new ExporterModel($id, $this->is_debug());
    }

    public function is_debug()
    {

        if (defined('IWP_DEBUG') && true === IWP_DEBUG) {
            return true;
        }

        $uninstall_enabled = get_option('iwp_settings');
        if (isset($uninstall_enabled['debug']) && true === $uninstall_enabled['debug']) {
            return true;
        }

        return false;
    }

    /**
     * Delete Importer
     *
     * @param int $id
     * @return void
     */
    public function delete_exporter($id)
    {
        $exporter = $this->get_exporter($id);
        $exporter->delete();
    }

    public function export($id)
    {
        $exporter_data = $this->get_exporter($id);
        $previous_time = microtime(true);

        if ($exporter_data->getFileType() === 'csv') {
            $file = new CSVFile($exporter_data);
        } elseif ($exporter_data->getFileType() === 'xml') {
            $file = new XMLFile($exporter_data);
        } else {
            $file = new JSONFile($exporter_data);
        }

        $type = $exporter_data->getType();
        $matches = null;
        if (preg_match('/^ewp_tax_(.*?)$/', $type, $matches) == 1) {
            $taxonomy = $matches[1];
            $mapper = new TaxMapper($taxonomy);
        } elseif (preg_match('/^ewp_comment_(.*?)$/', $type, $matches) == 1) {
            $post_type = $matches[1];
            $mapper = new CommentMapper($post_type);
        } elseif ($type === 'user') {
            $mapper = new UserMapper();
        } else {

            $mapper = apply_filters('iwp/exporter/load_mapper', false, $type);
            if (!$mapper) {
                $mapper = new PostMapper($type);
            }
        }

        $mapper->set_filters($exporter_data->getFilters());

        // Default to showing all fields, if none selected
        if (empty($exporter_data->getFields(true))) {

            $fields = $mapper->get_fields();

            switch ($exporter_data->getFileType()) {
                case 'csv':
                    $fields = $this->flattenFields($fields);
                    break;
                default:
                    $fields = $this->nestedFields($fields);
                    break;
            }


            $exporter_data->setFields($fields);
        }

        $file->start();

        $columns = $exporter_data->getFields();

        $total = 0;
        $i = 0;

        if ($mapper->have_records($exporter_data->getId())) {

            $total = $mapper->found_records();

            for ($i = 0; $i < $total; $i++) {

                $mapper_data = new MapperData($mapper, $i);
                if (!$mapper_data->skip()) {
                    $file->add($mapper_data);
                }

                $current_time = microtime(true);
                $delta_time = $current_time - $previous_time;

                if ($delta_time > 0.1) {
                    $exporter_data->set_status('running', $i, $total);
                    $previous_time = $current_time;
                }
            }
        }

        $file->end();

        $key = md5(time());
        add_post_meta($exporter_data->getId(), '_ewp_file_' . $key, array(
            'url' => $file->get_file_url(),
            'path' => $file->get_file_path(),
            'type' => $exporter_data->getFileType()
        ));

        $status = $exporter_data->set_status('complete', $i, $total);
        $status['file'] = $key;
        return $status;
        // echo json_encode($status) . "\n";

        // flush();
        // ob_flush();
        // die();
    }

    public function flattenFields($fields, $prefix = '')
    {
        $output = [];

        $output = array_merge($output, array_reduce($fields['fields'], function ($carry, $item) use ($prefix) {

            $carry[] = [
                'parent' => 0,
                'id' => count($carry) + 1,
                'selection' => $prefix . $item,
                'loop' => false,
                'custom' => false
            ];

            return $carry;
        }, []));

        if (!empty($fields['children'])) {
            foreach ($fields['children'] as $child) {
                $output = array_merge($output, $this->flattenFields($child, $child['key'] . '.'));
            }
        }

        return $output;
    }

    private $nested_field_counter = 0;

    public function nestedFields($fields, $prefix = '', $parent = 0)
    {
        $output = [];

        if ($parent == 0) {

            $this->nested_field_counter = 1;
            $output[] = [
                'parent' => 0,
                'id' => 1,
                'selection' => '',
                'label' => 'records',
                'loop' => false,
            ];

            $this->nested_field_counter++;
            $output[] = [
                'parent' => 1,
                'id' => 2,
                'selection' => 'main',
                'label' => 'record',
                'loop' => true,
            ];

            $parent = 2;
        } elseif ($fields['loop'] == true) {
            $this->nested_field_counter++;
            $output[] = [
                'parent' => $parent,
                'id' => $this->nested_field_counter,
                'selection' => '',
                'label' => $fields['key'] . '_wrapper',
                'loop' => false,
            ];
            $parent = $this->nested_field_counter;

            $this->nested_field_counter++;
            $output[] = [
                'parent' => $parent,
                'id' => $this->nested_field_counter,
                'selection' => $fields['key'],
                'label' => $fields['key'],
                'loop' => true,
            ];

            $parent = $this->nested_field_counter;
        }

        if (isset($fields['loop_fields']) && !empty($fields['loop_fields'])) {

            $output = array_merge($output, array_reduce($fields['loop_fields'], function ($carry, $item) use ($parent) {

                $this->nested_field_counter++;
                $carry[] = [
                    'parent' => $parent,
                    'id' => $this->nested_field_counter,
                    'selection' => $item,
                    'loop' => false,
                    'custom' => false
                ];

                return $carry;
            }, []));
        } else {

            $output = array_merge($output, array_reduce($fields['fields'], function ($carry, $item) use ($prefix, $parent) {

                $this->nested_field_counter++;
                $carry[] = [
                    'parent' => $parent,
                    'id' => $this->nested_field_counter,
                    'selection' => $prefix . $item,
                    'loop' => false,
                    'custom' => false
                ];

                return $carry;
            }, []));
        }

        if (!empty($fields['children'])) {
            foreach ($fields['children'] as $child) {
                $output = array_merge($output, $this->nestedFields($child, $child['key'] . '.', $parent));
            }
        }

        return $output;
    }
}
