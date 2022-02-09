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
            'slug'
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

        return array_merge($core, $custom_fields);
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
            }
        }
        return $output;
    }
}
