<?php

namespace ImportWP\Common\Exporter\File;

class JSONFile extends File
{
    private $counter = 0;

    public function start()
    {
        // write headers
        fputs($this->fh, "[" . PHP_EOL);

        $this->counter = 0;
    }

    public function add($data)
    {

        //		$data = array_map(function($value){
        //			if(is_array($value)){
        //				$value = implode('|', $value);
        //			}
        //			return $value;
        //		}, $data);

        if ($this->counter > 0) {
            fputs($this->fh, "," . PHP_EOL);
        }

        fputs($this->fh, "\t" . json_encode($data));
        $this->counter++;
    }

    public function end()
    {
        fputs($this->fh, PHP_EOL . "]");
        fclose($this->fh);
    }

    public function get_file_name()
    {
        return $this->exporter->getId() . '.json';
    }
}
