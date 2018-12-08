<?php

class IWP_Template_User extends IWP_Template {

	public $_name = 'user';
	public $_unique = array( 'user_email' );

	public $_field_groups = array(

		'user' => array(
			'import_type'      => 'user',
			'import_type_name' => 'user',
			'field_type'       => 'single',
			'group'            => 'user', // for backwards compatability
			'key'              => array(),
			'unique'           => array( 'user_email' ),
			'relationship'     => array(),
			'map'              => array(
				array(
					'title' => 'Username',
					'field' => 'user_login'
				),
				array(
					'title' => 'Email',
					'field' => 'user_email'
				),
				array(
					'title' => 'First Name',
					'field' => 'first_name'
				),
				array(
					'title' => 'Last Name',
					'field' => 'last_name'
				),
				array(
					'title' => 'Website',
					'field' => 'user_url'
				),
				array(
					'title' => 'Password',
					'field' => 'user_pass'
				),
				array(
					'title' => 'Role',
					'field' => 'role',
				),
				array(
					'title' => 'Nice Name',
					'field' => 'user_nicename',
				),
				array(
					'title' => 'Display Name',
					'field' => 'display_name',
				),
				array(
					'title' => 'Nickname',
					'field' => 'nickname',
				),
				array(
					'title' => 'Description',
					'field' => 'description',
				),

			)
		)
	);

	public function __construct() {
		parent::__construct();
		add_action( 'jci/after_template_fields', array( $this, 'field_settings' ) );
		add_action( 'jci/save_template', array( $this, 'save_template' ) );
		add_action( 'jci/after_user_insert', array( $this, 'after_user_insert' ), 10, 2 );

		add_filter( 'jci/log_user_columns', array( $this, 'log_user_columns' ) );
		add_action( 'jci/log_user_content', array( $this, 'log_user_content' ), 10, 2 );

		// Quick Fix: Skip if we are in an ajax request
		// TODO: Switch this to an ajax search / select instead so we dont have to list all.
		if ( true === wp_doing_ajax() ) {
			return;
		}

		// output role select
		if(isset($_GET['import']) && isset($_GET['action'])) {
			global $wp_roles;
			$test_roles = array();

			foreach ( $wp_roles->roles as $role => $role_arr ) {
				$test_roles[ $role ] = $role_arr['name'];
			}

			foreach ( $this->_field_groups['user']['map'] as &$field ) {

				if ( $field['field'] == 'role' ) {
					$field['options'] = $test_roles;
				}
			}
		}
	}

	/**
	 * Check for errors before any data is saved
	 *
	 * Hooked Action: jci/before_{$template_name}_row_save
	 *
	 * @param  array $data
	 * @param  string $group_id template group name
	 *
	 * @return void
	 * @throws \ImportWP\Importer\Exception\MapperException
	 */
	public function before_template_save( $data, $group_id ) {

		if ( empty( $data['user_login'] ) ) {
			throw new \ImportWP\Importer\Exception\MapperException( "No Username Found" );

		}

		if ( empty( $data['user_email'] ) ) {
			throw new \ImportWP\Importer\Exception\MapperException( "No Email Found" );
		}

		if ( ! empty( $data['user_email'] ) && ! is_email( $data['user_email'] ) ) {
			throw new \ImportWP\Importer\Exception\MapperException( strval( $data['user_email'] ) . " is not a valid email address" );
		}


	}

	/**
	 * Pre Process data before each record saves data
	 *
	 * Hooked Filter: jci/before_{$template_name}_group_save
	 *
	 * @param  array $data
	 * @param  string $group_id template group name
	 *
	 * @return array
	 */
	public function before_group_save( $data, $group_id ) {

		if ( $group_id == 'user' ) {

			/**
			 * @global JC_Importer $jcimporter
			 */
			global $jcimporter;
			$importer_id = $jcimporter->importer->ID;

			$enable_user_nicename = IWP_Importer_Settings::getImporterMetaArr( $importer_id, array(
				'_template_settings',
				'enable_user_nicename'
			) );
			$enable_display_name  = IWP_Importer_Settings::getImporterMetaArr( $importer_id, array(
				'_template_settings',
				'enable_display_name'
			) );
			$enable_nickname      = IWP_Importer_Settings::getImporterMetaArr( $importer_id, array(
				'_template_settings',
				'enable_nickname'
			) );
			$enable_description   = IWP_Importer_Settings::getImporterMetaArr( $importer_id, array(
				'_template_settings',
				'enable_description'
			) );
			$enable_pass          = IWP_Importer_Settings::getImporterMetaArr( $importer_id, array(
				'_template_settings',
				'enable_pass'
			) );
			$enable_role          = IWP_Importer_Settings::getImporterMetaArr( $importer_id, array(
				'_template_settings',
				'enable_role'
			) );

			if ( $enable_user_nicename == 0 ) {
				unset( $data['user_nicename'] );
			}

			if ( $enable_display_name == 0 ) {
				unset( $data['display_name'] );
			}

			if ( $enable_nickname == 0 ) {
				unset( $data['nickname'] );
			}

			if ( $enable_description == 0 ) {
				unset( $data['description'] );
			}

			if ( $enable_pass == 0 ) {
				unset( $data['user_pass'] );
			}

			// generate password
			$generate_pass = IWP_Importer_Settings::getImporterMetaArr( $importer_id, array(
				'_template_settings',
				'generate_pass'
			) );
			if ( $generate_pass == 1 && ( ! isset( $data['user_pass'] ) || empty( $data['user_pass'] ) ) ) {
				$data['user_pass'] = wp_generate_password( 10 );
			}
		}

		return $data;
	}

