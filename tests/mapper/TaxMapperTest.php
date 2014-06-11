<?php

class TaxMapperTest extends WP_UnitTestCase {

	var $importer;

	public function setUp() {
		parent::setUp();
		$this->importer = $GLOBALS['jcimporter'];
	}

	/**
	 * Test a basic insert of term
	 * @group core
	 * @group mapper
	 */
	public function testBasicInsert() {

		$mapper = new JC_TaxMapper();

		// check for basic insert success
		$result = $mapper->insert_data( array( 'term' => 'Term 01', 'taxonomy' => 'category', 'description' => 'Description 01', 'parent' => '', 'slug' => 'term-01' ) );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * @group core
	 * @group mapper
	 */
	public function testNoTermInsert(){

		$mapper = new JC_TaxMapper();

		// check for basic insert success
		$result = $mapper->insert_data( array( 'term' => '', 'taxonomy' => 'category', 'description' => 'Description 01', 'parent' => '', 'slug' => 'term-01' ) );
		$this->assertFalse( $result );
	}

	/**
	 * @group core
	 * @group mapper
	 */
	public function testNoTaxonomyInsert() {

		$mapper = new JC_TaxMapper();

		// check for basic insert success
		$result = $mapper->insert_data( array( 'term' => 'Term 01', 'taxonomy' => '', 'description' => 'Description 01', 'parent' => '', 'slug' => 'term-01' ) );
		$this->assertFalse( $result );
	}

	/**
	 * @group core
	 * @group mapper
	 */
	public function testInvalidTaxonomyInsert() {

		$mapper = new JC_TaxMapper();

		// check for basic insert success
		$result = $mapper->insert_data( array( 'term' => 'Term 01', 'taxonomy' => 'no-taxonomy', 'description' => 'Description 01', 'parent' => '', 'slug' => 'term-01' ) );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @depends testBasicInsert
	 * @group core
	 * @group mapper
	 */
	public function testNameExists() {
		
		// setup
		$term 		= 'Term 01';
		$taxonomy 	= 'category';
		$mapper 	= new JC_TaxMapper();
		$result 	= $mapper->insert_data( array( 'term' => $term, 'taxonomy' => $taxonomy ) );
		$term_id 	= $result['term_id'];

		//
		$result = $mapper->exist_data( $term, $taxonomy, 'name' );
		$this->assertEquals($term_id, $result);
	}

	/**
	 * @depends testBasicInsert
	 * @group core
	 * @group mapper
	 */
	public function testSlugExists() {
		
		// setup
		$term 		= 'Term 01';
		$taxonomy 	= 'category';
		$slug = 'jc-test-01';
		$mapper 	= new JC_TaxMapper();
		$result 	= $mapper->insert_data( array( 'term' => $term, 'taxonomy' => $taxonomy, 'slug' => $slug ) );
		$term_id 	= $result['term_id'];

		//
		$result = $mapper->exist_data( $slug, $taxonomy, 'slug' );
		$this->assertEquals($term_id, $result);
	}

	/**
	 * @depends testBasicInsert
	 * @group core
	 * @group mapper
	 */
	public function testIdExists() {
		
		// setup
		$term 		= 'Term 01';
		$taxonomy 	= 'category';
		$slug = 'jc-test-01';
		$mapper 	= new JC_TaxMapper();
		$result 	= $mapper->insert_data( array( 'term' => $term, 'taxonomy' => $taxonomy, 'slug' => $slug ) );
		$term_id 	= $result['term_id'];

		//
		$result = $mapper->exist_data( $term_id, $taxonomy, 'term_id' );
		$this->assertEquals($term_id, $result);
	}

	/**
	 * @depends testIdExists
	 * @group core
	 * @group mapper
	 */
	public function testUpdate() {

		$term 		= 'Term 01';
		$taxonomy 	= 'category';
		$slug = 'jc-test-01';
		$mapper 	= new JC_TaxMapper();
		$result 	= $mapper->insert_data( array( 'term' => $term, 'taxonomy' => $taxonomy, 'slug' => $slug ) );
		$term_id 	= $result['term_id'];

		//
		$a = $mapper->update_data( $term_id, $taxonomy, array('name' => 'UPDATED'));

		//
		$result = get_term_by( 'term_id', $term_id, $taxonomy);
		$this->assertEquals( 'UPDATED', $result->name );
	}
}