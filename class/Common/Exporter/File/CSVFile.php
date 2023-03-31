<?php

namespace ImportWP\Common\Exporter\File;

use ImportWP\Common\Exporter\Mapper\MapperData;

class CSVFile extends File
{
    private $columns;
    public function start()
    {
        $fields = $this->exporter->getFields();

        $this->columns = array_reduce($fields, function ($carry, $item) {
            $carry[$this->getFieldLabel($item)] = isset($item['selection']) ? $item['selection'] : '';
            return $carry;
        }, []);
        // write headers
        fputcsv($this->fh, array_keys($this->columns));
    }

    /**
     * @param MapperData $mapper
     * @return void
     */
    public function add($mapper)
    {
        $data = [];

        $max = $mapper->get_total_records();
        if ($max <= 0) {
            $max = 1;
        }
        for ($i = 0; $i < $max; $i++) {

            $data = array_map(function ($item) use ($mapper, $i) {
                $record = $mapper->data([], $i);
                $tmp = $mapper->get_value($item, $record[0]);
                return is_array($tmp) ? implode(',', $tmp) : $tmp;
            }, $this->columns);

            fputcsv($this->fh, $data);
        }
    }

    public function end()
    {
        fclose($this->fh);
    }

    public function get_file_name()
    {
        return $this->exporter->getId() . '.csv';
    }
}
