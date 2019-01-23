<?php

class UserMapperTest extends WP_UnitTestCase {

	var $importer;

	public function setUp() {
		parent::setUp();
		$this->importer = $GLOBALS['jcimporter'];
	}

	/**
	 * @group core
	 * @group mapper
	 * @group user_mapper
	 */
	public function test_insert() {

		$mapper = new JC_UserMapper();
		$result = $mapper->insert( 'user', array(
			'user_email' => 'james@test.com',
			'user_login' => 'jcadmin',
			'user_pass'  => 'pass'
		) );
		$user   = new WP_User_Query( array(
			'search'         => $result,
			'search_columns' => array( 'ID' )
		) );

		$this->assertEquals( 1, $user->total_users );
	}

	/**
	 * @expectedException IWP_Exception
	 * @group core
	 * @group mapper
	 * @group user_mapper
	 */
	public function test_insert_no_login() {

		$mapper = new JC_UserMapper();
		$result = $mapper->insert( 'user', array(
			'user_email' => 'james@test.com'
		) );

	}

	/**
	 * @group core
	 * @group mapper
	 * @group user_mapper
	 */
	public function test_add_meta_data(){
		$mapper = new JC_UserMapper();
		$result = $mapper->insert( 'user', array(
			'user_email' => 'james2@test.com',
			'user_login' => 'jcadmin2',
			'user_pass'  => 'pass2',
			'_test_user_meta' => 'yes'
		) );

		$this->assertEquals('yes', get_user_meta( $result, '_test_user_meta', true ));
	}

	/**
	 * @group core
	 * @group mapper
	 * @group user_mapper
	 */
	public function test_edit_meta_data(){
		$mapper = new JC_UserMapper();
		$result = $mapper->insert( 'user', array(
			'user_email' => 'james3@test.com',
			'user_login' => 'jcadmin3',
			'user_pass'  => 'pass3',
			'_test_user_meta' => 'one'
		) );

		$this->assertEquals('one', get_user_meta( $result, '_test_user_meta', true ));

		$mapper = new JC_UserMapper();
		$result = $mapper->update($result, 'user', array(
			'user_email' => 'james3@test.com',
			'user_login' => 'jcadmin3',
			'user_pass'  => 'pass3',
			'_test_user_meta' => 'two'
		) );

		$this->assertEquals('two', get_user_meta( $result, '_test_user_meta', true ));
	}
}