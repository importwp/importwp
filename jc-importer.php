<?php

/**
 * Plugin Name: Import WP
 * Plugin URI: https://www.importwp.com
 * Description: Import WP is a powerful Importer & Exporter with a visual data selection tool that makes it easy to Export or Import any XML or CSV file to WordPress.
 * Author: James Collings <james@jclabs.co.uk>
 * Version: 2.5.3 
 * Author URI: https://www.importwp.com
 * Network: True
 */

$iwp_base_path = dirname(__FILE__);

if (!defined('IWP_VERSION')) {
	define('IWP_VERSION', '2.5.3');
}

if (!defined('IWP_MINIMUM_PHP_VERSION')) {
	define('IWP_MINIMUM_PHP_VERSION', '5.4');
}

if (!defined('IWP_POST_TYPE')) {
	define('IWP_POST_TYPE', 'iwp-importer');
}

if (!defined('EWP_POST_TYPE')) {
    define('EWP_POST_TYPE', 'iwp-exporter');
}

if (version_compare(PHP_VERSION, IWP_MINIMUM_PHP_VERSION, '>=')) {
	require_once $iwp_base_path . '/class/autoload.php';
	require_once $iwp_base_path . '/setup-iwp.php';
}
