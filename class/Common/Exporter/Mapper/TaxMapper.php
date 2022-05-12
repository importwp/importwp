<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\MapperInterface;

class TaxMapper extends AbstractMapper implements MapperInterface
{

    private $taxonomy;

    /**
     * @var \WP_Term_Query
     */
    private $query;

    private function get_core_fields()
    {
        return array(
            'term_id',
            'name',
            'slug',
            'description',
        );
    }

    public function __construct($taxonomy)
    {
        $this->taxonomy = $taxonomy;
    }

    public function get_fields()
    {

        $core = $this->get_core_fields();
        $custom_fields = array();

        // get taxonomy custom fields
        global $wpdb;
        $meta_fields = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM " . $wpdb->termmeta . " as tm INNER JOIN " . $wpdb->term_taxonomy . " as tt ON tm.term_id = tt.term_id WHERE tt.taxonomy = %s", [$this->taxonomy]));
        foreach ($meta_fields as $field) {
            $custom_fields[] = 'ewp_cf_' . $field;
        }

        $custom_fields = apply_filters('iwp/exporter/taxonomy/custom_field_list', $custom_fields, $this->taxonomy);

        $tax_fields = [
            'parent_id',
            'parent_slug',
            'parent_name',
            'parent_anscestors_id',
            'parent_anscestors_slug',
            'parent_anscestors_name',
        ];

        return array_merge($core, $tax_fields, $custom_fields);
    }

    public function have_records()
    {
        $this->query = new \WP_Term_Query(array(
            'taxonomy' => $this->taxonomy,
            'hide_empty' => false
        ));

        return $this->found_records() > 0;
    }

    public function found_records()
    {
        return count($this->query->terms);
    }

    public function get_record($i, $columns)
    {

        $record = $this->query->terms[$i];

        // Meta data
        $meta = get_term_meta($record->term_id);

        $row = array();
        foreach ($columns as $column) {
            $row[$column] = $this->get_field($column, $record, $meta);
        }

        if ($this->filter($row, $record, $meta)) {
            return false;
        }

        return $row;
    }

    public function get_field($column, $record, $meta)
    {
        // Core fields
        $core = $this->get_core_fields();

        $output = '';

        if (preg_match('/^ewp_cf_(.*?)$/', $column, $matches) == 1) {

            $meta_key = $matches[1];
            if (isset($meta[$meta_key])) {
                $output = $meta[$meta_key];
            }
        } else {

            if (in_array($column, $core, true)) {
                $output = $record->{$column};
            } else {
                switch ($column) {
                    case 'parent_id':
                        /**
                         * @var \WP_Term $record
                         */
                        $parent = get_term($record->parent, $this->taxonomy);
                        if (!is_wp_error($parent)) {
                            $output = $parent->term_id;
                        }
                        break;
                    case 'parent_slug':
                        $parent = get_term($record->parent, $this->taxonomy);
                        if (!is_wp_error($parent)) {
                            $output = $parent->slug;
                        }
                        break;
                    case 'parent_name':
                        $parent = get_term($record->parent, $this->taxonomy);
                        if (!is_wp_error($parent)) {
                            $output = $parent->name;
                        }
                        break;
                    case 'parent_anscestors_id':
                        $parents = get_ancestors($record->term_id, $this->taxonomy, 'taxonomy');
                        $output = implode(' > ', $parents);
                        break;
                    case 'parent_anscestors_slug':
                        $parents = get_ancestors($record->term_id, $this->taxonomy, 'taxonomy');
                        $parents = array_filter(array_map(function ($item) {
                            $term = get_term($item, $this->taxonomy);
                            return !is_wp_error($term) ? $term->slug : '';
                        }, $parents));
                        $output = implode(' > ', $parents);
                        break;
                    case 'parent_anscestors_name':
                        $parents = get_ancestors($record->term_id, $this->taxonomy, 'taxonomy');
                        $parents = array_filter(array_map(function ($item) {
                            $term = get_term($item, $this->taxonomy);
                            return !is_wp_error($term) ? $term->name : '';
                        }, $parents));

                        $output = implode(' > ', $parents);
                        break;
                }
            }
        }

        $output = apply_filters('iwp/exporter/taxonomy/value', $output, $column, $record, $meta);
        return $output;
    }
}
