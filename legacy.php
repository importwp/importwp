<?php

if (defined('IWP_CRON_TOKEN') && !empty(IWP_CRON_TOKEN)) {

    if (isset($_GET['iwp_cron_token']) && $_GET['iwp_cron_token'] === IWP_CRON_TOKEN) {
        add_action('wp_loaded', 'iwp_capture_cron', 9);
    }
} else {
    add_action('iwp_cron_runner', 'iwp_capture_cron', 9);
}

function iwp_capture_cron()
{

    if (!defined('IWP_PRO_VERSION') || version_compare(IWP_PRO_VERSION, '2.11.0', '>')) {
        return;
    }
    add_action('update_postmeta', 'iwp_cron_polyfill', 10, 4);
}

function iwp_cron_polyfill($meta_id, $object_id, $meta_key, $meta_value)
{
    if ($meta_key != '_iwp_session') {
        return;
    }

    /**
     * @var \WPDB $wpdb
     */
    global $wpdb;

    $current_time = time();
    $timestamp = $current_time + 5;
    $meta_key = '_iwp_session_cron_timestamp';

    // if there is no timestamp add one.
    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s",
            [$object_id, $meta_key]
        )
    );
    if (empty($count)) {
        update_post_meta($object_id, $meta_key, 0);
    }

    $result = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE post_id=%d AND meta_value < %s AND meta_key=%s",
            [$timestamp, $object_id, $current_time, $meta_key]
        )
    );
    if ($result <= 0) {
        // make sure the same importer is not triggered within the same 5 second period
        exit;
    }

    update_post_meta($object_id, '_iwp_cron_last_ran', current_time('timestamp'));
}
