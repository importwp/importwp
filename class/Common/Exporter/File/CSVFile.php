<?php

namespace ImportWP\Common\Exporter\File;

class CSVFile extends File
{
    public function start()
    {
        // write headers
        fputcsv($this->fh, $this->exporter->getFields());
    }

    public function add($data)
    {

        $data = array_map(
            function ($value) {
                if (is_array($value)) {
                    $value = implode('|', $value);
                }
                return $value;
            },
            $data
        );

        fputcsv($this->fh, $data);
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
