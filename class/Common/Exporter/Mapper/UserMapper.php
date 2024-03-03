<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\ExporterRecord;
use ImportWP\Common\Exporter\MapperInterface;

class UserMapper extends AbstractMapper implements MapperInterface
{
    /**
     * @var \WP_User_Query
     */
    private $query;

    public function __construct()
    {
        add_filter('iwp/exporter_record/user', [$this, 'get_record_data'], 10, 3);
    }

    public function get_core_fields()
    {
        return array(
            'ID',
            'user_login',
            'user_nicename',
            'user_email',
            'user_url',
            'user_registered',
            'user_status',
            'display_name',
            'first_name',
            'last_name'
        );
    }

    public function get_fields()
    {
        /**
         * @var \WPDB
         */
        global $wpdb;

        $fields = [
            'key' => 'main',
            'label' => __('User', 'jc-importer'),
            'loop' => true,
            'fields' => [],
            'children' => [
                'custom_fields' => [
                    'key' => 'custom_fields',
                    'label' => __('Custom Fields', 'jc-importer'),
                    'loop' => true,
                    'loop_fields' => ['meta_key', 'meta_value'],
                    'fields' => [],
                    'children' => []
                ]
            ]

        ];

        $fields['fields'] = $this->get_core_fields();
        $fields['fields'][] = 'role';
        $fields['fields'][] = 'description';

        // user meta
        $meta_fields = $wpdb->get_col("SELECT DISTINCT meta_key FROM " . $wpdb->usermeta . " WHERE user_id IN (SELECT DISTINCT ID FROM " . $wpdb->users . " )");
        $custom_file_fields = apply_filters('iwp/exporter/user/custom_file_id_fields', []);
        foreach ($meta_fields as $field) {

            if (in_array($field, ['first_name', 'last_name', 'description'])) {
                continue;
            }

            $fields['children']['custom_fields']['fields'][] = $field;

            if (in_array($field, $custom_file_fields)) {
                $fields['children']['custom_fields']['fields'][] = $field . '::id';
                $fields['children']['custom_fields']['fields'][] = $field . '::url';
                $fields['children']['custom_fields']['fields'][] = $field . '::title';
                $fields['children']['custom_fields']['fields'][] = $field . '::alt';
                $fields['children']['custom_fields']['fields'][] = $field . '::caption';
                $fields['children']['custom_fields']['fields'][] = $field . '::description';
            }
        }

        $fields['children']['custom_fields']['fields'] = apply_filters('iwp/exporter/user/custom_field_list', $fields['children']['custom_fields']['fields'], null);
        $fields = apply_filters('iwp/exporter/user/fields', $fields, 'user');

        return $this->parse_fields($fields);
    }

    public function have_records($exporter_id)
    {
        $query_args = [];
        $query_args = apply_filters('iwp/exporter/user_query', $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/%d/user_query', $exporter_id), $query_args);

        $query_args = wp_parse_args($query_args, [
            'number' => -1,
            'fields' => 'ids'
        ]);

        $this->query = new \WP_User_Query($query_args);
        $this->items = $this->query->results;

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
        $user = get_user_by('id', $this->items[$i]);

        $this->record = new ExporterRecord((array)$user->data, 'user');
        $this->record = apply_filters('iwp/exporter/user/setup_data', $this->record, 'user');

        return true;
    }

    public function get_record_data($value, $key, $record)
    {
        switch ($key) {
            case 'custom_fields':
                $value = get_user_meta($record['ID']);

                if (isset($value['first_name'])) {
                    unset($value['first_name']);
                }

                if (isset($value['last_name'])) {
                    unset($value['last_name']);
                }

                if (isset($value['description'])) {
                    unset($value['description']);
                }

                $value = $this->modify_custom_field_data($value, 'user');
                break;
            case 'role':
                $userdata = get_userdata($record['ID']);
                $value = (array)$userdata->roles;
                break;
            case 'first_name':
            case 'last_name':
            case 'description':

                $value = get_user_meta($record['ID'], $key, true);
                break;
        }
        return $value;
    }
}
