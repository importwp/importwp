<?php

namespace ImportWP\Common\Queue;

use ImportWP\Common\Importer\DataParser;
use ImportWP\Common\Importer\Exception\FileException;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Exception\ParserException;
use ImportWP\Common\Importer\Exception\RecordUpdatedSkippedException;
use ImportWP\Common\Importer\Parser\CSVParser;
use ImportWP\Common\Util\DB;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;
use XMLParser;

class TMP_Queue_Task implements QueueTaskInterface
{
    public $data_parser;
    public $mapper;
    public $importer_manager;
    public $importer;
    public $is_setup = false;

    public function __construct($importer_manager)
    {
        $this->importer_manager = $importer_manager;
    }

    public function process($import_id, $chunk)
    {
        switch ($chunk['type']) {
            case 'S':
                return $this->process_init($import_id, $chunk);
            case 'D':
                $this->setup();
                return $this->process_delete($import_id, $chunk);
            case 'R':
                $this->setup();
                return $this->process_remove($import_id, $chunk);
            case 'P':
                $this->setup();
                return $this->process_complete($import_id, $chunk);
            default:
                $this->setup();
                return $this->process_import($import_id, $chunk);
        }
    }

    protected function setup()
    {
        if ($this->is_setup) {
            return;
        }

        /**
         * @var \WPDB
         */
        global $wpdb;

        $import_table = DB::get_table_name('import');
        $importer_id = $wpdb->get_var("SELECT `importer_id` FROM {$import_table}");
        $importer_data = $this->importer_manager->get_importer($importer_id);

        Logger::debug('IM -get_config');
        $config = $this->importer_manager->get_config($importer_data);

        // template
        Logger::debug('IM -get_importer_template');
        $template = $this->importer_manager->get_importer_template($importer_data);

        Logger::debug('IM -register_hooks');
        $template->register_hooks($importer_data);

        // permission
        Logger::debug('IM -permissions');
        $permission = new \ImportWP\Common\Importer\Permission\Permission($importer_data);

        // mapper
        Logger::debug('IM -get_importer_mapper');
        $mapper = $this->importer_manager->get_importer_mapper($importer_data, $template, $permission);

        // get parser
        if ($importer_data->getParser() === 'csv') {
            Logger::debug('IM -get_csv_file');
            $file = $this->importer_manager->get_csv_file($importer_data, $config);
            Logger::debug('IM -load_parser');
            $parser = new CSVParser($file);
            if (true === $importer_data->getFileSetting('show_headings')) {
                $start = 1;
            }
        } elseif ($importer_data->getParser() === 'xml') {
            Logger::debug('IM -get_xml_file');
            $file = $this->importer_manager->get_xml_file($importer_data, $config);
            Logger::debug('IM -load_parser');
            $parser = new XMLParser($file);
        } else {
            $parser = apply_filters('iwp/importer/init_parser', false, $importer_data, $config);
        }

        $this->mapper = $mapper;
        $this->mapper->setup();

        $this->data_parser = new DataParser($parser, $this->mapper, $config->getData());

        $this->importer = new \ImportWP\Common\Importer\Importer($config);
        $this->importer->parser($parser);
        $this->importer->mapper($mapper);
        $this->importer->filter($importer_data->getFilters());
        $this->importer->disable_caching();

        $this->is_setup = true;
    }

