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
use ImportWP\Common\Importer\Template\CustomPostTypeTemplate;
use ImportWP\Common\Importer\Template\PageTemplate;
use ImportWP\Common\Importer\Template\PostTemplate;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Importer\Template\TermTemplate;
use ImportWP\Common\Importer\Template\UserTemplate;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Util\Logger;
use ImportWP\EventHandler;

class ImporterManager
{

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var ImporterStatusManager
     */
    private $importer_status_manager;

    /**
     * @var TemplateManager $template_manager
     */
    private $template_manager;

    /**
     * @var EventHandler $event_handler
     */
    protected $event_handler;

    public function __construct(ImporterStatusManager $importer_status_manager, Filesystem $filesystem, TemplateManager $template_manager, EventHandler $event_handler)
    {
        $this->importer_status_manager = $importer_status_manager;
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

    public function upload_file($id, $file)
    {

        $importer = $this->get_importer($id);
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
        $allowed_file_types = $this->event_handler->run('importer.allowed_file_types', [$importer->getAllowedFileTypes()]);
        $result = $this->filesystem->download_file($source, $filetype, $allowed_file_types);

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
        $allowed_file_types = $this->event_handler->run('importer.allowed_file_types', [$importer->getAllowedFileTypes()]);
        $result = $this->filesystem->copy_file($source, $allowed_file_types);

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

        // Allow the modification of file path
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
            mkdir($base);
        }

        for ($i = 0; $i < ceil(strlen($session) / 2); $i++) {
            $base .= substr($session, $i * 2, 2) . DIRECTORY_SEPARATOR;
            if (!file_exists($base)) {
                mkdir($base);
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

    public function import($id, $session)
    {
        $importer_data = $this->get_importer($id);
        Logger::write(__CLASS__ . '::import -session=' . $session, $importer_data->getId());

        $importer_status = $this->importer_status_manager->get_importer_status($importer_data, $session);
        Logger::write(__CLASS__ . '::import -status=' . $importer_status->get_status(), $importer_data->getId());

        try {

            if (!$importer_status) {
                $importer_post = get_post($importer_data->getId());
                $exception_msg = "Unable to read importer session: (" . $importer_post->post_excerpt . ")";
                Logger::write(__CLASS__ . '::import -error=' . $exception_msg);
                throw new \Exception($exception_msg);
            }

            $this->event_handler->run('importer_manager.import', [$importer_data]);

            // clear config before import
            $is_init = $importer_status->has_status('init');
            if ($is_init) {
                $set_time_limit = set_time_limit(0);
                Logger::write(__CLASS__ . '::import -set-time-limit=' . ($set_time_limit === true ? 'yes' : 'no') . ' -time-limit=' . intval(ini_get('max_execution_time')), $importer_data->getId());
                $this->clear_config_files($id, false, true);
            }

            $config = $this->get_config($importer_data);

            // template
            $template = $this->get_importer_template($importer_data);
            $template->register_hooks($importer_data);

            // permission
            $permission = new Permission($importer_data);

            // mapper
            $mapper = $this->get_importer_mapper($importer_data, $template, $permission);

            // TODO: allow for field groups to set 'base'
            //
            // If base is set on field group, add it to the group `default::$index`
            // This will allow for it to be processed and put back into the default
            // group for processing.

            $template_fields = $template->field_map($importer_data->getMap());
            $field_groups = [
                'default' => [
                    'id' => 'default',
                    'fields' => []
                ]
            ];

            foreach ($template_fields as $key => $value) {

                if (empty($value) || preg_match('/\.row_base$/', $key) !== 1) {
                    continue;
                }

                $group_id = substr($key, 0, strlen($key) - strlen('.row_base'));

                $field_groups[$group_id] = [
                    'id' => $group_id,
                    'base' => $value,
                    'fields' => []
                ];
            }

            foreach ($template_fields as $field_id => $field_value) {

                $found = false;

                foreach ($field_groups as $group_prefix => $group_settings) {
                    if (false === strpos($field_id, $group_prefix) || preg_match('/\.row_base$/', $field_id) === 1) {
                        continue;
                    }

                    $field_groups[$group_prefix]['fields'][$field_id] = $field_value;
                    $found = true;
                }

                if (false === $found) {
                    $field_groups['default']['fields'][$field_id] = $field_value;
                }
            }

            // get data
            $config->set('data', $field_groups);

            $start = 0;

            // get parser
            if ($importer_data->getParser() === 'csv') {
                $file = $this->get_csv_file($importer_data, $config);
                $parser = new CSVParser($file);
                if (true === $importer_data->getFileSetting('show_headings')) {
                    $start = 1;
                }
            } else {
                $file = $this->get_xml_file($importer_data, $config);
                $parser = new XMLParser($file);
            }

            // TODO: if end <= start it imports all, should throw an error instead.
            $end = $parser->file()->getRecordCount();
            Logger::write(__CLASS__ . '::import -record-count=' . $end, $importer_data->getId());

            if ($importer_status->has_status('init')) {

                Logger::write(__CLASS__ . '::import -new', $importer_data->getId());

                $tmp_start = $importer_data->getStartRow();
                if (!is_null($tmp_start) && "" !== $tmp_start) {
                    $tmp_start = intval($tmp_start);
                    if ($tmp_start > $start) {
                        $start = $tmp_start;
                    }
                }

                $tmp_max_row = $importer_data->getMaxRow();
                if (!is_null($tmp_max_row) && $tmp_max_row !== '') {
                    $tmp_end = $start + intval($tmp_max_row);
                    if ($tmp_end < $end) {
                        $end = $tmp_end;
                    }
                }

                $importer_status->set_start($start);
                $importer_status->set_end($end);

                Logger::write(__CLASS__ . '::import -start=' . $start . ' -end=' . $end, $importer_data->getId());

                $importer_status->set_status('running');
                $importer_status->set_section('importing');
                $importer_status->save();
            } elseif ($importer_status->has_status('timeout')) { // || $importer_status->has_status('paused')) {

                Logger::write(__CLASS__ . '::import -resume', $importer_data->getId());

                // TODO: continue from where we left off
                $start = $importer_status->get_counter();
                $end = $importer_status->get_total();

                Logger::write(__CLASS__ . '::import -start=' . $start . ' -end=' . $end, $importer_data->getId());

                $importer_status->set_status('running');

                // clear paused flag
                delete_post_meta($importer_data->getId(), '_iwp_paused_' . $session);

                $importer_status->save();
            }

            $importer = new \ImportWP\Common\Importer\Importer($config);
            $importer->parser($parser);
            $importer->mapper($mapper);
            $importer->status($importer_status);
            $importer->from($start);
            $importer->to($end);
            $importer->import();

            $template->unregister_hooks();

            Logger::write(__CLASS__ . '::import -complete', $importer_data->getId());
        } catch (\Exception $e) {

            // TODO: Missing template errors are currently not being logged to history, possibly others?
            Logger::write(__CLASS__ . '::import -error=' . $e->getMessage(), $importer_data->getId());
            $importer_status->record_fatal_error($e->getMessage());
            $importer_status->save();
            $importer_status->write_to_file();
        }

        $this->event_handler->run('importer_manager.import_shutdown', [$importer_data]);

        return $importer_status;
    }

    public function get_importer_template($id)
    {
        $importer_model = $this->get_importer($id);
        $templates = $this->get_templates();
        $template_name = $importer_model->getTemplate();

        if (!isset($templates[$template_name])) {
            $exception_msg = "Unable to locate importer template: " . $template_name;
            Logger::write(__CLASS__ . '::import -get_importer_template=' . $exception_msg, $importer_model->getId());
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
}
