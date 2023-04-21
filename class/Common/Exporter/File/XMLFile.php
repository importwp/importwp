<?php

namespace ImportWP\Common\Exporter\File;

use ImportWP\Common\Exporter\Mapper\MapperData;

class XMLFile extends File
{
    private $template;
    private $template_loops;
    private $template_sections = [];
    private $template_data = [];
    private $i = -1;
    private $setup = false;

    public function start()
    {
        //  Generating a template, allows at a later date for custom templates to be added via user.
        $this->template = $this->generateTemplate();

        // Parse Template <!-- ewp:loop {"loop": "post"} -->
        preg_match_all('/<!--\s*(\/?)ewp:loop\s*(\{.*?\})?\s*-->/', $this->template, $this->template_loops, PREG_OFFSET_CAPTURE);

        // generate template loop data from matches
        $last = $this->template_loops[0][count($this->template_loops[0]) - 1];
        $this->template_sections['start'] = substr($this->template, 0, $this->template_loops[0][0][1]); // + strlen($this->template_loops[0][0][0]));
        $this->template_sections['end'] = substr($this->template, $last[1] + strlen($last[0]));

        fputs($this->fh, $this->template_sections['start'] . PHP_EOL);

        $data = $this->buildTemplateDataStructure([]);
        $this->template_data = $data[0];

        update_site_option('iwp_exporter_xml_config', [
            'template' => $this->template,
            'template_sections' => $this->template_sections,
            'template_data' => $this->template_data
        ]);
    }

    public function loadConfig()
    {

        if (!$this->setup) {
            $config = get_site_option('iwp_exporter_xml_config');
            $this->template = $config['template'];
            $this->template_sections = $config['template_sections'];
            $this->template_data = $config['template_data'];
            $this->setup = true;
        }
    }

    public function buildTemplateDataStructure($data)
    {
        $this->i++;

        if (count($this->template_loops[0]) < $this->i + 1) {
            return $data;
        }

        $is_opener = $this->template_loops[1][$this->i][0] !== '/';
        if ($is_opener) {

            $row = [];
            $row['id'] = $this->template_loops[0][$this->i][0];
            $row['args'] = json_decode($this->template_loops[2][$this->i][0], true);
            $row['start'] = $this->template_loops[0][$this->i][1];
            $row['children'] = $this->buildTemplateDataStructure([]);
            $row['end'] = $this->template_loops[0][$this->i][1];

            $start_with_offset = $row['start'] + strlen($row['id']);

            $row['template'] = [];

            if (!empty($row['children'])) {

                // start
                $row['template']['open'] = trim(substr($this->template, $start_with_offset, $row['children'][0]['start'] - $start_with_offset));

                // inbetween parts
                if (count($row['children']) > 1) {

                    $tmp = [];
                    $tmp[] = $row['children'][0];

                    for ($i = 1; $i < count($row['children']); $i++) {
                        $prev = $row['children'][$i - 1];
                        $current = $row['children'][$i];
                        $end_with_offset = $prev['end'] + strlen($this->template_loops[0][$this->i][0]);
                        $tmp[] = trim(substr($this->template, $end_with_offset, $current['start'] - $end_with_offset));
                        $tmp[] = $row['children'][$i];
                    }

                    $row['children'] = $tmp;
                }

                // end

                $prev_index = $this->i - 1;

                $end_start = $this->template_loops[0][$prev_index][1] + strlen($this->template_loops[0][$prev_index][0]);
                $end_end = $this->template_loops[0][$this->i][1];

                $row['template']['close'] = trim(substr($this->template, $end_start, $end_end - $end_start));

                // $next_index = $this->i + 1 == count($this->template_loops[0]) ? -1 : $this->i + 1;
                // $end_with_offset = $row['end'] + strlen($this->template_loops[0][$this->i][0]);
                // if ($next_index == -1) {
                //     $row['template'][] = trim(substr($this->template, $end_with_offset));
                // } else {
                //     $row['template'][] = trim(substr($this->template, $end_with_offset, $this->template_loops[0][$next_index][1] - $end_with_offset));
                // }
            } else {
                $row['template']['main']  = trim(substr($this->template, $start_with_offset, $row['end'] - $start_with_offset));
            }

            $data[] = $row;

            return $this->buildTemplateDataStructure($data);
        }

        return $data;
    }

    public function generateTemplate()
    {
        $fields = $this->exporter->getFields();
        $template = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $template .= $this->recursivelyGenerateXML($fields);
        return $template;
    }

