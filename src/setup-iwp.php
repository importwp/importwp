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
    if (function_exists(('import_wp'))) {
        import_wp();
    }
}
add_action('plugins_loaded', 'iwp_loaded');
