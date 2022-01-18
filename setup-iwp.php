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

function iwp_cli_loaded()
{
    // register with wp-cli if it's running, and command hasn't already been defined elsewhere
    if (defined('WP_CLI') && WP_CLI && class_exists('ImportWP\Common\Cli\Command')) {
        \ImportWP\Common\Cli\Command::register();
    }
}
add_action('plugins_loaded', 'iwp_cli_loaded', 20);

function iwp_loaded()
{
    if (function_exists(('import_wp'))) {
        import_wp();
    }
}
add_action('plugins_loaded', 'iwp_loaded');
