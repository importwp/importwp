<?php

namespace ImportWP\Common\Queue;

use ImportWP\Common\Importer\DataParser;
use ImportWP\Common\Importer\Parser\CSVParser;
use ImportWP\Common\Importer\Parser\XMLParser;
use ImportWP\Common\Queue\Action\CompleteAction;
use ImportWP\Common\Queue\Action\DeleteAction;
use ImportWP\Common\Queue\Action\ImportAction;
use ImportWP\Common\Queue\Action\SetupDeleteAction;
use ImportWP\Common\Queue\Action\SetupImportAction;
use ImportWP\Common\Util\DB;
use ImportWP\Common\Util\Logger;

class QueueTask implements QueueTaskInterface
{
    public $data_parser;
    public $mapper;
    public $importer_manager;
    public $importer;
    public $is_setup = false;
    public $template;
    public $importer_data;

    public function __construct($importer_manager)
    {
        $this->importer_manager = $importer_manager;
    }

    public function process($import_id, $chunk)
    {
        switch ($chunk['type']) {
            case 'S':
                if (!$this->is_setup) {
                    $this->setup($import_id, true);
                }
                return new SetupImportAction($import_id);

            case 'D':

                if (!$this->is_setup) {
                    $this->setup($import_id);
                }
                return new SetupDeleteAction($chunk, $this->mapper);

            case 'R':

                if (!$this->is_setup) {
                    $this->setup($import_id);
                }
                return new DeleteAction($import_id, $chunk, $this->mapper);

            case 'P':

                if (!$this->is_setup) {
                    $this->setup($import_id);
                }
                return new CompleteAction($import_id, $this->importer_data, $this->importer_manager);

            default:

                if (!$this->is_setup) {
                    $this->setup($import_id);
                }
                return new ImportAction($chunk, $this->data_parser, $this->importer);
        }
    }

    protected function setup($import_id = null, $init = false)
    {
        /**
         * @var \WPDB
         */
        global $wpdb;

        $import_table = DB::get_table_name('import');

        $importer_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `importer_id` FROM {$import_table} WHERE `id`=%d",
                [$import_id]
            )
        );
        $this->importer_data = $importer_data = $this->importer_manager->get_importer($importer_id);

        // TODO: cant run this from here.
        $this->importer_manager->event_handler->run('importer_manager.import', [$importer_data]);

        $config_data = get_site_option('iwp_importer_config_' . $importer_id, []);

        if ($init) {
            Logger::debug('IM -init_state');

            // if this is a new session, clear config files
            // rest importer log.
            Logger::clear($importer_id);
            Logger::debug('IM -clear_config_files');
            $this->importer_manager->clear_config_files($importer_id, false, true);
            $config_data['features'] = [
                'session_table' => true,
                'queue' => 1
            ];
        }

        Logger::debug('IM -get_config');
        $config = $this->importer_manager->get_config($importer_data);

        // template
        Logger::debug('IM -get_importer_template');
        $this->template = $template = $this->importer_manager->get_importer_template($importer_data);

        Logger::debug('IM -register_hooks');
        $template->register_hooks($importer_data);

        // permission
        Logger::debug('IM -permissions');
        $permission = new \ImportWP\Common\Importer\Permission\Permission($importer_data);

        // mapper
        Logger::debug('IM -get_importer_mapper');
        $mapper = $this->importer_manager->get_importer_mapper($importer_data, $template, $permission);

        if ($init) {
            // if this is a new session, build config

            Logger::debug('IM -generate_config');

            $config_data['data'] = $template->config_field_map($importer_data->getMap());
            $config->set('data', $config_data['data']);

            $config_data['id'] = $import_id;

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
                        $attachment_id = $this->importer_manager->remote_file($importer_data, $source, $importer_data->getParser());
                        break;
                    case 'local':
                        $raw_source = $importer_data->getDatasourceSetting('local_url');
                        $source = apply_filters('iwp/importer/datasource', $raw_source, $raw_source, $importer_data);
                        $source = apply_filters('iwp/importer/datasource/local', $source, $raw_source, $importer_data);
                        $attachment_id = $this->importer_manager->local_file($importer_data, $source, $importer_data->getParser());
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
        }

        $start = 0;

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

        if ($init) {
            Logger::debug('IM -get_record_count');
            $end = $parser->file()->getRecordCount();

            $config_data['start'] = $this->importer_manager->get_start($importer_data, $start);
            $config_data['end'] = $this->importer_manager->get_end($importer_data, $config_data['start'], $end);

            // if queue is enabled
            $queue = new Queue;
            $queue->generate($import_id, new QueueConfig(
                $config,
                $config_data['start'],
                $config_data['end']
            ));

            update_site_option('iwp_importer_config_' . $importer_id, $config_data);

            do_action('iwp/importer/init', $importer_data);
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

    public function teardown()
    {
        $this->template->unregister_hooks();

        $this->importer_manager->event_handler->run('importer_manager.import_shutdown', [$this->importer_data]);
    }

    public function __destruct()
    {
        if ($this->is_setup) {
            $this->teardown();
        }
    }
}
