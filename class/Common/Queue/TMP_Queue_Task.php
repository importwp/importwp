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
use ImportWP\Container;

class TMP_Queue_Task implements QueueTaskInterface
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
                return new SetupImportAction($import_id);

            case 'D':

                if (!$this->is_setup) {
                    $this->setup();
                }
                return new SetupDeleteAction($chunk, $this->mapper);

            case 'R':

                if (!$this->is_setup) {
                    $this->setup();
                }
                return new DeleteAction($import_id, $chunk, $this->mapper);

            case 'P':

                if (!$this->is_setup) {
                    $this->setup();
                }
                return new CompleteAction($import_id);

            default:

                if (!$this->is_setup) {
                    $this->setup();
                }
                return new ImportAction($chunk, $this->data_parser, $this->importer);
        }
    }

    protected function setup()
    {
        /**
         * @var \WPDB
         */
        global $wpdb;

        $import_table = DB::get_table_name('import');
        $importer_id = $wpdb->get_var("SELECT `importer_id` FROM {$import_table}");
        $this->importer_data = $importer_data = $this->importer_manager->get_importer($importer_id);

        // TODO: cant run this from here.
        $this->importer_manager->event_handler->run('importer_manager.import', [$importer_data]);

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

    public function teardown()
    {
        /**
         * @var Properties $properties
         */
        $properties = Container::getInstance()->get('properties');

        // rotate files to not fill up server
        $this->importer_data->limit_importer_files($properties->file_rotation);
        $this->importer_manager->prune_importer_logs($this->importer_data, $properties->log_rotation);

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
