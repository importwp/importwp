<?php

class JC_Post_Template extends JC_Importer_Template {

	public $_name = 'post';

	public $_field_groups = array(

		'post' => array(
			'import_type'      => 'post', // which map to use
			'import_type_name' => 'post', // post_type
			'field_type'       => 'single', // single|repeater
			'post_status'      => 'publish', // default group publish
			'group'            => 'post', // group name, for old time sake
			'unique'           => array( 'post_name' ),
			'key'              => array( 'ID', 'post_name' ),
			'relationship'     => array(),
			'attachments'      => 1,
			'taxonomies'       => 1,
			'map'              => array(
				array(
					'title' => 'ID',
					'field' => 'ID'
				),
				array(
					'title' => 'Title',
					'field' => 'post_title'
				),
				array(
					'title' => 'Content',
					'field' => 'post_content'
				),
				array(
					'title' => 'Excerpt',
					'field' => 'post_excerpt'
				),
				array(
					'title' => 'Slug',
					'field' => 'post_name'
				),
				array(
					'title' => 'Status',
					'field' => 'post_status',
					'options' => array('draft' => 'Draft', 'publish' => 'Published', 'pending' => 'Pending', 'future' => 'Future', 'private' => 'Private', 'trash' => 'Trash')
				),
				array(
					'title' => 'Author',
					'field' => 'post_author',
				),
				array(
					'title' => 'Parent',
					'field' => 'post_parent'
				),
				array(
					'title' => 'Order',
					'field' => 'menu_order'
				),
				array(
					'title' => 'Password',
					'field' => 'post_password'
				),
				array(
					'title' => 'Date',
					'field' => 'post_date'
				),
				array(
					'title'  => 'Allow Comments',
					'field'  => 'comment_status',
					'options' => array( 0 => 'Disabled', 1 => 'Enabled' )
				),
				array(
					'title'  => 'Allow Pingbacks',
					'field'  => 'ping_status',
					'options' => array( 'closed' => 'Closed', 'open' => 'Open' )
				),
			)
		)
	);

	public function __construct() {
		parent::__construct();
		add_action( 'jci/after_template_fields', array( $this, 'field_settings' ) );
		add_action( 'jci/save_template', array( $this, 'save_template' ) );

		add_filter( 'jci/log_post_columns', array( $this, 'log_post_columns' ) );
		add_action( 'jci/log_post_content', array( $this, 'log_post_content' ), 10, 2 );

		
		foreach( $this->_field_groups['post']['map'] as &$field){

			
			if( $field['field'] == 'post_author' ){

				/**
				 * Populate authors dropdown
				 */
				$field['options'] = jci_get_user_list();

			}elseif( $field['field'] == 'post_parent' ){

				/**
				 * Populate parent posts pages
				 */
				$field['options'] = jci_get_post_list('post');
			}
		}

		
	}

