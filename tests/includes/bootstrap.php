<?php
/**
 * Load WordPress test environment
 */

/*ini_set( 'display_errors','on' );
error_reporting( E_ALL );

$tests_dir    = dirname( __FILE__ );
$plugin_dir   = dirname( $tests_dir );
$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : $plugin_dir . '/tmp/wordpress-tests-lib';

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( "wpdf/wpdf.php" ),
);

// load the WP testing environment
if( $wp_tests_dir . '/includes/bootstrap.php' ){
	require_once( $wp_tests_dir . '/includes/bootstrap.php' );
}else{
	exit( "Couldn't find path to wordpress-tests/bootstrap.php\n" );
}*/

/**
 * Load WordPress test environment
 */
class ImportWP_Unit_Tests_Bootstrap {

	/** @var \ImportWP_Unit_Tests_Bootstrap instance */
	protected static $instance = null;

	/** @var string directory where wordpress-tests-lib is installed */
	public $wp_tests_dir;

	/** @var string testing directory */
	public $tests_dir;

	/** @var string plugin directory */
	public $plugin_dir;

	public function __construct() {

		ini_set( 'display_errors','on' );
		error_reporting( E_ALL );

		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = dirname( $this->tests_dir );
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : $this->plugin_dir . '/tmp/wordpress-tests-lib';

		// load test function so tests_add_filter() is available
		require_once( $this->wp_tests_dir . '/includes/functions.php' );

		tests_add_filter( 'muplugins_loaded', array( $this, 'load_plugin' ) );

		// load the WP testing environment
		require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );

		require_once 'unit-tests/includes/factory.php';
	}

	/**
	 * Load Plugin
	 *
	 * @since 2.2
	 */
	public function load_plugin() {
		tests_add_filter( 'setup_theme', array( $this, 'install_jci_db' ));

		require_once( $this->plugin_dir . '/jc-importer.php' );
	}

	function install_jci_db() {

		require_once $this->plugin_dir . '/libs/class-iwp-migrations.php';
		$migrations = new IWP_Migrations();
		$migrations->install();
	}

	/**
	 * Get the single class instance
	 *
	 * @since 2.2
	 * @return ImportWP_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

ImportWP_Unit_Tests_Bootstrap::instance();