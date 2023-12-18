<?php

namespace ImportWP\Common\Runner;

use ImportWP\Common\Importer\DataParser;
use ImportWP\Common\Importer\Exception\FileException;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Exception\ParserException;
use ImportWP\Common\Importer\Importer;
use ImportWP\Common\Util\Logger;
use ImportWP\Common\Util\Util;

class ImporterRunner extends Runner
{
    /**
     * @var Importer
     */
    protected $importer;

    /**
     * @var string
     */
    protected $object_type = 'importer';

    public function __construct($properties, $importer)
    {
        parent::__construct($properties);
        $this->importer = $importer;
    }

    public function setup($state, $state_data, $config, $user, $id)
    {
        $state->populate($state_data);

        if (!$state->validate($config['id'])) {
            throw new \Exception(__("Importer session has changed", 'jc-importer'));
        }

        if ($state->has_status('running')) {

            $section = $state->get_section();
            if (isset($state_data['progress'][$section]) && $state_data['progress'][$section]['end'] - $state_data['progress'][$section]['start'] <= $state_data['progress'][$section]['current_row']) {

                // Does this user or any user have any dangling jobs?
                $dangling = $this->try_process_dangling_state($id, $user, $state);
                if (!$dangling) {
                    $this->is_timeout = true;
                    $state_data['duration'] = floatval($state_data['duration']) + Logger::timer();
                    return $state_data;
                }

                switch ($state->get_section()) {
                    case 'import':

                        if ($this->importer->getMapper()->permission() && $this->importer->getMapper()->permission()->allowed_method('remove')) {

                            // importer delete
                            $state_data['section'] = 'delete';

                            // generate list of items to be deleted
                            $object_ids = $this->importer->getMapper()->get_objects_for_removal();

                            $config = get_site_option('iwp_importer_config_' . $id);
                            $config['delete_ids'] = $object_ids;
                            update_site_option('iwp_importer_config_' . $id, $config);

                            $state_data['progress']['delete']['start'] = 0;
                            $state_data['progress']['delete']['end'] = $object_ids ? count($object_ids) : 0;
                        } else {
                            $state_data['section'] = '';
                            $state_data['status'] = 'complete';
                        }

                        break;
                    case 'delete':

                        // importer complete
                        $state_data['section'] = '';
                        $state_data['status'] = 'complete';

                        break;
                }
            }

            // Get increase index, locking record, and saving to user importer state
            if (!empty($state_data['section'])) {
                $state_data['progress'][$state_data['section']]['current_row']++;
                update_site_option('iwp_importer_state_' . $id . '_' . $user, array_merge($state_data, ['last_modified' => current_time('timestamp')]));
            }
        }

        $state_data['duration'] = floatval($state_data['duration'] ?? 0) + Logger::timer();

        return $state_data;
    }

    public function process_row($id, $user, $importer_state, $session, $section, $progress)
    {
        $stats = [
            'inserts' => 0,
            'updates' => 0,
            'deletes' => 0,
            'skips' => 0,
            'errors' => 0,
        ];

        if ($section === 'import') {


            // TODO: Run through field map from config (xml or csv)
            $data_parser = new DataParser($this->importer->getParser(), $this->importer->getMapper(), $this->importer->config->getData());

            $i = $progress['start'] + $progress['current_row'] - 1;

            /**
             * @var ParsedData $data
             */
            $data = null;

            try {

                $data = $data_parser->get($i);

                $skip_record = $this->importer->filterRecords();
                $skip_record = apply_filters('iwp/importer/skip_record', $skip_record, $data, $this->importer);

                if ($skip_record) {

                    Logger::write('import -skip-record=' . $i);

                    $stats['skips']++;

                    // set data to null, to flag chunk as skipped
                    Util::write_status_log_file_message($id, $session, "Skipped Record", 'S', $progress['current_row']);

                    $data = null;
                } else {

                    // import
                    $data = apply_filters('iwp/importer/before_mapper', $data, $this->importer);
                    $data->map();

                    if ($data->isInsert()) {

                        Logger::write('import:' . $i . ' -success -insert');

                        $stats['inserts']++;

                        $message = apply_filters('iwp/status/record_inserted', 'Record Inserted: #' . $data->getId(), $data->getId(), $data);
                        Util::write_status_log_file_message($id, $session, $message, 'S', $progress['current_row']);
                    }

                    if ($data->isUpdate()) {

                        Logger::write('import:' . $i . ' -success -update');

                        $stats['updates']++;

                        $message = apply_filters('iwp/status/record_updated', 'Record Updated: #' . $data->getId(), $data->getId(), $data);
                        Util::write_status_log_file_message($id, $session, $message, 'S', $progress['current_row']);
                    }
                }
            } catch (ParserException $e) {

                $stats['errors']++;
                Logger::error('import:' . $i . ' -parser-error=' . $e->getMessage());
                Util::write_status_log_file_message($id, $session, $e->getMessage(), 'E', $progress['current_row']);
            } catch (MapperException $e) {

                $stats['errors']++;
                Logger::error('import:' . $i . ' -mapper-error=' . $e->getMessage());
                Util::write_status_log_file_message($id, $session, $e->getMessage(), 'E', $progress['current_row']);
            } catch (FileException $e) {

                $stats['errors']++;
                Logger::error('import:' . $i . ' -file-error=' . $e->getMessage());
                Util::write_status_log_file_message($id, $session, $e->getMessage(), 'E', $progress['current_row']);
            }

            $this->update_importer_stats($importer_state, $stats);
            Util::write_status_session_to_file($id, $importer_state);

            delete_site_option('iwp_importer_state_' . $id . '_' . $user);
            return;
        }

        if ($section === 'delete') {
            if ($this->importer->getMapper()->permission() && $this->importer->getMapper()->permission()->allowed_method('remove')) {

                $GLOBALS['wp_object_cache']->delete('iwp_importer_config_' . $id, 'options');
                $config = get_site_option('iwp_importer_config_' . $id);
                $i = $progress['current_row'] - 1;

                $object_ids = $config['delete_ids'];
                if ($object_ids && count($object_ids) > $i) {
                    $object_id = $object_ids[$i];
                    $this->importer->getMapper()->delete($object_id);
                    $stats['deletes']++;

                    Logger::write('delete:' . $i . ' -object=' . $object_id);

                    $message = apply_filters('iwp/status/record_deleted', 'Record Deleted: #' . $object_id, $object_id);
                    Util::write_status_log_file_message($id, $session, $message, 'D', $progress['current_row']);
                }
            }

            $this->update_importer_stats($importer_state, $stats);
            Util::write_status_session_to_file($id, $importer_state);

            delete_site_option('iwp_importer_state_' . $id . '_' . $user);
            return;
        }
    }

    function update_importer_stats($importer_state, $stats)
    {
        $importer_state->update(function ($state) use ($stats) {
            if (!isset($state['stats'])) {
                $state['stats'] = [
                    'inserts' => 0,
                    'updates' => 0,
                    'deletes' => 0,
                    'skips' => 0,
                    'errors' => 0,
                ];
            }

            $state['stats']['inserts'] += $stats['inserts'];
            $state['stats']['updates'] += $stats['updates'];
            $state['stats']['deletes'] += $stats['deletes'];
            $state['stats']['skips'] += $stats['skips'];
            $state['stats']['errors'] += $stats['errors'];

            return $state;
        });
    }
}
