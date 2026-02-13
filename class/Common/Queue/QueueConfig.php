<?php

namespace ImportWP\Common\Queue;

/**
 * Hack to get the file index from the importer config
 */
class QueueConfig implements QueueTasksInterface
{

    public $start;
    public $end;
    public $config;
    public function __construct($config, $start, $end)
    {
        $this->config = $config;
        $this->start = $start;
        $this->end = $end;
    }

    public function getFileIndex()
    {
        $index_data = [];

        for ($i = $this->start; $i < $this->end; $i++) {
            // $test = $this->config->getIndex('file_index', $i);
            // $index_data[] = [
            //     'start' => $test[0],
            //     'length' => $test[1],
            // ];

            // dont store the file position, store the row number,
            // use the old index saved position.
            $index_data[] = [
                'start' => $i,
                'length' => 0,
            ];
        }

        return $index_data;
    }
}
