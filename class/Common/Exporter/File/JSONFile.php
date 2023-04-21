<?php

namespace ImportWP\Common\Exporter\File;

class JSONFile extends File
{
    private $counter = 0;
    private $parts = [
        'open' => '{' . PHP_EOL,
        'close' => PHP_EOL . '}',
        'parent' => 0
    ];
    private $setup = false;

    public function start()
    {
        // write headers

        // TODO: get the opening posts loop wrappers
        $fields = $this->exporter->getFields();
        $tmp = $this->generate_wrapper($fields);

        foreach ($tmp as $i => $t) {
            $is_last = count($tmp) - 1 == $i;
            $open_tag = $is_last ? '[' : '{';
            $close_tag = $is_last ? ']' : '}';

            $this->parts['open'] .= sprintf('"%s" : %s', $t, $open_tag) . PHP_EOL;
            $this->parts['close'] = PHP_EOL . sprintf('%s', $close_tag) . $this->parts['close'];
        }

        fputs($this->fh, $this->parts['open'] . PHP_EOL);
        $this->counter = 0;

        update_site_option('iwp_exporter_json_config', [
            'parts' => $this->parts
        ]);
    }

    public function loadConfig()
    {
        if (!$this->setup) {
            $config = get_site_option('iwp_exporter_json_config', []);
            $this->parts = $config['parts'];
            $this->setup = true;
        }
    }

    public function generate_wrapper($fields, $output = [], $parent = 0, $depth = 0)
    {

        $current_items = array_filter($fields, function ($item) use ($parent) {
            return intval($item['parent']) == $parent;
        });

        if (!empty($current_items)) {
            foreach ($current_items as $current_item) {
                $label = $this->getFieldLabel($current_item);

                if (isset($current_item['loop']) && ($current_item['loop'] === 'true' || $current_item['loop'] === true)) {
                    $output[] = $label;
                    $this->parts['parent'] = $current_item['id'];
                    break;
                } else {
                    $output[] = $label;
                    $output = $this->generate_wrapper($fields, $output, $current_item['id'], $depth + 1);
                }
            }
        }

        return $output;
    }

    public function add($mapper)
    {
        $this->loadConfig();

        //		$data = array_map(function($value){
        //			if(is_array($value)){
        //				$value = implode('|', $value);
        //			}
        //			return $value;
        //		}, $data);

        if ($this->counter > 0) {
            fputs($this->fh, "," . PHP_EOL);
        }

        $fields = $this->exporter->getFields();
        fputs($this->fh, "\t" . json_encode($this->generate_schema($fields, $mapper, $this->parts['parent'])));
        $this->counter++;
    }

    public function end()
    {
        $this->loadConfig();

        fputs($this->fh, PHP_EOL . $this->parts['close']);
        fclose($this->fh);
    }

    public function get_file_name()
    {
        return $this->exporter->getId() . '.json';
    }

    public function generate_schema($fields, $mapper, $parent = 0, $loop_args = [], $depth = 0, $data = null)
    {
        $output = [];

        $current_items = array_filter($fields, function ($item) use ($parent) {
            return intval($item['parent']) == $parent;
        });

        if (!empty($current_items)) {
            foreach ($current_items as $current_item) {
                $label = $this->getFieldLabel($current_item);

                if (isset($current_item['loop']) && ($current_item['loop'] === 'true' || $current_item['loop'] === true)) {

                    $loop_args = ['loop' => $current_item['selection']];

                    $loop_data = $mapper->data($loop_args);
                    $output[$label] = [];

                    // We are in a loop
                    foreach ($loop_data as $data) {
                        $output[$label][] = $this->generate_schema($fields, $mapper, $current_item['id'], $loop_args, $depth + 1, $data);
                    }
                } else {

                    $output[$label] = $this->generate_schema($fields, $mapper, $current_item['id'], $loop_args, $depth + 1, $data);
                    if (empty($output[$label])) {

                        $output[$label] = $mapper->get_value(isset($current_item['selection']) ? $current_item['selection'] : '', $data);
                    }
                }
            }
        }

        return $output;
    }
}
