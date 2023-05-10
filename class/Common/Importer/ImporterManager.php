<?php

namespace ImportWP\Common\Importer;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\Mapper\PostMapper;
use ImportWP\Common\Importer\Mapper\TermMapper;
use ImportWP\Common\Importer\Mapper\UserMapper;
use ImportWP\Common\Importer\Parser\CSVParser;
use ImportWP\Common\Importer\Parser\XMLParser;
use ImportWP\Common\Importer\Permission\Permission;
use ImportWP\Common\Importer\State\ImporterState;
use ImportWP\Common\Importer\Template\CustomPostTypeTemplate;
use ImportWP\Common\Importer\Template\PageTemplate;
use ImportWP\Common\Importer\Template\PostTemplate;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Importer\Template\TermTemplate;
use ImportWP\Common\Importer\Template\UserTemplate;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Runner\ImporterRunnerState;
use ImportWP\Common\Util\Logger;
use ImportWP\Common\Util\Util;
use ImportWP\Container;
use ImportWP\EventHandler;

class ImporterManager
{

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var TemplateManager $template_manager
     */
    private $template_manager;

    /**
     * @var EventHandler $event_handler
     */
    protected $event_handler;

    public function __construct(Filesystem $filesystem, TemplateManager $template_manager, EventHandler $event_handler)
    {
        $this->filesystem = $filesystem;
        $this->template_manager = $template_manager;
        $this->event_handler = $event_handler;
    }

    /**
     * Get Importers
     *
     * @return ImporterModel[]
     */
    public function get_importers()
    {

        $result = array();
        $query  = new \WP_Query(array(
            'post_type'      => IWP_POST_TYPE,
            'posts_per_page' => -1,
        ));

        foreach ($query->posts as $post) {
            $result[] = $this->get_importer($post);
        }
        return $result;
    }

