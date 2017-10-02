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
	 * Custom post type class name
	 *
	 * @var string
	 */
	private $_cpt_class = 'ImportWP_CustomPostTypes';

	/**
	 * Custom post datasource class name
	 *
	 * @var string
	 */
	private $_pd_class = 'JCI_Post_Datasource';

	/**
	 * Custom cron class name
	 *
	 * @var string
	 */
	private $_cron_class = 'JCI_Cron';

	/**
	 * ImportWP_Premium constructor.
	 */
	function __construct() {
		add_action( 'admin_init', array( $this, 'init' ) );

		// add form validation for extra settings, stop form being submitted
		add_filter( 'jci/setup_forms', array( $this, 'add_form_cpt_validation' ), 999, 2 );
		add_filter( 'jci/setup_forms', array( $this, 'add_form_pd_validation' ), 999, 2 );
	}

	/**
	 * Enable / Disable premium messages depending on what add-ons / plugins are loaded
	 */
	function init() {
		// Custom fields preview.
		if ( ! class_exists( $this->_cf_class ) ) {
			add_action( 'jci/after_template_fields', array( $this, 'show_custom_fields_block' ), 11, 3 );
		}

		// Custom post type preview
		if ( ! class_exists( $this->_cpt_class ) ) {
			add_action( 'jci/output_template_option', array( $this, 'display_custom_field_dropdown' ) );
		}

		if ( ! class_exists( $this->_cron_class ) ) {
			add_action( 'jci/output_datasource_section', array( $this, 'display_cron_display' ) );
			add_action( 'jci/importer_setting_section', array( $this, 'output_cron_edit_settings' ) );
		}

		if ( ! class_exists( $this->_pd_class ) ) {
			add_action( 'jci/output_datasource_option', array( $this, 'display_post_datasource_option' ) );
			add_action( 'jci/output_datasource_section', array( $this, 'display_post_datasource_create_form' ) );
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
            <div class="iwp-upgrade__block">
                <p>Upgrade to import custom fields and many other
                    features, <a target="_blank"
                                 href="<?php echo admin_url( 'admin.php?page=jci-settings&tab=premium' ); ?>">Click here
                        to find out
                        more</a>.</p>
            </div>
        </div>
		<?php
	}

	function display_custom_field_dropdown() {
		?>
        <div class="hidden show-custom-post-type jci-template-toggle-field">
            <div class="iwp-upgrade__block">
                <p>Upgrade to import custom post types and many other
                    features, <a target="_blank"
                                 href="<?php echo admin_url( 'admin.php?page=jci-settings&tab=premium' ); ?>">Click here
                        to find out
                        more</a>.</p>
            </div>
        </div>
		<?php
	}

	function add_form_cpt_validation( $form, $import_type ) {

		if ( class_exists( $this->_cpt_class ) ) {
			return $form;
		}

		$cpt_rule = array(
			'rule'    => array( 'notEqual', 'custom-post-type' ),
			'message' => 'Upgrade to ImportWP Pro to import to custom post types'
		);

		return $this->appendFormValidationRule( $form, 'template', $cpt_rule );
	}

	private function appendFormValidationRule( $form, $field, $rule ) {

		if ( isset( $form['CreateImporter']['validation'][ $field ]['rule'] ) ) {

			// convert to array
			$temp                                           = array(
				$form['CreateImporter']['validation'][ $field ],
				$rule
			);
			$form['CreateImporter']['validation'][ $field ] = $temp;

		} else {
			// add rule onto end of array
			$form['CreateImporter']['validation'][ $field ][] = $rule;
		}

		return $form;
	}

	function display_cron_display() {
		?>
        <div class="hidden show-remote toggle-field">
            <h4 class="title">4. Setup Import Schedule (Optional)</h4>

            <div class="iwp-upgrade__block">
                <p>Upgrade to schedule imports to run and many other
                    features, <a target="_blank"
                                 href="<?php echo admin_url( 'admin.php?page=jci-settings&tab=premium' ); ?>">Click here
                        to find out
                        more</a>.</p>
            </div>
        </div>
		<?php
	}

	/**
	 * Output fields to setup cron
	 */
	public function output_cron_edit_settings() {
		if ( ! in_array( JCI()->importer->get_import_type(), array( 'remote' ), true ) ) {
			return;
		}
		?>
        <div class="jci-group-cron jci-group-section" data-section-id="Schedule Import">
            <div class="cron">
                <div class="iwp-upgrade__block">
                    <p>Upgrade to schedule imports to run and many other
                        features, <a target="_blank"
                                     href="<?php echo admin_url( 'admin.php?page=jci-settings&tab=premium' ); ?>">Click
                            here to find out
                            more</a>.</p>
                </div>
            </div>
        </div>
		<?php

	}

	function display_post_datasource_option() {
		echo JCI_FormHelper::radio( 'import_type', array(
			'label' => '<strong>Push Request</strong> - Receive file sent from remote source',
			'value' => 'post',
			'class' => 'toggle-fields'
		) );
	}

	function display_post_datasource_create_form() {
		?>
        <div class="hidden show-post toggle-field">
            <div class="iwp-upgrade__block">
                <p>Upgrade to import using files submitted via POST requests and many other
                    features, <a target="_blank"
                                 href="<?php echo admin_url( 'admin.php?page=jci-settings&tab=premium' ); ?>">Click here
                        to find out
                        more</a>.</p>
            </div>
        </div>
		<?php
	}

	function add_form_pd_validation( $form, $import_type ) {

		if ( class_exists( $this->_pd_class ) ) {
			return $form;
		}

		$cpt_rule = array(
			'rule'    => array( 'notEqual', 'post' ),
			'message' => 'Upgrade to ImportWP Pro to import to custom post types'
		);

		return $this->appendFormValidationRule( $form, 'import_type', $cpt_rule );
	}
}

new ImportWP_Premium();