    public function process_init($import_session_id, $chunk)
    {
        /**
         * @var \WPDB
         */
        global $wpdb;

        /**
         * @var \ImportWP\Common\Importer\ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');

        $import_table = DB::get_table_name('import');
        $importer_id = $wpdb->get_var("SELECT `importer_id` FROM {$import_table}");
        $importer_data = $importer_manager->get_importer($importer_id);

        // store current importer
        iwp()->importer = $importer_data;

        $config_data = get_site_option('iwp_importer_config_' . $importer_id, []);

        Logger::debug('IM -init_state');

        // if this is a new session, clear config files
        // rest importer log.
        Logger::clear($importer_id);
        Logger::debug('IM -clear_config_files');
        $importer_manager->clear_config_files($importer_id, false, true);
        $config_data['features'] = [
            'session_table' => true,
            'queue' => 1
        ];

        Logger::debug('IM -get_config');
        $config = $importer_manager->get_config($importer_data);

        // template
        Logger::debug('IM -get_importer_template');
        $template = $importer_manager->get_importer_template($importer_data);

        Logger::debug('IM -register_hooks');
        $template->register_hooks($importer_data);

        // permission
        Logger::debug('IM -permissions');
        $permission = new \ImportWP\Common\Importer\Permission\Permission($importer_data);

        // mapper
        Logger::debug('IM -get_importer_mapper');
        $mapper = $importer_manager->get_importer_mapper($importer_data, $template, $permission);

        // if this is a new session, build config

        Logger::debug('IM -generate_config');

        $config_data['data'] = $template->config_field_map($importer_data->getMap());
        $config->set('data', $config_data['data']);

        $config_data['id'] = $import_session_id;

        // This is used for storing version on imported records
        update_post_meta($importer_id, '_iwp_session', $config_data['id']);

        // Increase Version
        $version = get_post_meta($importer_id, '_iwp_version', true);
        if ($version !== false) {
            $version++;
        } else {
            $version = 0;
        }
        update_post_meta($importer_id, '_iwp_version', $version);
        $config_data['version'] = $version;

        /**
         * Fetch new file if setting is checked
         * @since 2.7.15 
         */
        $run_fetch_file = $importer_data->getSetting('run_fetch') || false;
        $run_fetch_file = apply_filters('iwp/importer/run_fetch_file',  $run_fetch_file);
        if ($run_fetch_file) {

            $datasource = $importer_data->getDatasource();
            switch ($datasource) {
                case 'remote':
                    $raw_source = $importer_data->getDatasourceSetting('remote_url');
                    $source = apply_filters('iwp/importer/datasource', $raw_source, $raw_source, $importer_data);
                    $source = apply_filters('iwp/importer/datasource/remote', $source, $raw_source, $importer_data);
                    $attachment_id = $importer_manager->remote_file($importer_data, $source, $importer_data->getParser());
                    break;
                case 'local':
                    $raw_source = $importer_data->getDatasourceSetting('local_url');
                    $source = apply_filters('iwp/importer/datasource', $raw_source, $raw_source, $importer_data);
                    $source = apply_filters('iwp/importer/datasource/local', $source, $raw_source, $importer_data);
                    $attachment_id = $importer_manager->local_file($importer_data, $source, $importer_data->getParser());
                    break;
                default:
                    // TODO: record error 
                    $attachment_id = new \WP_Error('IWP_CRON_1', sprintf(__('Unable to get new file using datasource: %s', 'jc-importer'), $datasource));
                    break;
            }

            if (is_wp_error($attachment_id)) {
                throw new \Exception(sprintf(__('Importer Datasource: %s', 'jc-importer'), $attachment_id->get_error_message()));
            }
        }

        $start = 0;

        // get parser
        if ($importer_data->getParser() === 'csv') {
            Logger::debug('IM -get_csv_file');
            $file = $importer_manager->get_csv_file($importer_data, $config);
            Logger::debug('IM -load_parser');
            $parser = new CSVParser($file);
            if (true === $importer_data->getFileSetting('show_headings')) {
                $start = 1;
            }
        } elseif ($importer_data->getParser() === 'xml') {
            Logger::debug('IM -get_xml_file');
            $file = $importer_manager->get_xml_file($importer_data, $config);
            Logger::debug('IM -load_parser');
            $parser = new XMLParser($file);
        } else {
            $parser = apply_filters('iwp/importer/init_parser', false, $importer_data, $config);
        }

        // if this is a new session, set start / end rows to state

        Logger::debug('IM -get_record_count');
        $end = $parser->file()->getRecordCount();

        $config_data['start'] = $importer_manager->get_start($importer_data, $start);
        $config_data['end'] = $importer_manager->get_end($importer_data, $config_data['start'], $end);

        // if queue is enabled
        $queue = new Queue;
        $queue->generate($import_session_id, new TMP_Config_Queue(
            $config,
            $config_data['start'],
            $config_data['end']
        ));

        update_site_option('iwp_importer_config_' . $importer_id, $config_data);

        do_action('iwp/importer/init', $importer_data);

        return new QueueTaskResult(null, 'Y');
    }