    /**
     * Get Importer
     *
     * @param int $id
     * @return ImporterModel
     */
    public function get_importer($id)
    {
        if ($id instanceof ImporterModel) {
            return $id;
        }

        if (IWP_POST_TYPE !== get_post_type($id)) {
            return false;
        }

        return new ImporterModel($id, $this->is_debug());
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

    public function set_current_user($id)
    {
        $importer_model = $this->get_importer($id);
        $user_id = $importer_model->getUserId();

        Logger::write('set_current_user -user=' . $user_id, $importer_model->getId());

        if ($user_id) {
            return wp_set_current_user($user_id);
        }

        return false;
    }

    /**
     * Delete Importer
     *
     * @param int $id
     * @return void
     */
    public function delete_importer($id)
    {
        $importer = $this->get_importer($id);
        $importer->delete();
    }

    public function get_file($id)
    {
        $importer = $this->get_importer($id);
        $config = $this->get_config($importer);
        $parser = $importer->getParser();

        if ('xml' === $parser) {
            return $this->get_xml_file($importer, $config);
        } elseif ('csv' === $parser) {
            return $this->get_csv_file($importer, $config);
        }

        return false;
    }

    public function get_csv_file($id, $config)
    {
        $importer = $this->get_importer($id);
        $file = new CSVFile($importer->getFile(), $config);
        $file->setDelimiter($importer->getFileSetting('delimiter'));
        $file->setEnclosure($importer->getFileSetting('enclosure'));
        return $file;
    }

    public function get_xml_file($id, $config)
    {
        $importer = $this->get_importer($id);
        $file = new XMLFile($importer->getFile(), $config);
        $file->setRecordPath($importer->getFileSetting('base_path'));
        return $file;
    }

    public function preview_csv_file($id, $fields = [], $row = 0)
    {
        $importer = $this->get_importer($id);
        $config = $this->get_config($importer, true);

        $file = $this->get_csv_file($importer, $config);
        $parser = new CSVParser($file);

        $record = $parser->getRecord($row);

        return $record->queryGroup(['fields' => $fields]);
    }

    public function preview_xml_file($id, $fields = [], $row = 0)
    {
        $importer = $this->get_importer($id);
        $config = $this->get_config($importer, true);

        $file = $this->get_xml_file($importer, $config);
        $parser = new XMLParser($file);

        $record = $parser->getRecord($row);
        return $record->queryGroup(['fields' => $fields]);
    }

    public function process_csv_file($id, $delimiter, $enclosure, $tmp = false)
    {
        $importer = $this->get_importer($id);
        $config = $this->get_config($importer->getId(), $tmp);

        $file = $this->get_csv_file($importer, $config);
        $file->setDelimiter($delimiter);
        $file->setEnclosure($enclosure);
        $file->processing(true);

        if (empty($importer->getMap())) {

            // we are on first file import
            $headings = str_getcsv($file->getRecord(0), $file->getDelimiter(), $file->getEnclosure());
            $headings = array_map('trim', $headings);

            $template = $this->get_importer_template($id);
            $field_map = $template->generate_field_map($headings, $importer);
            $field_map = apply_filters('iwp/importer/generate_field_map', $field_map, $headings, $importer);

            $map = $field_map['map'];
            foreach ($map as $key => $value) {
                $importer->setMap($key, $value);
            }

            $enabled = $field_map['enabled'];
            foreach ($enabled as $enabled_field) {
                $importer->setEnabled($enabled_field);
            }

            // Disabled due to testing: 
            // https://localdev/wp-admin/tools.php?page=importwp&edit=29&step=1
            // 
            // a:9:{s:8:"template";s:19:"woocommerce-product";s:13:"template_type";s:0:"";s:4:"file";a:2:{s:2:"id";i:9;s:8:"settings";a:6:{s:9:"enclosure";s:1:""";s:9:"delimiter";s:1:",";s:13:"show_headings";b:1;s:5:"setup";b:0;s:5:"count";i:2;s:9:"processed";b:1;}}s:10:"datasource";a:2:{s:4:"type";s:5:"local";s:8:"settings";a:2:{s:10:"remote_url";s:0:"";s:9:"local_url";s:48:"/var/www/html/wp-content/uploads/exportwp/30.csv";}}s:6:"parser";s:3:"csv";s:3:"map";a:0:{}s:7:"enabled";a:0:{}s:11:"permissions";a:0:{}s:8:"settings";a:4:{s:9:"post_type";a:2:{i:0;s:7:"product";i:1;s:17:"product_variation";}s:12:"unique_field";a:3:{i:0;s:2:"ID";i:1;s:4:"_sku";i:2;s:9:"post_name";}s:9:"start_row";N;s:7:"max_row";N;}}
            // 
            $importer->save();
        }

        return $file->getRecordCount();
    }

    public function process_xml_file($id, $tmp = false)
    {

        $importer = $this->get_importer($id);
        $config = $this->get_config($importer->getId(), $tmp);

        $filePath = $importer->getFile();
        $file = new XMLFile($filePath, $config);
        $file->processing(true);
        $nodes = $file->get_node_list();
        $results = [];

        foreach ($nodes as $node) {
            $config = $this->get_config($importer->getId(), $tmp);
            $file = new XMLFile($filePath, $config);
            $file->setRecordPath($node);
            // TODO: Seperate record count, to when a node has been selected.
            $results[$node] = 0; //$file->getRecordCount();
        }

        return $results;
    }

    /**
     * Link import file to importer via post meta
     *
     * @param ImporterModel $id
     * @param string $file_path
     * @return integer Id of inserted file
     */
    public function link_importer_file($id, $file_path)
    {
        $importer = $this->get_importer($id);
        $index = get_post_meta($importer->getId(), '_importer_files', true);
        if (!$index) {
            $index = 1;
        }

        $index++;

        update_post_meta($importer->getId(), '_importer_files', $index);
        update_post_meta($importer->getId(), '_importer_file_' . $index, $file_path);
        return $index;
    }

    public function get_importer_file_prefix($importer)
    {
        $importer = $this->get_importer($importer);

        $file_index = get_post_meta($importer->getId(), '_importer_files', true);
        if (!$file_index) {
            $file_index = 1;
        }
        $file_index++;

        return $importer->getId() . '-' . intval($file_index) . '-';
    }

    public function upload_file($id, $file)
    {
        $importer = $this->get_importer($id);
        Logger::setId($importer->getId());

        $allowed_file_types = $this->event_handler->run('importer.allowed_file_types', [$importer->getAllowedFileTypes()]);
        $result = $this->filesystem->upload_file($file, $allowed_file_types);

        if (is_wp_error($result)) {
            return $result;
        }

        $attachment_id = $this->insert_file_attachment($importer, $result['dest'], $result['type']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        return $attachment_id;
    }

    public function remote_file($id, $source, $filetype = null)
    {
        $importer = $this->get_importer($id);
        Logger::setId($importer->getId());

        $allowed_file_types = $this->event_handler->run('importer.allowed_file_types', [$importer->getAllowedFileTypes()]);
        $prefix = $this->get_importer_file_prefix($importer);

        if (preg_match('/^s?ftp?:\/\//', $source) === 1) {

            if (preg_match('/^(?<protocol>s?ftp):\/\/(?:(?<user>[^\:@]+)(?:\:(?<pass>[^@]+))?@)?(?<host>[^\:\/]+)(?:\:(?<port>[0-9]+))?(?:\/(?<path>.*))$/', $source, $matches) !== 1) {

                return new \WP_Error("IM_RM_FTP_PARSE", "Unable to parse FTP connection string");
            }

            $user = isset($matches['user']) ? urldecode($matches['user']) : '';
            $pass = isset($matches['pass']) ? urldecode($matches['pass']) : '';
            $host = isset($matches['host']) ? $matches['host'] : false;
            $port = isset($matches['port']) && !empty($matches['port']) ? $matches['port'] : intval(21);
            $path = isset($matches['path']) ? $matches['path'] : false;

            if (!$host) {
                return new \WP_Error("IM_RM_FTP_HOST", "Unable to parse ftp host from connection string");
            }

            if (!$path) {
                return new \WP_Error("IM_RM_FTP_HOST", "Unable to parse ftp host from connection string");
            }

            /**
             * @var \ImportWP\Common\Ftp\Ftp $ftp
             */
            $ftp = Container::getInstance()->get('ftp');
            $result = $ftp->download_file($path, $host, $user, $pass, false, $port);
        } else {

            $result = $this->filesystem->download_file($source, $filetype, $allowed_file_types, null, $prefix);
        }


        if (is_wp_error($result)) {
            return $result;
        }

        $attachment_id = $this->insert_file_attachment($importer, $result['dest'], $result['type']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        return $attachment_id;
    }

    public function local_file($id, $source)
    {
        $importer = $this->get_importer($id);
        Logger::setId($importer->getId());

        $allowed_file_types = $this->event_handler->run('importer.allowed_file_types', [$importer->getAllowedFileTypes()]);
        $prefix = $this->get_importer_file_prefix($importer);
        $result = $this->filesystem->copy_file($source, $allowed_file_types, null, $prefix);

        if (is_wp_error($result)) {
            return $result;
        }

        $attachment_id = $this->insert_file_attachment($importer, $result['dest'], $result['type']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        return $attachment_id;
    }

    /**
     * Add uploaded file to importer
     * 
     * Insert file record in database, set as current import file.
     * 
     * @param integer|ImporterModel $id Importer to attach file 
     * @param string $file_path File location on server
     * @param string $file_type Type of file
     * 
     * @return \WP_Error|int Id of file
     */
    private function insert_file_attachment($id, $file_path, $file_type)
    {
        $importer_model = $this->get_importer($id);
        Logger::setId($importer_model->getId());

        // Allow the modification of file path
        $file_path = wp_normalize_path($file_path);
        $file_path = apply_filters('iwp/importer/file_uploaded/file_path', $file_path, $importer_model);

        $file_id = $this->link_importer_file($id, $file_path);
        if (!$file_id) {
            return new \WP_Error('IWP_IM_01', 'Unable to link importer file');
        }

        if (is_null($importer_model->getParser())) {
            $importer_model->setParser($file_type);
        }

        $importer_model->setFileId($file_id);
        $importer_model->save();

        do_action('iwp/importer/file_uploaded', $file_path, $importer_model);

        return $file_id;
    }

    /**
     * Clear config files
     *
     * @param int $id
     * @return void
     */
    public function clear_config_files($id, $tmp = false, $all = true)
    {
        $config_path = $this->get_config_path($id, $tmp);
        if (file_exists($config_path)) {

            unlink($config_path);
            if (true === $all) {
                foreach (glob($config_path . '*') as $file) {

                    // don't remove status file
                    if (basename($file) === basename($config_path) . '.status') {
                        continue;
                    }

                    unlink($file);
                }
            }
        }
    }

    public function get_config($importer, $tmp = false)
    {
        $config_path = $this->get_config_path($importer, $tmp);
        $config = new Config($config_path);


        $importer = $this->get_importer($importer);
        $file_encoding = $importer->getFileSetting('file_encoding');

        // file encoding
        $config->set('file_encoding', apply_filters('iwp/importer/file_encoding', $file_encoding, $importer));

        return $config;
    }

    public function get_session_path($id, $session, $max_depth = 4)
    {
        $base = $this->filesystem->get_temp_directory() . DIRECTORY_SEPARATOR;

        $base .= str_pad($id, 2, STR_PAD_LEFT) . DIRECTORY_SEPARATOR;
        if (!file_exists($base)) {
            if (!is_writable(dirname($base)) || !mkdir($base)) {
                throw new \Exception("Unable to create directory: " . $base);
            }
        }

        for ($i = 0; $i < ceil(strlen($session) / 2); $i++) {
            $base .= substr($session, $i * 2, 2) . DIRECTORY_SEPARATOR;
            if (!file_exists($base)) {

                if (!is_writable(dirname($base)) || !mkdir($base)) {
                    throw new \Exception("Unable to create directory: " . $base);
                }
            }

            if ($i >= $max_depth - 2) {
                break;
            }
        }
        return $base;
    }

    public function get_config_path($id, $tmp = false, $session_id = null)
    {
        $importer = $this->get_importer($id);

        $key = 'config-%d.json';
        if ($tmp) {
            $key = 'temp-config-%d.json';
        }

        $base = !is_null($session_id) ? $this->get_session_path($id, $session_id) : $this->filesystem->get_temp_directory() . DIRECTORY_SEPARATOR;

        return $base . sprintf($key, $importer->getId());
    }

    public function import($id, $user, $session = null)
    {
        Logger::timer();

        $importer_data = $this->get_importer($id);
        $importer_id = $importer_data->getId();

        $config_data = get_site_option('iwp_importer_config_' . $importer_id, []);

        $this->event_handler->run('importer_manager.import', [$importer_data]);

        $state = new ImporterState($importer_id, $user);

        try {

            Logger::debug('IM -init_state');
            $state->init($session);

            if ($state->has_status('init')) {
                Logger::debug('IM -clear_config_files');
                $this->clear_config_files($importer_id, false, true);
                $config_data['features'] = [
                    'session_table' => true
                ];
            }

            Logger::debug('IM -get_config');
            $config = $this->get_config($importer_data);

            // template
            Logger::debug('IM -get_importer_template');
            $template = $this->get_importer_template($importer_data);

            Logger::debug('IM -register_hooks');
            $template->register_hooks($importer_data);

            // permission
            Logger::debug('IM -permissions');
            $permission = new Permission($importer_data);

            // mapper
            Logger::debug('IM -get_importer_mapper');
            $mapper = $this->get_importer_mapper($importer_data, $template, $permission);

            if ($state->has_status('init')) {

                Logger::debug('IM -generate_config');

                $config_data['data'] = $template->config_field_map($importer_data->getMap());
                $config->set('data', $config_data['data']);

                $config_data['id'] = $state->get_session();

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
            }

            $start = 0;

            // get parser
            if ($importer_data->getParser() === 'csv') {
                Logger::debug('IM -get_csv_file');
                $file = $this->get_csv_file($importer_data, $config);
                Logger::debug('IM -load_parser');
                $parser = new CSVParser($file);
                if (true === $importer_data->getFileSetting('show_headings')) {
                    $start = 1;
                }
            } elseif ($importer_data->getParser() === 'xml') {
                Logger::debug('IM -get_xml_file');
                $file = $this->get_xml_file($importer_data, $config);
                Logger::debug('IM -load_parser');
                $parser = new XMLParser($file);
            } else {
                $parser = apply_filters('iwp/importer/init_parser', false, $importer_data, $config);
            }

            if ($state->has_status('init')) {

                Logger::debug('IM -get_record_count');
                $end = $parser->file()->getRecordCount();

                $config_data['start'] = $this->get_start($importer_data, $start);
                $config_data['end'] = $this->get_end($importer_data, $config_data['start'], $end);

                update_site_option('iwp_importer_config_' . $importer_id, $config_data);

                Logger::debug('IM -update_state');
                $state->update(function ($state) use ($config_data) {

                    Logger::debug('IM -update_state=running');

                    $state['id'] = $config_data['id'];
                    $state['status'] = 'running';
                    $state['progress']['import']['start'] = $config_data['start'];
                    $state['progress']['import']['end'] = $config_data['end'];
                    return $state;
                });

                Logger::debug('IM -write_status_session_to_file');
                Util::write_status_session_to_file($id, $state);

                do_action('iwp/importer/init', $importer_data);
            }

            Logger::debug('IM -import');
            $importer = new \ImportWP\Common\Importer\Importer($config);
            $importer->parser($parser);
            $importer->mapper($mapper);
            $importer->from($config_data['start']);
            $importer->to($config_data['end']);
            $importer->filter($importer_data->getFilters());
            $importer->import($importer_id, $user, $state);
            Logger::debug('IM -import_complete');
        } catch (\Exception $e) {

            // TODO: Missing template errors are currently not being logged to history, possibly others?
            Logger::error('import -error=' . $e->getMessage(), $importer_id);
            return $state->error($e)->get_raw();
        }

        /**
         * @var Properties $properties
         */
        $properties = Container::getInstance()->get('properties');

        // rotate files to not fill up server
        $importer_data->limit_importer_files($properties->file_rotation);
        $this->prune_importer_logs($importer_data, $properties->log_rotation);

        $template->unregister_hooks();

        $this->event_handler->run('importer_manager.import_shutdown', [$importer_data]);

        return $state->update(function ($data) {
            $data['duration'] = floatval($data['duration']) + Logger::timer();
            return $data;
        })->get_raw();
    }

    public function get_start($importer_data, $start)
    {
        $tmp_start = $importer_data->getStartRow();
        if (!is_null($tmp_start) && "" !== $tmp_start) {
            $tmp_start = intval($tmp_start);

            if ($tmp_start > $start) {
                $start = $tmp_start;
            }
        }

        return $start;
    }

    public function get_end($importer_data, $start, $end)
    {
        $tmp_max_row = $importer_data->getMaxRow();
        if (!is_null($tmp_max_row) && $tmp_max_row !== '') {
            $tmp_end = $start + intval($tmp_max_row);
            if ($tmp_end < $end) {
                $end = $tmp_end;
            }
        }

        return $end;
    }

    public function get_importer_template($id)
    {
        $importer_model = $this->get_importer($id);
        $templates = $this->get_templates();
        $template_name = $importer_model->getTemplate();

        if (!isset($templates[$template_name])) {
            $exception_msg = "Unable to locate importer template: " . $template_name;
            Logger::error('import -get_importer_template=' . $exception_msg, $importer_model->getId());
            throw new \Exception($exception_msg);
        }

        return $this->template_manager->load_template($templates[$template_name]);
    }

    /**
     * Get importer mapper
     *
     * @param int $id
     * @param Template $template
     * @param Permission $permission
     * @return MapperInterface
     */
    public function get_importer_mapper($id, $template, $permission = null)
    {
        $importer = $this->get_importer($id);
        $mapper_name = $template->get_mapper();

        $mappers = $this->get_mappers();
        return isset($mappers[$mapper_name]) ? new $mappers[$mapper_name]($importer, $template, $permission) : false;
    }

    public function get_mappers()
    {
        $mappers = $this->event_handler->run('mappers.register', [[]]); // apply_filters('iwp/mappers/register', []);
        $mappers = array_merge($mappers, [
            'post' => PostMapper::class,
            'user' => UserMapper::class,
            'term' => TermMapper::class
        ]);
        return $mappers;
    }

    public function get_mapper($key)
    {
        $mappers = $this->get_mappers();
        if (!isset($mappers[$key])) {
            return new \WP_Error('IWP_IM_1', 'Unable to locate mapper: ' . $key);
        }

        return $mappers[$key];
    }

    public function get_templates()
    {
        $templates = $this->event_handler->run('templates.register', [[]]);
        $templates = array_merge($templates, [
            'post' => PostTemplate::class,
            'page' => PageTemplate::class,
            'user' => UserTemplate::class,
            'term' => TermTemplate::class,
            'custom-post-type' => CustomPostTypeTemplate::class,
        ]);
        return $templates;
    }

    public function get_template($key)
    {
        $templates = $this->get_templates();
        if (!isset($templates[$key])) {
            return new \WP_Error('IWP_IM_1', 'Unable to locate template: ' . $key);
        }

        return $templates[$key];
    }

    public function get_importer_debug_log(ImporterModel $importer_data, $page = 0, $per_page = -1)
    {
        $file_path = Logger::getLogFile($importer_data->getId());

        $line_counter = 0;
        $lines = [];
        $start = $end = -1;

        if ($per_page > 0) {
            $start = ($page - 1) * $per_page;
            $end =  $start + $per_page;
        }

        if (file_exists($file_path)) {
            $fh = fopen($file_path, 'r');
            if ($fh !== false) {
                while (($data = fgetcsv($fh)) !== false) {
                    if ($line_counter === $end) {
                        return $lines;
                    } elseif ($per_page === -1 || $line_counter >= $start) {
                        $lines[] = $data;
                    }

                    $line_counter++;
                }
                fclose($fh);
            }
        }
        return $lines;
    }

    public function prune_importer_logs($importer_model, $limit)
    {
        $limit = intval($limit);
        if ($limit <= -1) {
            return;
        }

        $file_path = Util::get_importer_status_file_path($importer_model->getId());
        $tmp_file_path = Util::get_importer_status_file_path($importer_model->getId()) . '.tmp';
        $lines = $this->get_importer_logs($importer_model);

        if (count($lines) > $limit) {

            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
            $fileSystemDirect = new \WP_Filesystem_Direct(false);

            $fh = fopen($tmp_file_path, 'w');
            for ($i = 0; $i < count($lines); $i++) {

                if ($i < count($lines) - $limit) {
                    $log_file_path = Util::get_importer_log_file_path($importer_model->getId(), $lines[$i]['id']);
                    if ($fileSystemDirect->exists($log_file_path)) {

                        $fileSystemDirect->delete($log_file_path);

                        $tmp = $log_file_path;
                        for ($j = 0; $j < 3; $j++) {
                            $tmp = dirname($tmp);
                            $sub_files = $fileSystemDirect->dirlist($log_file_path);
                            if (empty($sub_files)) {
                                $fileSystemDirect->rmdir($tmp);
                            }
                        }
                    }
                } else {
                    fputs($fh, json_encode($lines[$i]) . "\n");
                }
            }
            fclose($fh);

            if ($fileSystemDirect->move($tmp_file_path, $file_path, true)) {
                $fileSystemDirect->delete($tmp_file_path);
            }
        }
    }

    public function get_importer_logs(ImporterModel $importer_data, $page = 0, $per_page = -1)
    {
        $file_path = Util::get_importer_status_file_path($importer_data->getId());

        $line_counter = 0;
        $lines = [];

        $start = $end = -1;

        if ($per_page > 0) {
            $start = ($page - 1) * $per_page;
            $end =  $start + $per_page;
        }

        if (file_exists($file_path)) {
            $fh = fopen($file_path, 'r');
            if ($fh !== false) {
                while (($data = fgets($fh)) !== false) {
                    if ($data[strlen($data) - 1] === "\n") {
                        $data = substr($data, 0, strlen($data) - 1);
                    }

                    if ($line_counter === $end) {
                        return $lines;
                    } elseif ($per_page === -1 || $line_counter >= $start) {
                        $lines[] = json_decode($data, true);
                    }

                    $line_counter++;
                }
                fclose($fh);
            }
        }
        return $lines;
    }

    public function get_importer_log(ImporterModel $importer_data, $session_id, $page = 0, $per_page = -1)
    {
        $file_path = Util::get_importer_log_file_path($importer_data->getId(), $session_id);

        $line_counter = 0;
        $lines = [];
        $start = $end = -1;

        if ($per_page > 0) {
            $start = ($page - 1) * $per_page;
            $end =  $start + $per_page;
        }

        if (file_exists($file_path)) {
            $fh = fopen($file_path, 'r');
            if ($fh !== false) {
                while (($data = fgetcsv($fh)) !== false) {
                    if ($line_counter === $end) {
                        return $lines;
                    } elseif ($per_page === -1 || $line_counter >= $start) {
                        $lines[] = $data;
                    }

                    $line_counter++;
                }
                fclose($fh);
            }
        }
        return $lines;
    }

    public function get_importer_status_report(ImporterModel $immpoter_data, $session)
    {
        $logs = $this->get_importer_logs($immpoter_data);
        foreach ($logs as $log) {
            if ($log && isset($log['id']) && $log['id'] === $session) {
                return $log;
            }
        }
        return false;
    }
}
