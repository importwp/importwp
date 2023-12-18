<?php

namespace ImportWP\Common\Runner;

use ImportWP\Common\Exporter\Mapper\MapperData;
use ImportWP\Common\Util\Logger;

class ExporterRunner extends Runner
{

    protected $file;

    /**
     * @var MapperInterface
     */
    protected $mapper;

    /**
     * @var string
     */
    protected $object_type = 'exporter';

    public function __construct($properties, $mapper, $file)
    {
        parent::__construct($properties);
        $this->mapper = $mapper;
        $this->file = $file;
    }

    public function setup($state, $state_data, $config, $user, $id)
    {
        $state->populate($state_data);

        if (!$state->validate($config['id'])) {
            throw new \Exception(__("Exporter session has changed", 'jc-importer'));
        }

        $section = 'export';

        if ($state->has_status('running')) {

            if (isset($state_data['progress'][$section]) && $state_data['progress'][$section]['end'] - $state_data['progress'][$section]['start'] <= $state_data['progress'][$section]['current_row']) {

                // Does this user or any user have any dangling jobs?
                $dangling = $this->try_process_dangling_state($id, $user, $state);
                if (!$dangling) {
                    $this->is_timeout = true;
                    $state_data['duration'] = floatval($state_data['duration']) + Logger::timer();
                    return $state_data;
                }

                $state_data['section'] = '';
                $state_data['status'] = 'end';
            }

            // Get increase index, locking record, and saving to user importer state
            $state_data['progress'][$section]['current_row']++;
            update_site_option('iwp_exporter_state_' . $id . '_' . $user, array_merge($state_data, ['last_modified' => current_time('timestamp')]));
        }

        $state_data['duration'] = floatval($state_data['duration'] ?? 0) + Logger::timer();

        return $state_data;
    }

    public function process_row($id, $user, $exporter_state, $session, $section, $progress)
    {
        $stats = [
            'rows' => 0,
            'skips' => 0,
            'errors' => 0,
        ];

        $i = $progress['start'] + $progress['current_row'] - 1;

        try {

            // TODO: Export row

            $mapper_data = new MapperData($this->mapper, $i);
            if (!$mapper_data->skip()) {
                $this->file->add($mapper_data);
            }

            Logger::write('export:' . $i . ' -success');
            $stats['rows']++;
        } catch (\Exception $e) {
            $stats['errors']++;
            Logger::error('export:' . $i . ' -error=' . $e->getMessage());
        }

        $this->update_importer_stats($exporter_state, $stats);
        delete_site_option('iwp_exporter_state_' . $id . '_' . $user);
    }

    function update_importer_stats($exporter_state, $stats)
    {
        $exporter_state->update(function ($state) use ($stats) {
            if (!isset($state['stats'])) {
                $state['stats'] = [
                    'rows' => 0,
                    'skips' => 0,
                    'errors' => 0,
                ];
            }

            $state['stats']['rows'] += $stats['rows'];
            $state['stats']['skips'] += $stats['skips'];
            $state['stats']['errors'] += $stats['errors'];

            return $state;
        });
    }
}
