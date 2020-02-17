<?php

namespace ImportWP\Common\Importer;

use Exception;
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
use ImportWP\Common\Importer\Template\TermTemplate;
use ImportWP\Common\Importer\Template\UserTemplate;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Util\Logger;

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

    public function __construct(ImporterStatusManager $importer_status_manager, Filesystem $filesystem)
    {
        $this->importer_status_manager = $importer_status_manager;
        $this->filesystem = $filesystem;
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
            $result[] = new ImporterModel($post);
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

        return new ImporterModel($id);
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

        return $file->getRecordCount();
    }

    public function process_xml_file($id, $tmp = false)
    {

        $importer = $this->get_importer($id);
        $config = $this->get_config($importer->getId(), $tmp);

        $filePath = $importer->getFile();
        $file = new XMLFile($filePath, $config);
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
     * @param string $filepath
     * @return void
     */
    public function link_importer_file($id, $filepath)
    {
        $importer = $this->get_importer($id);
        $index = get_post_meta($importer->getId(), '_importer_files', true);
        if (!$index) {
            $index = 1;
        }

        $index++;

        update_post_meta($importer->getId(), '_importer_files', $index);
        update_post_meta($importer->getId(), '_importer_file_' . $index, $filepath);
        return $index;
    }

    public function upload_file($id, $file)
    {

        $importer = $this->get_importer($id);
        $result = $this->filesystem->upload_file($file, $importer->getAllowedFileTypes());

        if (is_wp_error($result)) {
            return $result;
        }

        $attachment_id = $this->insert_file_attachment($importer, $result['dest'], $result['type']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $importer->setFileId($attachment_id);
        $importer->save();

        return $attachment_id;
    }

    public function remote_file($id, $source, $filetype = null)
    {
        $importer = $this->get_importer($id);
        $result = $this->filesystem->download_file($source, $filetype, $importer->getAllowedFileTypes());

        if (is_wp_error($result)) {
            return $result;
        }

        $attachment_id = $this->insert_file_attachment($importer, $result['dest'], $result['type']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $importer->setFileId($attachment_id);
        $importer->save();

        return $attachment_id;
    }

    public function local_file($id, $source)
    {
        $importer = $this->get_importer($id);
        $result = $this->filesystem->copy_file($source, $importer->getAllowedFileTypes());

        if (is_wp_error($result)) {
            return $result;
        }

        $attachment_id = $this->insert_file_attachment($importer, $result['dest'], $result['type']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $importer->setFileId($attachment_id);
        $importer->save();

        return $attachment_id;
    }

    private function insert_file_attachment($id, $dest, $type)
    {
        $importer = $this->get_importer($id);

        $result = $this->link_importer_file($id, $dest);
        if (!$result) {
            return new \WP_Error('IWP_IM_01', 'Unable to link importer file');
        }

        if (is_null($importer->getParser())) {
            $importer->setParser($type);
        }

        return $result;
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

    public function get_config($id, $tmp = false)
    {
        $config_path = $this->get_config_path($id, $tmp);
        return new Config($config_path);
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
        Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -session=' . $session);

        $importer_status = $this->importer_status_manager->get_importer_status($importer_data, $session);
        Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -status=' . print_r($importer_status, true));

        try {

            if (!$importer_status) {
                $importer_post = get_post($importer_data->getId());
                throw new \Exception("Unable to read importer session: (" . $importer_post->post_excerpt . ")");
            }

            // clear config before import
            $is_init = $importer_status->has_status('init');
            if ($is_init) {
                $set_time_limit = set_time_limit(0);
                Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' : -set-time-limit=' . ($set_time_limit === true ? 'yes' : 'no') . ' -time-limit=' . intval(ini_get('max_execution_time')));

                Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' : Clearing config files');
                $this->clear_config_files($id, false, true);
            }

            $config = $this->get_config($importer_data);

            // file encoding
            $config->set('file_encoding', apply_filters('iwp/importer/file_encoding', false, $importer_data));

            // template
            $template = $this->get_importer_template($importer_data);
            $template->register_hooks($importer_data);

            // permission
            $permission = new Permission($importer_data);

            // mapper
            $mapper = $this->get_importer_mapper($importer_data, $template, $permission);

            // get data
            $config->set('data', [
                'default' => [
                    'fields' => $template->field_map($importer_data->getMap())
                ]
            ]);

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
            Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' : Get record count');
            $end = $parser->file()->getRecordCount();

            Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -count=' . $end);

            if ($importer_status->has_status('init')) {

                Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -new');

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

                Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -start=' . $start . ' -end=' . $end);

                $importer_status->set_status('running');
                $importer_status->set_section('importing');
                $importer_status->save();
            } elseif ($importer_status->has_status('timeout')) { // || $importer_status->has_status('paused')) {

                Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -resume');

                // TODO: continue from where we left off
                $start = $importer_status->get_counter();
                $end = $importer_status->get_total();

                Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -start=' . $start . ' -end=' . $end);

                $importer_status->set_status('running');
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

            Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -complete');
        } catch (Exception $e) {

            // TODO: Missing template errors are currently not being logged to history, possibly others?
            Logger::write(__CLASS__ . '::import -id=' . $importer_data->getId() . ' -error=' . $e->getMessage());
            $importer_status->record_fatal_error($e->getMessage());
            $importer_status->save();
            $importer_status->write_to_file();
        }

        return $importer_status;
    }

    public function get_importer_template($id)
    {
        $importer = $this->get_importer($id);

        $templates = $this->get_templates();
        $template_name = $importer->getTemplate();

        if (!isset($templates[$template_name])) {
            throw new \Exception("Unable to locate importer template: " . $template_name);
        }

        return new $templates[$template_name];
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
        switch ($mapper_name) {
            case 'post':
                return new PostMapper($importer, $template, $permission);
                break;
            case 'user':
                return new UserMapper($importer, $template, $permission);
                break;
            case 'term':
                return new TermMapper($importer, $template, $permission);
                break;
        }
    }

    public function get_templates()
    {
        return [
            'post' => PostTemplate::class,
            'page' => PageTemplate::class,
            'user' => UserTemplate::class,
            'term' => TermTemplate::class,
            'custom-post-type' => CustomPostTypeTemplate::class,
        ];
    }
}
