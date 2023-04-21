<?php

namespace ImportWP\Common\Exporter\State;

use ImportWP\Common\Runner\RunnerState;

class ExporterState extends RunnerState
{
    protected static $object_type = 'exporter';

    public function is_running()
    {
        return $this->has_status('running');
    }

    public function is_resumable()
    {
        return $this->has_section(['export', 'timeout']);
    }

    protected function default($session)
    {
        return array_merge(parent::default($session), [
            'section' => 'export',
            'progress' => [
                'export' => [
                    'start' => 0,
                    'end' => 0,
                    'current_row' => 0,
                ]
            ],
        ]);
    }
}
