<?php

function import_wp()
{
    global $iwp;

    if (!is_null($iwp)) {
        return $iwp;
    }

    $iwp = new ImportWP\Free\ImportWPFree();
    $iwp->register();
    return $iwp;
}

function iwp_loaded()
{
    if (function_exists('import_wp_pro')) {
        return;
    }

    if (function_exists('import_wp')) {
        import_wp();
    }
}
add_action('plugins_loaded', 'iwp_loaded');

/**
 * Register IWP Addon
 *
 * @param string $name
 * @param string $id
 * @param callable(AddonInterface) $callback
 * 
 * @return ImportWP\Common\Addon\AddonInterface
 */
function iwp_register_importer_addon($name, $id, $callback)
{
    return new ImportWP\Common\Addon\AddonBase($name, $id, $callback);
}
