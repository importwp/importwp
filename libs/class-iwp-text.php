<?php
/**
 * Text library
 *
 * @package ImportWP
 * @author James Collings
 * @created 24/07/2017
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class IWP_Text
 */
class IWP_Text {

	/**
	 * Default text
	 *
	 * @var array|null
	 */
	protected $_default = null;

	/**
	 * WPDF_Text constructor.
	 */
	public function __construct() {

		$this->_default = array(
			// Import General Settings
			'import.settings.start_line'               => __( 'Set the row you wish to start your import from.', 'iwp' ),
			'import.settings.row_count'                => __( 'Maximum number of rows to import, leave "0" to ignore.', 'iwp' ),
			'import.settings.record_import_count'      => __( 'Number of items to import at a time, increasing this number may speed up the import, or if your import is timing out decrease it.', 'iwp' ),
			'import.settings.template_type'            => __( 'Set the type of import you are running, once changed hit save all for the page to be changed.', 'iwp' ),
			// Local Upload Settings
			'import.local.local_url'                   => __( 'Enter the full local path to the file you want to import.', 'iwp' ),
			// Local Upload Settings
			'import.remote.remote_url'                 => __( 'Enter the full Url to the remote file you want to import.', 'iwp' ),
			'import.cron.recurring_imports'            => __( '', 'iwp' ),

			// Import CSV Settings
			'import.settings.csv_delimiter'            => __( 'The character which separates the CSV record elements.', 'iwp' ),
			'import.settings.csv_enclosure'            => __( 'The character which is wrapper around the CSV record elements.', 'iwp' ),
			// Import XML Settings
			'import.settings.xml_base_node'            => __( 'This Record Base is the path to the XML records that you want to import.', 'iwp' ),
			// General Template
			'template.default.template_unique+field'   => __( 'Unique field name to check for existing records, leave black to use template default', 'iwp' ),
			// Default post template fields
			'template.default.post_title'              => __( 'Name of the %s.', 'iwp' ),
			'template.default.post_name'               => __( 'The slug is the user friendly and URL valid name of the %s.', 'iwp' ),
			'template.default.post_content'            => __( 'Main WYSIWYG editor content of the %s.', 'iwp' ),
			'template.default.post_excerpt'            => __( 'A custom short extract for the %s.', 'iwp' ),
			'template.default.post_date'               => __( 'The date of the %s , enter in the format "YYYY-MM-DD HH:ii:ss"', 'iwp' ),
			// Default user template fields
			'template.default.user_login'              => __( '', 'iwp' ),
			'template.default.user_email'              => __( '', 'iwp' ),
			'template.default.first_name'              => __( '', 'iwp' ),
			'template.default.last_name'               => __( '', 'iwp' ),
			'template.default.user_url'                => __( '', 'iwp' ),
			'template.default.role'                    => __( '', 'iwp' ),
			'template.default.user_nicename'           => __( '', 'iwp' ),
			'template.default.display_name'            => __( '', 'iwp' ),
			'template.default.nickname'                => __( '', 'iwp' ),
			'template.default.description'             => __( '', 'iwp' ),
			// Default Taxonomy Template
			'template.default.taxonomy_tax'            => __( '', 'iwp' ),
			'template.default.taxonomy_term'           => __( '', 'iwp' ),
			'template.default.taxonomy_permission'     => __( '', 'iwp' ),
			// Default Attachment Template
			'template.default.attachment_location'     => __( '', 'iwp' ),
			'template.default.attachment_permissions'  => __( '', 'iwp' ),
			'template.default.attachment_download'     => __( '', 'iwp' ),
			'template.default.attachment_local_path'   => __( '', 'iwp' ),
			'template.default.attachment_ftp_server'   => __( '', 'iwp' ),
			'template.default.attachment_ftp_username' => __( '', 'iwp' ),
			'template.default.attachment_ftp_password' => __( '', 'iwp' ),
		);
	}

	/**
	 * Get text string
	 *
	 * @param string $key String key.
	 *
	 * @return mixed|string
	 */
	public function get( $key ) {

		return isset( $this->_default[ $key ] ) ? $this->_default[ $key ] : '';
	}
}