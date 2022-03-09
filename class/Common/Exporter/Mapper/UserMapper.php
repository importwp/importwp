<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\MapperInterface;

class UserMapper extends AbstractMapper implements MapperInterface
{
    /**
     * @var \WP_User_Query
     */
    private $query;

    public function get_fields()
    {
        $core_fields = $this->get_core_fields();

        $custom_fields = array();

        global $wpdb;
        $meta_fields = $wpdb->get_col("SELECT DISTINCT meta_key FROM " . $wpdb->usermeta . " WHERE user_id IN (SELECT DISTINCT ID FROM " . $wpdb->users . " )");
        foreach ($meta_fields as $field) {
            $custom_fields[] = 'ewp_cf_' . $field;
        }

        $custom_fields = apply_filters('iwp/exporter/user/custom_field_list', $custom_fields, null);

        return array_merge($core_fields, $custom_fields);
    }

    public function have_records()
    {
        $this->query = new \WP_User_Query(array(
            'number' => -1
        ));

        return $this->found_records() > 0;
    }

    public function found_records()
    {
        return $this->query->get_total();
    }

    public function get_record($i, $columns)
    {

        /**
         * @var WP_User $record
         */
        $record = $this->query->results[$i];
        $meta = get_user_meta($record->ID);

        $row = array();

        foreach ($columns as $column) {
            $row[$column] = $this->get_field($column, $record, $meta);
        }

        if ($this->filter($row, $record, $meta)) {
            return false;
        }

        return $row;
    }

    private function get_core_fields()
    {
        return array(
            'ID',
            'user_login',
            'user_nicename',
            'user_email',
            'user_url',
            'user_registered',
            'user_status',
            'display_name'
        );
    }

    public function get_field($column, $record, $meta)
    {

        $output = '';
        $core = $this->get_core_fields();


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

        $output = apply_filters('iwp/exporter/user/value', $output, $column, $record, $meta);
        return $output;
    }
}