	public function field_settings( $id ) {

		$template = ImporterModel::getImportSettings( $id, 'template' );
		if ( $template == $this->_name ) {

			$enable_id             = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_id'
				) );
			$enable_post_status    = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_post_status'
				) );
			$enable_post_author    = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_post_author'
				) );
			$enable_post_parent    = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_post_parent'
				) );
			$enable_menu_order     = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_menu_order'
				) );
			$enable_post_password  = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_post_password'
				) );
			$enable_post_date      = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_post_date'
				) );
			$enable_comment_status = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_comment_status'
				) );
			$enable_ping_status    = ImporterModel::getImporterMetaArr( $id, array(
					'_template_settings',
					'enable_ping_status'
				) );

			?>
			<div class="jci-group-settings jci-group-section" data-section-id="settings">
				<div id="jci_post_enable_fields">
					<h4>Fields:</h4>
					<?php
					echo JCI_FormHelper::checkbox( 'template_settings[enable_id]', array(
							'label'   => 'Enable ID Field',
							'checked' => $enable_id
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_post_status]', array(
							'label'   => 'Enable Post Status Field',
							'checked' => $enable_post_status
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_post_author]', array(
							'label'   => 'Enable Author Field',
							'checked' => $enable_post_author
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_post_parent]', array(
							'label'   => 'Enable Parent Field',
							'checked' => $enable_post_parent
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_menu_order]', array(
							'label'   => 'Enable Order Field',
							'checked' => $enable_menu_order
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_post_password]', array(
							'label'   => 'Enable Password Field',
							'checked' => $enable_post_password
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_post_date]', array(
							'label'   => 'Enable Date Field',
							'checked' => $enable_post_date
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_comment_status]', array(
							'label'   => 'Enable Comment Field',
							'checked' => $enable_comment_status
						) );
					echo JCI_FormHelper::checkbox( 'template_settings[enable_ping_status]', array(
							'label'   => 'Enable Ping Field',
							'checked' => $enable_ping_status
						) );
					?>
				</div>
			</div>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {

					// show/hide input fields
					$.fn.jci_enableField('enable_id', 'post-ID');
					$.fn.jci_enableField('enable_menu_order', 'post-menu_order');
					$.fn.jci_enableField('enable_post_password', 'post-post_password');
					$.fn.jci_enableField('enable_post_date', 'post-post_date');

					// optional selects
					$.fn.jci_enableSelectField('enable_post_parent', 'post-post_parent');
					$.fn.jci_enableSelectField('enable_post_status', 'post-post_status');
					$.fn.jci_enableSelectField('enable_post_author', 'post-post_author');
					$.fn.jci_enableSelectField('enable_comment_status', 'post-comment_status');
					$.fn.jci_enableSelectField('enable_ping_status', 'post-ping_status');
				});
			</script>
		<?php
		}
	}

	public function save_template( $id ) {

		$template = ImporterModel::getImportSettings( $id, 'template' );
		if ( $template == $this->_name ) {

			// get template settings
			$enable_id             = isset( $_POST['jc-importer_template_settings']['enable_id'] ) ? $_POST['jc-importer_template_settings']['enable_id'] : 0;
			$enable_post_status    = isset( $_POST['jc-importer_template_settings']['enable_post_status'] ) ? $_POST['jc-importer_template_settings']['enable_post_status'] : 0;
			$enable_post_author    = isset( $_POST['jc-importer_template_settings']['enable_post_author'] ) ? $_POST['jc-importer_template_settings']['enable_post_author'] : 0;
			$enable_post_parent    = isset( $_POST['jc-importer_template_settings']['enable_post_parent'] ) ? $_POST['jc-importer_template_settings']['enable_post_parent'] : 0;
			$enable_menu_order     = isset( $_POST['jc-importer_template_settings']['enable_menu_order'] ) ? $_POST['jc-importer_template_settings']['enable_menu_order'] : 0;
			$enable_post_password  = isset( $_POST['jc-importer_template_settings']['enable_post_password'] ) ? $_POST['jc-importer_template_settings']['enable_post_password'] : 0;
			$enable_post_date      = isset( $_POST['jc-importer_template_settings']['enable_post_date'] ) ? $_POST['jc-importer_template_settings']['enable_post_date'] : 0;
			$enable_comment_status = isset( $_POST['jc-importer_template_settings']['enable_comment_status'] ) ? $_POST['jc-importer_template_settings']['enable_comment_status'] : 0;
			$enable_ping_status    = isset( $_POST['jc-importer_template_settings']['enable_ping_status'] ) ? $_POST['jc-importer_template_settings']['enable_ping_status'] : 0;

			// update template settings
			ImporterModel::setImporterMeta( $id, array( '_template_settings', 'enable_id' ), $enable_id );
			ImporterModel::setImporterMeta( $id, array(
					'_template_settings',
					'enable_post_status'
				), $enable_post_status );
			ImporterModel::setImporterMeta( $id, array(
					'_template_settings',
					'enable_post_author'
				), $enable_post_author );
			ImporterModel::setImporterMeta( $id, array(
					'_template_settings',
					'enable_post_parent'
				), $enable_post_parent );
			ImporterModel::setImporterMeta( $id, array(
					'_template_settings',
					'enable_menu_order'
				), $enable_menu_order );
			ImporterModel::setImporterMeta( $id, array(
					'_template_settings',
					'enable_post_password'
				), $enable_post_password );
			ImporterModel::setImporterMeta( $id, array( '_template_settings', 'enable_post_date' ), $enable_post_date );
			ImporterModel::setImporterMeta( $id, array(
					'_template_settings',
					'enable_comment_status'
				), $enable_comment_status );
			ImporterModel::setImporterMeta( $id, array(
					'_template_settings',
					'enable_ping_status'
				), $enable_ping_status );
		}
	}

	public function before_template_save( $data, $current_row ) {

		global $jcimporter;
		$id = $jcimporter->importer->ID;

		$this->enable_id             = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_id'
			) );
		$this->enable_post_status    = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_post_status'
			) );
		$this->enable_post_author    = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_post_author'
			) );
		$this->enable_post_parent    = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_post_parent'
			) );
		$this->enable_menu_order     = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_menu_order'
			) );
		$this->enable_post_password  = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_post_password'
			) );
		$this->enable_post_date      = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_post_date'
			) );
		$this->enable_comment_status = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_comment_status'
			) );
		$this->enable_ping_status    = ImporterModel::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_ping_status'
			) );
	}

	public function before_group_save( $data, $group_id ) {

		global $jcimporter;
		$id = $jcimporter->importer->ID;

		/**
		 * Clear unenabled fields
		 */
		if ( $this->enable_id == 0 ) {
			unset( $data['ID'] );
		}
		if ( $this->enable_menu_order == 0 ) {
			unset( $data['menu_order'] );
		}
		if ( $this->enable_post_password == 0 ) {
			unset( $data['post_password'] );
		}
		if ( $this->enable_post_date == 0 ) {
			unset( $data['post_date'] );
		}

		/**
		 * Check to see if post_parent
		 */
		if( intval($data['post_parent']) == 0 && $data['post_parent'] != ''){

			$posts = new WP_Query(array(
				'post_type' => 'post',
				'name' => sanitize_title($data['post_parent'])
			));
			if($posts->have_posts() && $posts->post_count == 1){
				$data['post_parent'] = intval($posts->post->ID);
			}
		}

		/**
		 * Check to see if post_author
		 */
		if( intval($data['post_author']) == 0 && $data['post_author'] != ''){

			// get user ('slug', 'email', or 'login')
			$user = get_user_by( 'login', $data['post_author'] );
			if($user){
				$data['post_author'] = $user->ID;
			}else{
				unset($data['post_author']);
			}
		}

		// generate slug from title if no slug present
		if(empty($data['post_name'])){
			$data['post_name'] = sanitize_title( $data['post_title'] );
		}

		return $data;
	}

	/**
	 * Register Post Columns
	 *
	 * @param  array $columns
	 *
	 * @return array
	 */
	public function log_post_columns( $columns ) {

		$columns['post']        = 'Post';
		$columns['taxonomies']  = 'Taxonomies';
		$columns['attachments'] = 'Attachments';
		$columns['method']      = 'Method';

		return $columns;
	}

	/**
	 * Output column data
	 *
	 * @param  array $column
	 * @param  array $data
	 *
	 * @return void
	 */
	public function log_post_content( $column, $data ) {

		switch ( $column ) {
			case 'post':
				echo $data['post']['post_title'];
				break;
			case 'method':

				if ( $data['post']['_jci_type'] == 'I' ) {
					echo 'Inserted';
				} elseif ( $data['post']['_jci_type'] == 'U' ) {
					echo 'Updated';
				}
				break;
		}
	}

}

add_filter( 'jci/register_template', 'register_post_template', 10, 1 );
function register_post_template( $templates = array() ) {
	$templates['post'] = 'JC_Post_Template';

	return $templates;
}