    public function recursivelyGenerateXML($fields, $parent = 0, $schema = '', $loop = null)
    {
        $current_items = array_filter($fields, function ($item) use ($parent) {
            return intval($item['parent']) == $parent;
        });

        // move attributes to top
        usort($current_items, function ($a, $b) {

            $found_a  = isset($a['label']) && strpos($a['label'], '@') === 0;
            $found_b  = isset($b['label']) &&  strpos($b['label'], '@') === 0;

            if ($found_a  && !$found_b) {
                return -1;
            } elseif (!$found_a  && $found_b) {
                return 1;
            }

            return 0;
        });



        if (!empty($current_items)) {
            foreach ($current_items as $current_item) {

                $label = $this->getFieldLabel($current_item);

                $is_attr = strpos($label, '@') === 0;

                if ($is_attr) {

                    $label = $this->esc_attr($label);

                    // we are an attribute
                    if (isset($current_item['selection']) && !empty($current_item['selection'])) {

                        $selection = $current_item['selection'];
                        if (!isset($current_item['custom']) || $current_item['custom'] !== 'true') {
                            $selection = '{' . $selection . ' | ' . wp_json_encode(["escape" => true]) . '}';
                        }

                        $schema .= ' ' . $label . '="' . $selection . '"';
                    }
                    continue;
                }

                if (!$this->hasClosingTag($schema)) {
                    $schema = $this->checkForClosingTag($schema);
                    $schema .= "\n";
                }

                $label = $this->esc_label($label);

                if (isset($current_item['loop']) && ($current_item['loop'] === 'true' || $current_item['loop'] === true)) {

                    $schema .= '<!-- ewp:loop ' . wp_json_encode(['loop' => $current_item['selection']]) . ' -->' . "\n";

                    $schema .= '<' . $label;
                    $schema = $this->recursivelyGenerateXML($fields, $current_item['id'], $schema, $current_item['selection']);
                    $schema = $this->checkForClosingTag($schema) . "\n";

                    $schema .= '</' . $label . '>' . "\n";

                    $schema .= '<!-- /ewp:loop -->' . "\n";
                } else {

                    //TODO: if data is array, repeat
                    $schema .= '<' . $label;

                    $schema = $this->recursivelyGenerateXML($fields, $current_item['id'], $schema, $loop);

                    $schema = $this->checkForClosingTag($schema);

                    if (isset($current_item['selection']) && !empty($current_item['selection'])) {

                        $selection = $current_item['selection'];
                        if (!isset($current_item['custom']) || $current_item['custom'] !== 'true') {
                            $selection = '<![CDATA[{' . $selection . '}]]>';
                        }

                        $schema .= $selection;
                    }

                    $schema .= '</' . $label . '>' . "\n";
                }
            }
        }

        return $schema;
    }

    public function esc_label($label)
    {

        $label = str_replace(' ', '_', $label);
        return $label;
    }

    public function esc_attr($attr)
    {

        // remove @ from start
        $attr = substr($attr, 1);
        $attr = str_replace(' ', '_', $attr);
        return $attr;
    }

    /**
     * @param MapperData $mapper
     * @return void
     */
    public function add($mapper)
    {
        $this->loadConfig();
        $output = $this->processLoop($this->template_data, $mapper);
        fputs($this->fh, $output);
    }

    /**
     * @param array $template_data
     * @param MapperData $mapper
     */
    public function processLoop($template_data, $mapper, $loop_args = [], $depth = 0)
    {
        $output = '';

        if (isset($template_data['args']) && $depth > 0) {
            $loop_args = $template_data['args'];
        }

        $output .= PHP_EOL . '<!-- processLoop ' . wp_json_encode($loop_args) . ' -->' . PHP_EOL;

        // TODO: get loop data and replace template variables
        $loop_data = $mapper->data($loop_args);
        foreach ($loop_data as $data) {

            if (isset($template_data['template']['open'])) {
                $output .= $mapper->template($template_data['template']['open'], $data);
            }

            if (!empty($template_data['children'])) {
                foreach ($template_data['children'] as $child) {
                    if (is_array($child)) {
                        $output .= $this->processLoop($child, $mapper, $loop_args, $depth + 1);
                    } else {
                        $output .= $mapper->template($child, $data);
                    }
                }
            } elseif (isset($template_data['template']['main'])) {
                $output .= $mapper->template($template_data['template']['main'], $data);
            }

            if (isset($template_data['template']['close'])) {
                $output .= $mapper->template($template_data['template']['close'], $data);
            }
        }

        $output .= PHP_EOL . '<!-- /processLoop -->' . PHP_EOL;

        return $output;
    }

    public function checkForClosingTag($output)
    {
        $tmp = trim($output);
        if (strlen($tmp) > 1 && substr($tmp, -1) !== '>') {
            $output .= '>';
        }
        return $output;
    }

    public function hasClosingTag($output)
    {
        $tmp = trim($output);
        if (strlen($tmp) > 1 && substr($tmp, -1) !== '>') {
            return false;
        }
        return true;
    }

    public function indent($depth)
    {
        return str_repeat("\t", $depth + 1);
    }

    public function end()
    {
        $this->loadConfig();
        fputs($this->fh, $this->template_sections['end'] . PHP_EOL);
        fclose($this->fh);
    }

    public function get_file_name()
    {
        return $this->exporter->getId() . '.xml';
    }
}
