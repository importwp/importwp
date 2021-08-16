<?php

/**
 * Plugin Name: ImportWP
 * Plugin URI: https://www.importwp.com
 * Description: ImportWP allows you to select which WordPress data you want to export csv and xml files using the visual data select tool.
 * Author: James Collings <james@jclabs.co.uk>
 * Version: 2.2.5 
 * Author URI: https://www.importwp.com
 * Network: True
 */

$iwp_base_path = dirname(__FILE__);

if (!defined('IWP_VERSION')) {
	define('IWP_VERSION', '2.2.5');
}

if (!defined('IWP_MINIMUM_PHP_VERSION')) {
	define('IWP_MINIMUM_PHP_VERSION', '5.4');
}

if (!defined('IWP_POST_TYPE')) {
	define('IWP_POST_TYPE', 'iwp-importer');
}

if (version_compare(PHP_VERSION, IWP_MINIMUM_PHP_VERSION, '>=')) {
	require_once $iwp_base_path . '/class/autoload.php';
	require_once $iwp_base_path . '/setup-iwp.php';
}
