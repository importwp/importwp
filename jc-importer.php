<?php
/**
 * Plugin Name: ImportWP
 * Plugin URI: https://www.importwp.com
 * Description: Wordpress CSV/XML Importer Plugin, Easily import users, posts, custom post types and taxonomies from XML or CSV files
 * Author: James Collings <james@jclabs.co.uk>
 * Author URI: http://www.jamescollings.co.uk
 * Version: 0.7.2
 *
 * @package ImportWP
 * @author James Collings <james@jclabs.co.uk>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// required packages
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Importer/Config/IWP_Config.php';
require_once __DIR__ . '/src/Importer/Mapper/AbstractMapper.php';
require_once __DIR__ . '/src/Importer/Mapper/PostMapper.php';
require_once __DIR__ . '/src/Importer/Mapper/UserMapper.php';
require_once __DIR__ . '/src/Importer/Mapper/TaxMapper.php';

require_once __DIR__ . '/app/core/exceptions.php';

// libs.
require_once __DIR__ . '/app/libs/xmloutput.php';
require_once __DIR__ . '/app/libs/class-importwp-premium.php';

// attachments.
require_once __DIR__ . '/app/attachment/class-jci-attachment.php';
require_once __DIR__ . '/app/attachment/class-jci-ftp-attachments.php';
require_once __DIR__ . '/app/attachment/class-jci-curl-attachments.php';
require_once __DIR__ . '/app/attachment/class-jci-upload-attachments.php';
require_once __DIR__ . '/app/attachment/class-jci-string-attachments.php';
require_once __DIR__ . '/app/attachment/class-jci-local-attachments.php';

// parsers.
require_once __DIR__ . '/app/parsers/class-iwp-field-parser.php';
require_once __DIR__ . '/app/parsers/class-iwp-csv-field-parser.php';
require_once __DIR__ . '/app/parsers/class-iwp-xml-field-parser.php';
require_once __DIR__ . '/app/parsers/class-iwp-csv-parser.php';
require_once __DIR__ . '/app/parsers/class-iwp-xml-parser.php';

// templates.
require_once __DIR__ . '/app/templates/template.php';
require_once __DIR__ . '/app/templates/template-user.php';
require_once __DIR__ . '/app/templates/template-post.php';
require_once __DIR__ . '/app/templates/template-page.php';
require_once __DIR__ . '/app/templates/template-tax.php';

require_once __DIR__ . '/app/helpers/form.php';
require_once __DIR__ . '/app/functions.php';

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
	 * @var JC_Importer_Core
	 */
	public $importer;
	/**
	 * Current Plugin Version
	 *
	 * @var string
	 */
	protected $version = '0.7.2';
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

		require_once $this->plugin_dir . 'app/libs/class-iwp-text.php';
		$this->text = new IWP_Text();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'db_update_check' ) );

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

		require_once $this->plugin_dir . 'app/libs/class-iwp-debug.php';


		do_action( 'jci/before_init' );

		$this->register_post_types();

		// register templates.
		$this->templates = apply_filters( 'jci/register_template', $this->templates );

		// load importer.
		require_once $this->plugin_dir . 'app/core/importer.php';

		// core models.
		require_once $this->plugin_dir . 'app/models/importer.php';
		require_once $this->plugin_dir . 'app/models/log.php';
		require_once $this->plugin_dir . 'app/models/class-iwp-status.php';

		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {

			// load importer.
			$importer_id = isset( $_GET['import'] ) && ! empty( $_GET['import'] ) ? intval( $_GET['import'] ) : 0;
			if ( $importer_id > 0 ) {
				$this->importer = new JC_Importer_Core( $importer_id );
			}

			require_once $this->plugin_dir . 'app/libs/class-iwp-imports-list-table.php';

			require_once $this->plugin_dir . 'app/admin.php';
			new JC_Importer_Admin( $this );

			require_once $this->plugin_dir . 'app/ajax.php';
			new JC_Importer_Ajax( $this );
		}

		ImporterModel::init( $this );
		ImportLog::init( $this );
		JCI_FormHelper::init( $this );

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

		// importer tempaltes.
		register_post_type( 'jc-import-template', array(
			'public'            => false,
			'has_archive'       => false,
			'show_in_nav_menus' => false,
			'label'             => 'Template',
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

			if ( get_option( 'Activated_Plugin' ) === 'jcimporter' ) {

				// scaffold log table.
				require_once 'app/models/schema.php';
				$schema = new JCI_DB_Schema( $this );
				$schema->install();
				delete_option( 'Activated_Plugin' );
			}

			$this->db_update_check();
		}
	}

	/**
	 * Check if database requires an upgrade
	 */
	public function db_update_check() {

		$curr_db = intval( get_site_option( 'jci_db_version' ) );
		if ( is_admin() && $curr_db < $this->db_version ) {

			require_once 'app/models/schema.php';
			$schema = new JCI_DB_Schema( $this );
			$schema->upgrade( $curr_db );

			update_site_option( 'jci_db_version', $this->db_version );
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
