<?php

namespace ImportWP\Common\Exporter\File;

class XMLFile extends File
{
    public function start()
    {

        fputs($this->fh, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
        fputs($this->fh, '<records>' . PHP_EOL);
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

        $xml = "\t" . '<record>' . PHP_EOL;
        foreach ($data as $key => $value) {
            $xml .= sprintf("\t\t<%s><![CDATA[%s]]></%s>", $key, $value, $key) . PHP_EOL;
        }
        $xml .= "\t" . '</record>' . PHP_EOL;
        fputs($this->fh, $xml);
    }

    public function end()
    {
        fputs($this->fh, '</records>' . PHP_EOL);
        fclose($this->fh);
    }

    public function get_file_name()
    {
        return $this->exporter->getId() . '.xml';
    }
}
