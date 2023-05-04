<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\MapperInterface;

class UserMapper extends AbstractMapper implements MapperInterface
{
    /**
     * @var \WP_User_Query
     */
    private $query;

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
            'label' => 'User',
            'loop' => true,
            'fields' => [],
            'children' => [
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
        $fields['fields'][] = 'role';
        $fields['fields'][] = 'first_name';
        $fields['fields'][] = 'last_name';
        $fields['fields'][] = 'description';

        // user meta
        $meta_fields = $wpdb->get_col("SELECT DISTINCT meta_key FROM " . $wpdb->usermeta . " WHERE user_id IN (SELECT DISTINCT ID FROM " . $wpdb->users . " )");
        foreach ($meta_fields as $field) {

            if (in_array($field, ['first_name', 'last_name', 'description'])) {
                continue;
            }

            $fields['children']['custom_fields']['fields'][] = $field;
        }

        $fields['children']['custom_fields']['fields'] = apply_filters('iwp/exporter/user/custom_field_list', $fields['children']['custom_fields']['fields'], null);

        return $fields;
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
        $this->record = (array)$user->data;
        $this->record['custom_fields'] = get_user_meta($this->record['ID']);

        $userdata = get_userdata($this->record['ID']);
        $this->record['role'] = (array)$userdata->roles;

        foreach (['first_name', 'last_name', 'description'] as $field) {

            if (isset($this->record['custom_fields'][$field])) {

                $this->record[$field] = $this->record['custom_fields'][$field][0];
                unset($this->record['custom_fields'][$field]);
            } else {

                $this->record[$field] = '';
            }
        }

        return true;
    }
}
