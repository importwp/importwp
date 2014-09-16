<?php

class PostMapperTest extends WP_UnitTestCase {

	var $importer;

	public function setUp() {
		parent::setUp();
		$this->importer = $GLOBALS['jcimporter'];
	}

	/**
	 * @group core
	 * @group mapper
	 */
	public function test_insert() {

		$slug = 'test-' . md5( time() );

		$mapper = new JC_PostMapper();

		// check for basic insert success
		$result = $mapper->insert_data( array( 'post_title' => 'ABC', 'post_name' => $slug ), 'publish', 'post' );
		$this->assertGreaterThan( 0, $result );

		// check for error
		$result = $mapper->insert_data( array(), 'publish', 'post' );
		$this->assertInstanceOf( 'WP_Error', $result );

	}

	/**
	 * check to see if a post exists with the same slug
	 * 
	 * @depends test_insert
	 * @group core
	 * @group mapper
	 */
	public function test_exists() {

		$slug    = 'test-insert';
		$post_id = wp_insert_post( array( 'post_title'  => 'INSERTED',
		                                  'post_name'   => $slug,
		                                  'post_type'   => 'post',
		                                  'post_status' => 'publish'
			) );

		$mapper      = new JC_PostMapper();
		$result_id = $mapper->exists_data( array( 'post_name' => $slug ), array( 'post_name' ), 'post', 'publish' );

		$this->assertGreaterThan( 0, $result_id );
		$this->assertEquals( $post_id, $result_id );

	}

	/**
	 * check if no slug is set, it will be generated from the title and used to see if a post exists
	 * 
	 * @depends test_insert
	 * @group core
	 * @group mapper
	 */
	public function test_title_exists() {

		$slug    = 'test-insert-123';
		$post_id = wp_insert_post( array( 'post_title'  => 'test insert 123',
		                                  'post_type'   => 'post',
		                                  'post_status' => 'publish'
		) );

		wp_insert_post( array( 'post_title'  => 'test insert 456',
		                                  'post_type'   => 'post',
		                                  'post_status' => 'publish'
		) );

		$mapper      = new JC_PostMapper();
		$result_id = $mapper->exists_data( array( 'post_name' => $slug ), array( 'post_name' ), 'post', 'publish' );

		$this->assertGreaterThan( 0, $result_id );
		$this->assertEquals( $post_id, $result_id );

	}

	/**
	 * @depends test_exists
	 * @group core
	 * @group mapper
	 */
	public function test_update() {

		$slug    = 'test-' . md5( time() );
		$post_id = wp_insert_post( array( 'post_title'  => 'INSERTED',
		                                  'post_type'   => 'post',
		                                  'post_status' => 'publish'
			) );

		$mapper = new JC_PostMapper();
		$result = $mapper->update_data( $post_id, array( 'post_title' => 'UPDATED' ), 'post' );
		$this->assertEquals( 'UPDATED', get_the_title( $result ) );
	}
}