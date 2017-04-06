<?php
/**
 * Premium plugin version hints
 *
 * @package ImportWP
 * @author James Collings <james@jclabs.co.uk>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class ImportWP_Premium
 */
class ImportWP_Premium {

	/**
	 * Custom field class name
	 *
	 * @var string
	 */
	private $_cf_class = 'JCI_Custom_Fields_Template';

	/**
	 * ImportWP_Premium constructor.
	 */
	function __construct() {
		add_action( 'admin_init', array( $this, 'init' ) );
	}

	/**
	 * Enable / Disable premium messages depending on what add-ons / plugins are loaded
	 */
	function init() {
		// Custom fields preview.
		if ( ! class_exists( $this->_cf_class ) ) {
			add_action( 'jci/after_template_fields', array( $this, 'show_custom_fields_block' ), 11, 3 );
		}
	}

	/**
	 * Display custom fields tab, prompting user to upgrade to get access
	 *
	 * @param int $importer_id Importer Id
	 * @param string $group_id Template group name
	 * @param array $group Template group settings
	 */
	function show_custom_fields_block( $importer_id = 0, $group_id, $group ) {

		// escape if not post
		if ( $group['import_type'] !== 'post' && $group['import_type'] !== 'user' ) {
			return;
		}
		?>
		<div class="jci-custom-fields jci-group-section" data-section-id="Custom Fields">
			<div id="upgrade" class="iwp-error"><p>Upgrade to ImportWP Pro to import custom fields and many other
					features, <a target="_blank"
					             href="<?php echo admin_url( 'admin.php?page=jci-settings&tab=premium' ); ?>">Find out
						more</a>.</p></div>
		</div>
		<?php
	}
}

new ImportWP_Premium();
