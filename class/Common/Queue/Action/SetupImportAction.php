<?php

namespace ImportWP\Common\Queue\Action;

use ImportWP\Common\Importer\Parser\CSVParser;
use ImportWP\Common\Queue\Queue;
use ImportWP\Common\Queue\QueueTaskResult;
use ImportWP\Common\Queue\TMP_Config_Queue;
use ImportWP\Common\Util\DB;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;
use XMLParser;

class SetupImportAction implements ActionInterface
{
    protected $import_session_id;

    public function __construct(
        $import_session_id
    ) {
        $this->import_session_id = $import_session_id;
    }

    public function handle()
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
        $importer_id = $wpdb->get_var("SELECT `importer_id` FROM {$import_table} WHERE `id`={$this->import_session_id}");
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

        $config_data['id'] = $this->import_session_id;

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
        $queue->generate($this->import_session_id, new TMP_Config_Queue(
            $config,
            $config_data['start'],
            $config_data['end']
        ));

        update_site_option('iwp_importer_config_' . $importer_id, $config_data);

        do_action('iwp/importer/init', $importer_data);

        return new QueueTaskResult(null, 'Y');
    }
}
