<?php
/**
 * Plugin Name: ImportWP
 * Plugin URI: https://www.importwp.com
 * Description: Wordpress CSV/XML Importer Plugin, Easily import users, posts, custom post types and taxonomies from XML or CSV files
 * Author: James Collings <james@jclabs.co.uk>
 * Author URI: http://www.jamescollings.co.uk
 * Version: 1.1.7
 *
 * @package ImportWP
 * @author James Collings <james@jclabs.co.uk>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// required packages
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/mappers/class-iwp-mapper.php';
require_once __DIR__ . '/libs/mappers/class-iwp-mapper-post.php';
require_once __DIR__ . '/libs/mappers/class-iwp-mapper-user.php';
require_once __DIR__ . '/libs/mappers/class-iwp-mapper-tax.php';

require_once __DIR__ . '/libs/class-iwp-exception.php';

// libs.
require_once __DIR__ . '/libs/class-iwp-premium.php';

// attachments.
require_once __DIR__ . '/libs/attachments/class-iwp-attachment.php';
require_once __DIR__ . '/libs/attachments/class-iwp-attachment-ftp.php';
require_once __DIR__ . '/libs/attachments/class-iwp-attachment-curl.php';
require_once __DIR__ . '/libs/attachments/class-iwp-attachment-upload.php';
require_once __DIR__ . '/libs/attachments/class-iwp-attachment-string.php';
require_once __DIR__ . '/libs/attachments/class-iwp-attachment-local.php';

// parsers.
require_once __DIR__ . '/libs/parsers/class-iwp-field-parser.php';
require_once __DIR__ . '/libs/parsers/class-iwp-csv-field-parser.php';
require_once __DIR__ . '/libs/parsers/class-iwp-xml-field-parser.php';
require_once __DIR__ . '/libs/parsers/class-iwp-csv-parser.php';
require_once __DIR__ . '/libs/parsers/class-iwp-xml-parser.php';

// templates.
require_once __DIR__ . '/libs/templates/class-iwp-template.php';
require_once __DIR__ . '/libs/templates/class-iwp-template-user.php';
require_once __DIR__ . '/libs/templates/class-iwp-template-post.php';
require_once __DIR__ . '/libs/templates/class-iwp-template-page.php';
require_once __DIR__ . '/libs/templates/class-iwp-template-tax.php';

require_once __DIR__ . '/libs/class-iwp-form-builder.php';
require_once __DIR__ . '/libs/functions.php';

/**
 * Class JC_Importer
 *
 * Core plugin class
 */
class JC_Importer {

	/**
	 * Single instance of class
	 *
	 * @var null
	 */
	protected static $_instance = null;
	/**
	 * Loaded Importer Class
	 *
	 * @var IWP_Importer
	 */
	public $importer;
	/**
	 * Current Plugin Version
	 *
	 * @var string
	 */
	protected $version = '1.1.7';
	/**
	 * Plugin base directory
	 *
	 * @var string
	 */
	protected $plugin_dir;
	/**
	 * Plugin base url
	 *
	 * @var string
	 */
	protected $plugin_url;
	/**
	 * List of available template strings
	 *
	 * @var array[string]
	 */
	protected $templates = array();
	/**
	 * Current plugin database schema version
	 *
	 * @var int
	 */
	protected $db_version = 2;
	/**
	 * Debug flag
	 *
	 * @var bool
	 */
	protected $debug = false;
	/**
	 * Plugin Text Strings
	 *
	 * @var IWP_Text
	 */
	private $text;

	/**
	 * JC_Importer constructor.
	 */
	public function __construct() {

		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugins_url( '/', __FILE__ );

		require_once __DIR__ . '/libs/class-iwp-text.php';
		$this->text = new IWP_Text();

		add_action( 'init', array( $this, 'init' ) );

		// activation.
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		add_action( 'admin_init', array( $this, 'load_plugin' ) );
	}

