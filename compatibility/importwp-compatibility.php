<?php

function iwp_compat_is_rest_request()
{
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    if (preg_match('/iwp\/v1\/importer\/[\d]+\/(?:init|run|status)/', $request_uri) === 1) {
        return true;
    }

    return false;
}

function iwp_compat_is_importer_request()
{
    $rest_request = iwp_compat_is_rest_request();
    if ($rest_request) {
        return true;
    }

    return false;
}

add_filter('option_active_plugins', function ($plugins) {

    if (!is_array($plugins) || empty($plugins) || !iwp_compat_is_importer_request()) {
        return $plugins;
    }

    $blacklist = (array)get_option('iwp_compat_blacklist', []);

    if (!empty($blacklist)) {
        foreach ($plugins as $key => $plugin) {
            if (in_array($plugin, $blacklist)) {
                unset($plugins[$key]);
            }
        }
    }

    return $plugins;
});
