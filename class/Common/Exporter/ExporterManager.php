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
use ImportWP\Common\Exporter\State\ExporterState;
use ImportWP\Common\Model\ExporterModel;
use ImportWP\Common\Util\Logger;
use ImportWP\Common\Util\Util;
use ImportWP\Container;

class ExporterManager
{
    /**
     * @var int
     */
    protected $memory_limit;

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

    /**
     * @param \ImportWP\Common\Model\ExporterModel $exporter_data 
     * @return \ImportWP\Common\Exporter\File\File 
     */
    public function get_exporter_file($exporter_data)
    {

        if ($exporter_data->getFileType() === 'csv') {
            $file = new CSVFile($exporter_data);
        } elseif ($exporter_data->getFileType() === 'xml') {
            $file = new XMLFile($exporter_data);
        } else {
            $file = new JSONFile($exporter_data);
        }

        return $file;
    }

    /**
     * @param \ImportWP\Common\Model\ExporterModel $exporter_data 
     * @return \ImportWP\Common\Exporter\Mapper\MapperInterface 
     */
    public function get_exporter_mapper($exporter_data)
    {
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

        return $mapper;
    }

    public function export($id, $user = null, $session = null)
    {
        Logger::timer();

        if (is_null($user)) {
            $user = uniqid('iwp', true);
        }

        $exporter_data = $this->get_exporter($id);
        $exporter_id = $exporter_data->getId();

        $config_data = []; // get_site_option('iwp_exporter_config_' . $exporter_id, []);

        $state = new ExporterState($exporter_id, $user);

        try {


            $state->init($session);

            $file = $this->get_exporter_file($exporter_data);
            $mapper = $this->get_exporter_mapper($exporter_data);

            /**
             * @var MapperInterface $mapper
             */
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



            if ($state->has_status('init')) {

                if (!$mapper->have_records($exporter_id)) {
                    throw new \Exception(__("No records found to export", 'jc-importer'));
                }

                // write
                $config_data['id'] = $state->get_session();
                $config_data['version'] = 1;
                $config_data['records'] = $mapper->get_records();

                // save
                update_site_option('iwp_exporter_config_' . $exporter_id, $config_data);

                $state->update(function ($state) use ($config_data) {
                    $state['id'] = $config_data['id'];
                    $state['status'] = 'running';
                    $state['progress']['export']['start'] = 0;
                    $state['progress']['export']['end'] = count($config_data['records']);
                    return $state;
                });

                $file->wipe();
                $file->start();
            } else {
                $config_data = get_site_option('iwp_exporter_config_' . $exporter_id, []);
                $mapper->set_records($config_data['records']);
            }

            /**
             * @var Properties $properties
             */
            $properties = Container::getInstance()->get('properties');
            $this->process_chunk($exporter_data, $user, $state, $mapper, $file, $properties);
        } catch (\Exception $e) {

            Logger::error('import -error=' . $e->getMessage(), $exporter_id);
            return $state->error($e)->get_raw();
        }


        return $state->update(function ($data) {
            $data['duration'] = floatval($data['duration']) + Logger::timer();
            return $data;
        })->get_raw();
    }

