<?php

namespace ImportWP\Common\Exporter\File;

use ImportWP\Common\Exporter\Mapper\MapperData;

class CSVFile extends File
{
    private $columns;
    private $setup = false;

    protected $default_delimiter = ",";
    protected $default_enclosure = "\"";
    protected $default_escape = "\\";

    public function start()
    {
        $fields = $this->exporter->getFields();

        $this->columns = array_reduce($fields, function ($carry, $item) {
            $carry[$this->getFieldLabel($item)] = isset($item['selection']) ? $item['selection'] : '';
            return $carry;
        }, []);

        list($delimiter, $enclosure, $escape) = $this->get_csv_settings();

        // write headers
        fputcsv($this->fh, array_keys($this->columns), $delimiter, $enclosure, $escape);

        update_site_option('iwp_exporter_csv_config', [
            'columns' => $this->columns
        ]);
    }

    public function loadConfig()
    {
        if (!$this->setup) {
            $config = get_site_option('iwp_exporter_csv_config', []);
            $this->columns = $config['columns'];
            $this->setup = true;
        }
    }

    /**
     * @param MapperData $mapper
     * @return void
     */
    public function add($mapper)
    {
        $this->loadConfig();
        $data = [];

        list($delimiter, $enclosure, $escape) = $this->get_csv_settings();

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

            fputcsv($this->fh, $data, $delimiter, $enclosure, $escape);
        }
    }

    public function get_csv_settings()
    {
        $delimiter = $this->exporter->getFileSetting('delimiter', $this->default_delimiter);
        $enclosure = $this->exporter->getFileSetting('enclosure', $this->default_enclosure);
        $escape = $this->exporter->getFileSetting('escape', $this->default_escape);

        if ($enclosure === $escape) {
            $escape = '';
        }

        return [$delimiter, $enclosure, $escape];
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
