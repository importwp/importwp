<?php

namespace ImportWP\Common\Exporter\Mapper;

abstract class AbstractMapper
{
    protected $filters;
    protected $record = [];
    public $records = [];
    public $items = [];

    public function get_value($column, $template_data = null)
    {
        if (is_null($template_data)) {
            $template_data = $this->record();
        }

        $parts = explode('.', $column);
        if (count($parts) == 2) {

            // handle looped data
            if (isset($template_data[$parts[0]], $template_data[$parts[0]][0], $template_data[$parts[0]][0][$parts[1]])) {
                return array_reduce($template_data[$parts[0]], function ($carry, $item) use ($parts) {
                    if (isset($item[$parts[1]])) {
                        $carry[] = $item[$parts[1]];
                    }
                    return $carry;
                }, []);
            }

            // handled grouped data or single
            return isset($template_data[$parts[0]], $template_data[$parts[0]][$parts[1]]) ? $template_data[$parts[0]][$parts[1]] : '';
        }

        return isset($template_data[$column]) ? $template_data[$column] : '';
    }

    public function set_filters($filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Filter record
     *
     * @param array $post
     * @return boolean
     */
    public function filter()
    {
        $result = false;

        if (empty($this->filters)) {
            return $result;
        }

        foreach ($this->filters as $group) {

            $result = true;

            if (empty($group)) {
                continue;
            }

            foreach ($group as $row) {

                $left = $this->get_value($row['left']);
                $right = $row['right'];
                $right_parts = array_map('trim', explode(',', $right));

                switch ($row['condition']) {
                    case 'equal':
                        if ($left != $right) {
                            $result = false;
                        }
                        break;
                    case 'contains':
                        if (stripos($left, $right) === false) {
                            $result = false;
                        }
                        break;
                    case 'in':
                        if (!in_array($left, $right_parts)) {
                            $result = false;
                        }
                        break;
                    case 'not-equal':
                        if ($left == $right) {
                            $result = false;
                        }
                        break;
                    case 'not-contains':
                        if (stripos($left, $right) !== false) {
                            $result = false;
                        }
                        break;
                    case 'not-in':
                        if (in_array($left, $right_parts)) {
                            $result = false;
                        }
                        break;
                }
            }

            if ($result) {
                return true;
            }
        }


        return $result;
    }

    public function record($index = 0)
    {
        if (isset($this->records[$index])) {
            return $this->records[$index];
        }

        return $this->record;
    }

    public function get_records()
    {
        return $this->items;
    }

    public function set_records($records)
    {
        $this->items = $records;
    }

    public function modify_custom_field_data($record, $type)
    {
        $custom_file_fields = apply_filters('iwp/exporter/' . $type . '/custom_file_id_fields', []);
        if (!empty($custom_file_fields)) {
            foreach ($custom_file_fields as $custom_field) {

                if (!isset($record['custom_fields'][$custom_field]) && !empty($record['custom_fields'][$custom_field])) {
                    continue;
                }

                $data = [
                    'id' => [],
                    'url' => [],
                    'title' => [],
                    'alt' => [],
                    'caption' => [],
                    'description' => [],
                ];

                foreach ($record['custom_fields'][$custom_field] as $custom_field_value) {

                    $custom_field_data = maybe_unserialize($custom_field_value);
                    if (is_string($custom_field_data)) {
                        $custom_field_data = explode(',', $custom_field_data);
                    }

                    if (empty($custom_field_data)) {
                        continue;
                    }

                    foreach ($custom_field_data as $possible_attachment_id) {

                        $attachment_id = intval($possible_attachment_id);
                        if ($attachment_id === 0) {
                            continue;
                        }

                        $attachment = get_post($attachment_id, ARRAY_A);
                        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

                        $data['id'][] = $attachment_id;
                        $data['url'][] = wp_get_attachment_url($attachment_id);
                        $data['title'][] = $attachment['post_title'];
                        $data['alt'][] = $alt;
                        $data['caption'][] = $attachment['post_excerpt'];
                        $data['description'][] = $attachment['post_content'];
                    }
                }

                $record['custom_fields'][$custom_field . '::id'] = $data['id'];
                $record['custom_fields'][$custom_field . '::url'] = $data['url'];
                $record['custom_fields'][$custom_field . '::title'] = $data['title'];
                $record['custom_fields'][$custom_field . '::alt'] = $data['alt'];
                $record['custom_fields'][$custom_field . '::caption'] = $data['caption'];
                $record['custom_fields'][$custom_field . '::description'] = $data['description'];
            }
        }

        return $record;
    }
}
