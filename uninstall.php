<?php
// if uninstall.php is not called by WordPress, die

use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Migration\Migrations;
use ImportWP\Container;

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

do_action('iwp/uninstall_plugin');

// Escape if cleanup has not been enabled.
$uninstall_enabled = get_option('iwp_settings');
if (!isset($uninstall_enabled['cleanup']) || true !== $uninstall_enabled['cleanup']) {
    return;
}

$iwp_base_path = dirname(__FILE__);
if (file_exists($iwp_base_path . '/importwp-pro.php')) {
    require_once $iwp_base_path . '/importwp-pro.php';
} else {
    require_once $iwp_base_path . '/jc-importer.php';
}

// 1. Delete all importers, Delete all importer files

/**
 * @var ImporterManager $importer_manager
 */
$importer_manager = Container::getInstance()->get('importer_manager');
$importers = $importer_manager->get_importers();
foreach ($importers as $importer) {

    $files = $importer->getFiles();
    foreach ($files as $file) {
        @unlink($file);
    }

    $importer->delete();
}

// 2. Delete /wp-content/importwp folder
require_once ABSPATH . '/wp-admin/includes/class-wp-filesystem-direct.php';
$fileSystemDirect = new \WP_Filesystem_Direct(false);
$upload_dir = wp_upload_dir();

$fileSystemDirect->rmdir($upload_dir['basedir'] . DIRECTORY_SEPARATOR  . 'importwp', true);
$fileSystemDirect->rmdir($upload_dir['basedir'] . DIRECTORY_SEPARATOR  . 'exportwp', true);


// 3. Uninstall DB
$migrations = new Migrations();
$migrations->uninstall();