	/**
	 * Return current instance of class
	 *
	 * @return JC_Importer
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Setup plugin, loading all classes
	 *
	 * @return void
	 */
	public function init() {

		require_once __DIR__ . '/libs/class-iwp-debug.php';
		if(defined('IWP_DEBUG') && IWP_DEBUG === true){
			IWP_Debug::$_debug = true;
		}

		do_action( 'jci/before_init' );

		$this->register_post_types();

		// register templates.
		$this->templates = apply_filters( 'jci/register_template', $this->templates );

		// load importer.
		require_once __DIR__ . '/libs/class-iwp-importer.php';

		// core models.
		require_once __DIR__ . '/libs/class-iwp-importer-settings.php';
		require_once __DIR__ . '/libs/class-iwp-importer-log.php';
		require_once __DIR__ . '/libs/class-iwp-status.php';
		require_once __DIR__ . '/libs/class-iwp-importer-permissions.php';

		if ( is_admin() && current_user_can('manage_options')) {

			// load importer.
			$importer_id = isset( $_GET['import'] ) && ! empty( $_GET['import'] ) ? intval( $_GET['import'] ) : 0;
			if ( $importer_id > 0 ) {
				$this->importer = new IWP_Importer( $importer_id );
			}

			require_once __DIR__ . '/libs/class-iwp-imports-list-table.php';

			require_once __DIR__ . '/libs/class-iwp-admin.php';
			new IWP_Admin( $this );
		}

		if( defined( 'DOING_AJAX' ) && DOING_AJAX ){
			require_once __DIR__ . '/libs/class-iwp-ajax.php';
			new IWP_Ajax( $this );
		}

		IWP_Importer_Settings::init( $this );
		IWP_Importer_Log::init( $this );
		IWP_FormBuilder::init( $this );

		// plugin loaded.
		do_action( 'jci/init' );
	}

	/**
	 * Register jc-imports custom post types
	 *
	 * @return void
	 */
	function register_post_types() {

		// importers.
		register_post_type( 'jc-imports', array(
			'public'            => false,
			'has_archive'       => false,
			'show_in_nav_menus' => false,
			'label'             => 'Importer',
		) );

		// importer csv/xml files.
		register_post_type( 'jc-import-files', array(
			'public'            => false,
			'has_archive'       => false,
			'show_in_nav_menus' => false,
			'label'             => 'Importer Files',
		) );
	}

	/**
	 * Set Plugin Activation
	 *
	 * @return void
	 */
	function activation() {
		add_option( 'Activated_Plugin', 'jcimporter' );
	}

	/**
	 * Run Activation Functions
	 *
	 * @return void
	 */
	function load_plugin() {

		if ( is_admin() ) {

			require_once 'libs/class-iwp-migrations.php';
			$migrations = new IWP_Migrations();

			if ( get_option( 'Activated_Plugin' ) === 'jcimporter' ) {

				$migrations->install();
				delete_option( 'Activated_Plugin' );

			}else{
				$migrations->migrate();
			}
		}
	}

	/**
	 * Get and/or create the plugins tmp directory
	 *
	 * @return string
	 */
	public function get_tmp_dir() {

		$path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads';
		if ( ! is_dir( $path ) ) {
			mkdir( $path );
		}

		$path .= DIRECTORY_SEPARATOR . 'importwp';
		if ( ! is_dir( $path ) ) {
			mkdir( $path );
		}

		return $path;
	}

	/**
	 * Get plugin directory
	 *
	 * @return string
	 */
	public function get_plugin_dir() {
		return $this->plugin_dir;
	}

	/**
	 * Get plugin url
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Is debug enabled?
	 *
	 * @return bool
	 */
	public function is_debug() {
		return $this->debug;
	}

	/**
	 * Get importer template
	 *
	 * @param string $template Template name.
	 *
	 * @return mixed|string
	 */
	public function get_template( $template ) {

		if ( isset( $this->templates[ $template ] ) ) {
			$temp                         = $this->templates[ $template ];
			$this->templates[ $template ] = new $temp;

			return $this->templates[ $template ];
		}

		return false;
	}

	/**
	 * Get importer templates
	 *
	 * @return array
	 */
	public function get_templates() {
		return $this->templates;
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get text class
	 *
	 * @return IWP_Text
	 */
	public function text() {
		return $this->text;
	}

	public function get_tmp_config_path($id){
		return JCI()->get_tmp_dir() . DIRECTORY_SEPARATOR . sprintf('temp-config-%d.json', $id);
	}
}

/**
 * Globally access JC_Importer instance.
 *
 * @return JC_Importer
 */
function JCI() {
	return JC_Importer::instance();
}

$GLOBALS['jcimporter'] = JCI();
