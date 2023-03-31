<?php

namespace ImportWP\Common\Exporter\Mapper;

class MapperData
{
    /**
     * @var MapperInterface
     */
    private $_mapper;

    private $template_data;

    /**
     * @param MapperInterface $mapper
     * @param int $i
     */
    public function __construct($mapper, $i)
    {
        $this->_mapper = $mapper;
        $this->_mapper->setup($i);
    }

    public function skip()
    {
        return $this->_mapper->filter();
    }

    public function get_total_records()
    {
        return count($this->_mapper->records);
    }

    public function data($args, $index = 0)
    {
        $record = $this->_mapper->record($index);

        if (empty($args)) {
            return [$record];
        }

        if (strpos($args['loop'], 'tax_') === 0) {
            $taxonomy = substr($args['loop'], strlen('tax_'));
            $terms       = wp_get_object_terms($record['ID'], $taxonomy);
            $tmp = [];
            if (!empty($terms)) {
                foreach ($terms as $term) {

                    /**
                     * @var \WP_Term $term
                     */
                    $tmp[] = [
                        'id' => $term->term_id,
                        'slug' => $term->slug,
                        'name' => $term->name,
                    ];
                }
            }

            return $tmp;
        }

        switch ($args['loop']) {
            case 'custom_fields':
                $tmp = [];
                foreach ($record['custom_fields'] as $key => $value) {
                    $tmp[] = ['meta_key' => $key, 'meta_value' => implode("|", $value)];
                }

                return $tmp;
            default:
                if (isset($record[$args['loop']])) {
                    return $record[$args['loop']];
                }
                break;
        }


        return [];
    }



    public function template($template, $data)
    {
        $this->template_data = $data;
        // Allow for settings to be present in the template variable e.g. {custom_fields.price | {"seperator": ","}}
        $template = preg_replace_callback('/{(.*?)(?:\s+\|\s+)?({.*?})?}/', array($this, 'query_matches'), $template);
        return $template;
    }

    public function query_matches($matches)
    {
        if (isset($matches[0]) && isset($matches[1])) {

            $args = [];
            if (!empty($matches[2])) {
                $args = json_decode($matches[2], true);
            }

            $defaults = [
                'escape' => false,
                'seperator' => '|'
            ];

            $args = wp_parse_args($args, $defaults);

            $value = $this->get_value($matches[1]);
            $value = is_array($value) ? implode($args['seperator'], $value) : $value;

            if ($args['escape'] == true) {
                $value = esc_attr($value);
            }

            return $value;
        }

        return '';
    }

    public function get_value($field, $data = null)
    {
        return $this->_mapper->get_value($field, is_null($data) ? $this->template_data : $data);
    }
}