	/**
	 * Display template settings
	 *
	 * @param  int $id
	 *
	 * @return void
	 */
	public function field_settings( $id ) {

		$template = IWP_Importer_Settings::getImportSettings( $id, 'template' );
		if ( $template == $this->_name ) {

			$enable_pass          = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_pass'
			) );
			$enable_user_nicename = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_user_nicename'
			) );
			$enable_display_name  = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_display_name'
			) );
			$enable_nickname      = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_nickname'
			) );
			$enable_description   = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_description'
			) );
			$generate_pass        = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'generate_pass'
			) );
			$notify_pass          = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'notify_pass'
			) );
			$notify_reg           = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'notify_reg'
			) );
			$enable_role          = IWP_Importer_Settings::getImporterMetaArr( $id, array(
				'_template_settings',
				'enable_role'
			) );
			?>
            <div class="jci-group-settings jci-group-section" data-section-id="settings">
                <h4>Fields</h4>
				<?php
				echo IWP_FormBuilder::checkbox( 'template_settings[enable_user_nicename]', array(
					'label'   => 'Enable Nice Name Field',
					'checked' => $enable_user_nicename
				) );
				echo IWP_FormBuilder::checkbox( 'template_settings[enable_display_name]', array(
					'label'   => 'Enable Display Name Field',
					'checked' => $enable_display_name
				) );
				echo IWP_FormBuilder::checkbox( 'template_settings[enable_nickname]', array(
					'label'   => 'Enable Nickname Field',
					'checked' => $enable_nickname
				) );
				echo IWP_FormBuilder::checkbox( 'template_settings[enable_description]', array(
					'label'   => 'Enable Description Field',
					'checked' => $enable_description
				) );
				echo IWP_FormBuilder::checkbox( 'template_settings[enable_role]', array(
					'label'   => 'Enable Role Field',
					'checked' => $enable_role
				) );
				?>

                <h4>Passwords:</h4>
				<?php
				echo IWP_FormBuilder::checkbox( 'template_settings[enable_pass]', array(
					'label'   => 'Enable Password Field',
					'checked' => $enable_pass
				) );
				echo IWP_FormBuilder::checkbox( 'template_settings[generate_pass]', array(
					'label'   => 'Generate Password',
					'checked' => $generate_pass
				) );
				?>

                <h4>Notifications:</h4>
				<?php
				//echo JCI_FormHelper::checkbox('template_settings[notify_pass]', array('label' => 'Send new Password', 'checked' => $notify_pass));
				echo IWP_FormBuilder::checkbox( 'template_settings[notify_reg]', array(
					'label'   => 'Send User Registration',
					'checked' => $notify_reg
				) );
				?>
            </div>

            <script type="text/javascript">

                jQuery(document).ready(function ($) {

                    // show/hide input fields
                    $.fn.jci_enableField('enable_pass', 'user-user_pass');
                    $.fn.jci_enableField('enable_user_nicename', 'user-user_nicename');
                    $.fn.jci_enableField('enable_display_name', 'user-display_name');
                    $.fn.jci_enableField('enable_nickname', 'user-nickname');
                    $.fn.jci_enableField('enable_description', 'user-description');

                    // optional selects
                    $.fn.jci_enableSelectField('enable_role', 'user-role');
                });

            </script>
			<?php
		}
	}

	/**
	 * Save template fields
	 *
	 * @param  int $id
	 *
	 * @return void
	 */
	public function save_template( $id ) {

		$template = IWP_Importer_Settings::getImportSettings( $id, 'template' );
		if ( $template == $this->_name ) {

			// get template settings
			$enable_pass          = isset( $_POST['jc-importer_template_settings']['enable_pass'] ) ? $_POST['jc-importer_template_settings']['enable_pass'] : 0;
			$enable_role          = isset( $_POST['jc-importer_template_settings']['enable_role'] ) ? $_POST['jc-importer_template_settings']['enable_role'] : 0;
			$enable_user_nicename = isset( $_POST['jc-importer_template_settings']['enable_user_nicename'] ) ? $_POST['jc-importer_template_settings']['enable_user_nicename'] : 0;
			$enable_display_name  = isset( $_POST['jc-importer_template_settings']['enable_display_name'] ) ? $_POST['jc-importer_template_settings']['enable_display_name'] : 0;
			$enable_nickname      = isset( $_POST['jc-importer_template_settings']['enable_nickname'] ) ? $_POST['jc-importer_template_settings']['enable_nickname'] : 0;
			$enable_description   = isset( $_POST['jc-importer_template_settings']['enable_description'] ) ? $_POST['jc-importer_template_settings']['enable_description'] : 0;
			$generate_pass        = isset( $_POST['jc-importer_template_settings']['generate_pass'] ) ? $_POST['jc-importer_template_settings']['generate_pass'] : 0;
			$notify_pass          = isset( $_POST['jc-importer_template_settings']['notify_pass'] ) ? $_POST['jc-importer_template_settings']['notify_pass'] : 0;
			$notify_reg           = isset( $_POST['jc-importer_template_settings']['notify_reg'] ) ? $_POST['jc-importer_template_settings']['notify_reg'] : 0;

			// update template settings
			IWP_Importer_Settings::setImporterMeta( $id, array( '_template_settings', 'enable_pass' ), $enable_pass );
			IWP_Importer_Settings::setImporterMeta( $id, array(
				'_template_settings',
				'enable_user_nicename'
			), $enable_user_nicename );
			IWP_Importer_Settings::setImporterMeta( $id, array(
				'_template_settings',
				'enable_display_name'
			), $enable_display_name );
			IWP_Importer_Settings::setImporterMeta( $id, array( '_template_settings', 'enable_nickname' ), $enable_nickname );
			IWP_Importer_Settings::setImporterMeta( $id, array(
				'_template_settings',
				'enable_description'
			), $enable_description );
			IWP_Importer_Settings::setImporterMeta( $id, array( '_template_settings', 'generate_pass' ), $generate_pass );
			IWP_Importer_Settings::setImporterMeta( $id, array( '_template_settings', 'notify_pass' ), $notify_pass );
			IWP_Importer_Settings::setImporterMeta( $id, array( '_template_settings', 'notify_reg' ), $notify_reg );
			IWP_Importer_Settings::setImporterMeta( $id, array( '_template_settings', 'enable_role' ), $enable_role );
		}
	}

	public function after_user_insert( $user_id, $fields ) {

		/**
		 * @global JC_Importer $jcimporter
		 */
		global $jcimporter;
		$importer_id = $jcimporter->importer->ID;

		$notify_reg = IWP_Importer_Settings::getImporterMetaArr( $importer_id, array( '_template_settings', 'notify_reg' ) );

		if ( $notify_reg == 1 ) {

//			$pass = isset( $fields['user_pass'] ) ? $fields['user_pass'] : '';
            wp_new_user_notification($user_id, null, 'user');
		}
	}

	/**
	 * Register Post Columns
	 *
	 * @param  array $columns
	 *
	 * @return array
	 */
	public function log_user_columns( $columns ) {

		$columns['user_id']  = 'User ID';
		$columns['username'] = 'Username';
		$columns['email']    = 'Email';
		$columns['name']     = 'Name';
		$columns['method']   = 'Method';

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
	public function log_user_content( $column, $data ) {

		switch ( $column ) {
			case 'user_id':
				echo $data['user']['ID'];
				break;
			case 'username':
				echo $data['user']['user_login'];
				break;
			case 'name':
				echo $data['user']['first_name'] . ' ' . $data['user']['last_name'];
				break;
			case 'email':
				echo $data['user']['user_email'];
				break;
			case 'method':

				if ( $data['user']['_jci_type'] == 'I' ) {
					echo 'Inserted';
				} elseif ( $data['user']['_jci_type'] == 'U' ) {
					echo 'Updated';
				}
				break;
		}
	}

}

add_filter( 'jci/register_template', 'register_user_template', 10, 1 );
function register_user_template( $templates = array() ) {
	$templates['user'] = 'IWP_Template_User';

	return $templates;
}