    public function process_import($import_id, $chunk)
    {
        $i = $chunk['pos'];

        /**
         * @var ParsedData $data
         */
        $data = null;

        $record_id = 0;
        $import_type = '';
        $message = '';

        try {

            $data = $this->data_parser->get($i);
            do_action('iwp/importer/before_row', $data);

            $skip_record = false; //$this->filterRecords();
            $skip_record = apply_filters('iwp/importer/skip_record', $skip_record, $data, $this->importer);

            if ($skip_record) {

                Logger::write('import -skip-record=' . $i);
                $message = apply_filters('iwp/status/record_skipped', "Skipped Record");
                $data = null;
            } else {

                // import
                $data = apply_filters('iwp/importer/before_mapper', $data, $this->importer);
                $data->map();

                if ($data->isInsert()) {
                    Logger::write('import:' . $i . ' -success -insert');
                    $import_type = 'I';
                    $record_id = $data->getId();
                    $message = apply_filters('iwp/status/record_inserted', 'Record Inserted: #' . $data->getId(), $data->getId(), $data);
                }

                if ($data->isUpdate()) {
                    Logger::write('import:' . $i . ' -success -update');
                    $import_type = 'U';
                    $record_id = $data->getId();
                    $message = apply_filters('iwp/status/record_updated', 'Record Updated: #' . $data->getId(), $data->getId(), $data);
                }
            }
        } catch (RecordUpdatedSkippedException $e) {

            Logger::write('import:' . $i . ' -success -update -skipped="hash"');
            $import_type = 'S';
            $message = 'Record Update Skipped: #' . $data->getId() . ' ' . $e->getMessage();
        } catch (ParserException $e) {

            $import_type = 'E';

            Logger::error('import:' . $i . ' -parser-error=' . $e->getMessage());
            $message = 'Record Error: #' . $data->getId() . ' ' . $e->getMessage();
        } catch (MapperException $e) {

            $import_type = 'E';

            Logger::error('import:' . $i . ' -mapper-error=' . $e->getMessage());
            $message = 'Record Error: #' . $data->getId() . ' ' . $e->getMessage();
        } catch (FileException $e) {

            $import_type = 'E';

            Logger::error('import:' . $i . ' -file-error=' . $e->getMessage());
            $message = 'Record Error: #' . $data->getId() . ' ' . $e->getMessage();
        }

        do_action('iwp/importer/after_row');

        return new QueueTaskResult($record_id, $import_type, $message);
    }

    public function process_remove($import_id, $chunk)
    {
        $i = $chunk['pos'];
        $object_id = $chunk['record'];

        if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

            try {

                if (apply_filters('iwp/importer/enable_custom_delete_action', false, $import_id)) {

                    Logger::write('custom_delete_action:' . $i . ' -object=' . $object_id);
                    do_action('iwp/importer/custom_delete_action', $import_id, $object_id);
                } else {

                    Logger::write('delete:' . $i . ' -object=' . $object_id);
                    $this->mapper->delete($object_id);
                }

                $message = apply_filters('iwp/status/record_deleted', 'Record Deleted: #' . $object_id, $object_id);
            } catch (MapperException $e) {

                Logger::error('delete:' . $i . ' -mapper-error=' . $e->getMessage());
            }
        }

        return new QueueTaskResult($object_id, 'R');
    }

    public function process_delete($import_id, $chunk)
    {
        if ($this->mapper->permission() && $this->mapper->permission()->allowed_method('remove')) {

            // generate list of items to be deleted
            $object_ids = $this->mapper->get_objects_for_removal();
            $object_ids = array_values(array_unique($object_ids));
            if (!empty($object_ids)) {

                /**
                 * @var \WPDB $wpdb
                 */
                global $wpdb;

                $table_name = DB::get_table_name('queue');
                $base_query = "INSERT INTO {$table_name} (`import_id`,`record`, `pos`,`type`) VALUES ";
                $query_values = [];
                $rollback = false;

                $wpdb->query('START TRANSACTION');

                foreach ($object_ids as $i => $row) {
                    $query_values[] = "('{$chunk['import_id']}','{$row}', {$i}, 'R')";

                    if (count($query_values) > 1000) {

                        if (!$wpdb->query($base_query . implode(',', $query_values))) {
                            $rollback = true;
                            break;
                        }

                        $query_values = [];
                    }
                }

                if (!$rollback && !empty($query_values)) {
                    if (!$wpdb->query($base_query . implode(',', $query_values))) {
                        $rollback = true;
                    }
                }

                if ($rollback) {
                    $wpdb->query('ROLLBACK');
                } else {
                    $wpdb->query('COMMIT');
                }
            }
        }

        // TODO: process which items need to be queued for deleting records.
        return new QueueTaskResult(0, 'Y');
    }

    public function process_complete($import_id, $chunk)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        if ($table_name = DB::get_table_name('import')) {
            $wpdb->update($table_name, ['status' => 'Y'], ['id' => $import_id]);
        }

        return new QueueTaskResult(0, 'Y');
    }
}