    public function process_chunk($exporter_data, $user, $exporter_state, $mapper, $file, $properties)
    {
        $id = $exporter_data->getId();

        $exporter_state->populate([
            'status' => 'processing'
        ]);
        ExporterState::set_state($id, $exporter_state->get_raw());

        $time_limit = $properties->get_setting('timeout');
        Logger::info('time_limit ' . $time_limit . 's');

        $start = microtime(true);
        $max_record_time = 0;
        $memory_max_usage = 0;

        $progress = $exporter_state->get_progress();
        $session = $exporter_state->get_session();
        $max_total = $progress['end'] - 1;
        $i = $progress['start'] + $progress['current_row'] - 1;

        // limit to max 20 rows per chunk
        $i_max = $i + apply_filters('iwp/exporter/chunk_max_records', 100);

        while (
            $i < $max_total
            && (!defined('REST_REQUEST') || !REST_REQUEST ||  $i < $i_max)
            && (
                $time_limit === 0 || $this->has_enough_time($start, $time_limit, $max_record_time)
            )
            && $this->has_enough_memory($memory_max_usage)
        ) {
            $i++;

            $flag = ExporterState::get_flag($id);

            if (ExporterState::is_paused($flag)) {

                $exporter_state->populate([
                    'status' => 'paused'
                ]);

                ExporterState::set_state($id, $exporter_state->get_raw());
                Util::write_status_session_to_file($id, $exporter_state);
                return;
            }

            if (ExporterState::is_cancelled($flag)) {
                $exporter_state->populate([
                    'status' => 'cancelled'
                ]);

                ExporterState::set_state($id, $exporter_state->get_raw());
                Util::write_status_session_to_file($id, $exporter_state);
                return;
            }

            $stats = [
                'rows' => 0,
                'skips' => 0,
                'errors' => 0,
            ];

            $record_time = microtime(true);

            try {

                // TODO: Export row

                $mapper_data = new MapperData($mapper, $i);
                if (!$mapper_data->skip()) {
                    $file->add($mapper_data);
                }

                Logger::write('export:' . $i . ' -success');
                $stats['rows']++;
            } catch (\Exception $e) {
                $stats['errors']++;
                Logger::error('export:' . $i . ' -error=' . $e->getMessage());
            }

            $exporter_state->update_importer_stats($stats);

            $exporter_state->increment_current_row();
            $progress = $exporter_state->get_progress();

            ExporterState::set_state($id, $exporter_state->get_raw());

            $max_record_time = max($max_record_time, microtime(true) - $record_time);
        }

        // TODO: need a new state that will stop the running from happening more than once.
        // if returning timeout then the cron will stop on older versions
        if (defined('IWP_PRO_VERSION') && version_compare(IWP_PRO_VERSION, '2.8.0', '>')) {
            // default status to idle after run
            $exporter_state->populate([
                'status' => 'timeout'
            ]);
        } else {
            $exporter_state->populate([
                'status' => 'running'
            ]);
        }

        $state_data = $exporter_state->get_raw();
        $progress = $exporter_state->get_progress();

        if ($progress['end'] - $progress['start'] <= $progress['current_row']) {
            switch ($exporter_state->get_section()) {
                case 'export':

                    // complete
                    $file->end();

                    $key = md5(time());
                    add_post_meta($exporter_data->getId(), '_ewp_file_' . $key, array(
                        'url' => $file->get_file_url(),
                        'path' => $file->get_file_path(),
                        'type' => $exporter_data->getFileType()
                    ));

                    $state_data['section'] = '';
                    $state_data['status'] = 'complete';
                    $state_data['file'] = $key;

                    break;
            }
        }

        ExporterState::set_state($id, $state_data);
        $exporter_state->populate($state_data);
    }

    function has_enough_time($start, $time_limit, $max_record_time)
    {
        return (microtime(true) - $start) < $time_limit - $max_record_time;
    }

    function get_memory_usage()
    {
        return memory_get_usage(true);
    }

    function has_enough_memory($memory_max_usage)
    {
        $limit = $this->get_memory_limit();

        // Has unlimited memory
        if ($limit == '-1') {
            return true;
        }

        $limit *= 0.9;
        $current_usage = $this->get_memory_usage();

        if ($current_usage + $memory_max_usage < $limit) {
            return true;
        }

        Logger::error(sprintf("Not Enough Memory left to use %s,  %s/%s", Logger::formatBytes($memory_max_usage, 2), Logger::formatBytes($current_usage, 2), Logger::formatBytes($limit, 2)));

        return false;
    }

    function get_memory_limit($force = false)
    {
        if ($force || is_null($this->memory_limit)) {

            $memory_limit = ini_get('memory_limit');
            if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
                if ($matches[2] == 'G') {
                    $memory_limit = $matches[1] * 1024 * 1024 * 1024; // nnnM -> nnn MB
                } elseif ($matches[2] == 'M') {
                    $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
                } else if ($matches[2] == 'K') {
                    $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
                }
            }

            $this->memory_limit = $memory_limit;

            Logger::info('memory_limit ' . $this->memory_limit . ' bytes');
        }

        return $this->memory_limit;
    }

    public function export_old($id)
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

    public function get_importer_map_fields($exporter_id)
    {
        $exporter_data = $this->get_exporter($exporter_id);
        if (!$exporter_data) {
            return new \WP_Error("Unable to get exporter");
        }

        $mapper = $this->get_exporter_mapper($exporter_data);
        $file_type = $exporter_data->getFileType();

        $fields = $exporter_data->getFields(true);
        if (empty($fields)) {

            $fields = $mapper->get_fields();

            switch ($file_type) {
                case 'csv':
                    $fields = $this->flattenFields($fields);
                    break;
                default:
                    $fields = $this->nestedFields($fields);
                    break;
            }
        }

        return $fields;
    }
}
