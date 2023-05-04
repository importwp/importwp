<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\MapperInterface;

class TaxMapper extends AbstractMapper implements MapperInterface
{

    /**
     * @var \WP_Term_Query
     */
    private $query;
    private $taxonomy;

    public function get_core_fields()
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
        /**
         * @var \WPDB
         */
        global $wpdb;

        $fields = [
            'key' => 'main',
            'label' => $this->taxonomy,
            'loop' => true,
            'fields' => [],
            'children' => [
                'parent' => [
                    'key' => 'parent',
                    'label' => 'Parent',
                    'loop' => false,
                    'fields' => [],
                    'children' => []
                ],
                'anscestors' => [
                    'key' => 'anscestors',
                    'label' => 'Anscestors',
                    'loop' => true,
                    'fields' => [],
                    'children' => []
                ],
                'custom_fields' => [
                    'key' => 'custom_fields',
                    'label' => 'Custom Fields',
                    'loop' => true,
                    'loop_fields' => ['meta_key', 'meta_value'],
                    'fields' => [],
                    'children' => []
                ]
            ]

        ];

        $fields['fields'] = $this->get_core_fields();

        // get taxonomy custom fields
        global $wpdb;
        $meta_fields = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM " . $wpdb->termmeta . " as tm INNER JOIN " . $wpdb->term_taxonomy . " as tt ON tm.term_id = tt.term_id WHERE tt.taxonomy = %s", [$this->taxonomy]));
        foreach ($meta_fields as $field) {
            $fields['children']['custom_fields']['fields'][] = $field;
        }

        $fields['children']['parent']['fields'] = [
            'term_id',
            'slug',
            'name',
            'parent'
        ];

        $fields['children']['anscestors']['fields'] = [
            'term_id',
            'slug',
            'name',
            'parent'
        ];

        $fields['children']['custom_fields']['fields'] = apply_filters('iwp/exporter/taxonomy/custom_field_list',  $fields['children']['custom_fields']['fields'], $this->taxonomy);

        return $fields;
    }

    public function have_records($exporter_id)
    {
        $query_args = [];
        $query_args = apply_filters('iwp/exporter/tax_query', $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/%d/tax_query', $exporter_id), $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/tax_query/%s', $this->taxonomy), $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/%d/tax_query/%s', $exporter_id, $this->taxonomy), $query_args);

        $query_args = wp_parse_args($query_args, [
            'taxonomy' => $this->taxonomy,
            'hide_empty' => false,
            'fields' => 'ids'
        ]);

        $this->query = new \WP_Term_Query($query_args);
        $this->items = (array)$this->query->terms;

        return $this->found_records() > 0;
    }

    public function found_records()
    {
        return count($this->items);
    }

    public function get_records()
    {
        return $this->items;
    }

    public function setup($i)
    {
        $this->record = get_term($this->items[$i], '', ARRAY_A);
        $this->record['custom_fields'] = get_term_meta($this->record['term_id']);

        $parent = get_term($this->record['parent'], $this->taxonomy);
        if (!is_wp_error($parent)) {
            $this->record['parent'] = [
                'term_id' => $parent->term_id,
                'slug' => $parent->slug,
                'name' => $parent->name,
                'parent' => $parent->parent
            ];
        } else {
            $this->record['parent'] = [
                'term_id' => '',
                'slug' => '',
                'name' => '',
                'parent' => ''
            ];
        }

        $ancestor_ids = get_ancestors($this->record['term_id'], $this->taxonomy, 'taxonomy');
        $ancestor_ids = array_reverse($ancestor_ids);

        $this->record['anscestors'] = [];

        if ($ancestor_ids) {
            foreach ($ancestor_ids as $ancestor_id) {

                $ancestor = get_term($ancestor_id, $this->taxonomy);
                if (!is_wp_error($ancestor)) {
                    $this->record['anscestors'][] = [
                        'term_id' => $ancestor->term_id,
                        'slug' => $ancestor->slug,
                        'name' => $ancestor->name,
                        'parent' => $ancestor->parent
                    ];
                }
            }
        }

        return true;
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
