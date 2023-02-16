<?php

namespace ImportWP\Common\Exporter\Mapper;

use ImportWP\Common\Exporter\MapperInterface;

class UserMapper extends AbstractMapper implements MapperInterface
{
    /**
     * @var \WP_User_Query
     */
    private $query;

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


        // user meta
        $meta_fields = $wpdb->get_col("SELECT DISTINCT meta_key FROM " . $wpdb->usermeta . " WHERE user_id IN (SELECT DISTINCT ID FROM " . $wpdb->users . " )");
        foreach ($meta_fields as $field) {
            $fields['children']['custom_fields']['fields'][] = $field;
        }

        $fields['children']['custom_fields']['fields'] = apply_filters('iwp/exporter/user/custom_field_list', $fields['children']['custom_fields']['fields'], null);

        return $fields;
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

    public function setup($i)
    {
        $user = $this->query->results[$i];
        $this->record = (array)$user->data;
        $this->record['custom_fields'] = get_user_meta($this->record['ID']);
        return true;
    }
}
