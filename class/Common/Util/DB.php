<?php

namespace ImportWP\Common\Util;

class DB
{
    public static function get_table_name($table)
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        switch ($table) {
            case 'claim':
                return $wpdb->prefix . 'iwp_queue_claims';
            case 'queue':
                return $wpdb->prefix . 'iwp_queues';
            case 'import':
                return $wpdb->prefix . 'iwp_imports';
            case 'queue_error':
                return $wpdb->prefix . 'iwp_queue_errors';
        }

        return false;
    }
}
