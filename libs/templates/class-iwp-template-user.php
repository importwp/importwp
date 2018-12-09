<?php

class IWP_Template_User extends IWP_Template_Base {

	public $_name = 'user';

	public $_unique = array( 'user_email' );

	protected $_group = 'user';

	protected $_import_type = 'user';

	protected $_import_type_name = 'user';

	protected $_settings = array(
		'unique' => array( 'user_email' ),
	);

	public function __construct() {

		parent::__construct();

		$this->register_basic_field('Username', 'user_login');
		$this->register_basic_field('Email', 'user_email');
		$this->register_basic_field('First Name', 'first_name');
		$this->register_basic_field('Last Name', 'last_name');
		$this->register_basic_field('Website', 'user_url');
		$this->register_basic_field('Password', 'user_pass');

		global $wp_roles;
		$roles = array();
		foreach ( $wp_roles->roles as $role => $role_arr ) {
			$roles[ $role ] = $role_arr['name'];
		}
		$this->register_basic_field('Role', 'role', array(
			'options' => $roles
        ));

		$this->register_basic_field('Nice Name', 'user_nicename');
		$this->register_basic_field('Display Name', 'display_name');
		$this->register_basic_field('Nickname', 'nickname');
		$this->register_basic_field('Description', 'description');

		// Settings Tab
		$this->register_section('Settings', 'settings');
		$this->register_enable_toggle('Enable Nice Name Field', 'enable_user_nicename', 'settings', array(
			'user_nicename',
		));
		$this->register_enable_toggle('Enable Display Name Field', 'enable_display_name', 'settings', array(
			'display_name',
		));
		$this->register_enable_toggle('Enable Nickname Field', 'enable_user_nicename', 'settings', array(
			'nickname',
		));
		$this->register_enable_toggle('Enable Description Field', 'enable_description', 'settings', array(
			'description',
		));
		$this->register_enable_toggle('Enable Role Field', 'enable_role', 'settings', array(
			'role',
		));
		$this->register_enable_toggle('Enable Password Field', 'enable_pass', 'settings', array(
			'user_pass',
		));
		$this->register_checkbox('Generate Password', 'generate_pass', 'settings');
		$this->register_checkbox('Send User Registration', 'notify_reg', 'settings');

		add_action( 'jci/after_user_insert', array( $this, 'after_user_insert' ), 10, 1 );

		add_filter( 'jci/log_user_columns', array( $this, 'log_user_columns' ) );
		add_action( 'jci/log_user_content', array( $this, 'log_user_content' ), 10, 2 );

		// Run after base template has removed virtual fields
		add_filter( 'jci/before_' . $this->get_name() . '_group_save', array( $this, 'override_user_pass' ), 110, 1 );
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
	 *
	 * @return array
	 */
	public function override_user_pass( $data ) {

		$generate_pass = $this->get_field_value('generate_pass');

		// generate password
		if ( $generate_pass === '1' && ( ! isset( $data['user_pass'] ) || empty( $data['user_pass'] ) ) ) {
			$data['user_pass'] = wp_generate_password( 10 );
		}

		return $data;
	}

	public function after_user_insert( $user_id ) {

		$notify_reg = $this->get_field_value('notify_reg');
		if ( $notify_reg === '1' ) {
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
				echo $data[$this->get_group()]['ID'];
				break;
			case 'username':
				echo $data[$this->get_group()]['user_login'];
				break;
			case 'name':
				echo $data[$this->get_group()]['first_name'] . ' ' . $data[$this->get_group()]['last_name'];
				break;
			case 'email':
				echo $data[$this->get_group()]['user_email'];
				break;
			case 'method':

				if ( $data[$this->get_group()]['_jci_type'] == 'I' ) {
					echo 'Inserted';
				} elseif ( $data[$this->get_group()]['_jci_type'] == 'U' ) {
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