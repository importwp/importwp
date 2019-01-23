<?php

class PageImporterTest extends WP_UnitTestCase {

	/**
	 * Plugin Instance
	 *
	 * @var JC_Importer
	 */
	protected $importer;

	public function setUp() {
		parent::setUp();
		$this->importer = $GLOBALS['jcimporter'];
	}

	/**
	 * @group core
	 * @group template
	 */
	public function testXMLPageImporter() {

		$post_id = create_xml_importer( null, 'page', $this->importer->get_plugin_dir() . '/tests/data/data-pages.xml', array(
			'page' => array(
				'post_title'   => '{/title}',
				'post_name'    => '{/slug}',
				'post_excerpt' => '{/excerpt}',
				'post_content' => '{/content}',
				// 'post_author' => '',
				'post_status'  => '{/status}',
				// 'post_date' => '',
			)
		), array(
			'import_base' => '/pages/page',
			'group_base'  => array(
				'page' => ''
			)
		) );

		/**
		 * Test: Check if one record is returned
		 */
		IWP_Importer_Settings::clearImportSettings();
		$this->importer->importer = new IWP_Importer( $post_id );
		$import_data              = $this->importer->importer->run_import( 1 );
		$this->assertEquals( 1, count( $import_data ) );

		$import_data = array_shift( $import_data );
	}

	/**
	 * Test unenabled Optional Fields
	 */
	public function testUnEnableOptionalFields(){

		$ID 			= 'ID';
		$post_title		= 'post_title';
		$post_content 	= 'post_content';
		$post_excerpt 	= 'post_excerpt';
		$post_name 		= 'post_name';
		$post_status 	= 'post_status';
		$post_author 	= 'post_author';
		$post_parent 	= 11;
		$menu_order 	= 'menu_order';
		$post_password 	= 'post_password';
		$post_date 		= date('Y-m-d H:i:s');
		$comment_status = 'comment_status';
		$ping_status 	= 'ping_status';
		$page_template 	= 'page_template';

		$post_id = create_csv_importer( null, 'page', $this->importer->get_plugin_dir() . '/tests/data/data-pages.csv', array(
			'page' => array(
				'ID' 				=> $ID,
				'post_title' 		=> $post_title,
				'post_content' 		=> $post_content,
				'post_excerpt' 		=> $post_excerpt,
				'post_name' 		=> $post_name,
				'post_status' 		=> $post_status,
				'post_author' 		=> $post_author,
				'post_parent' 		=> $post_parent,
				'menu_order' 		=> $menu_order,
				'post_password' 	=> $post_password,
				'post_date' 		=> $post_date,
				'comment_status' 	=> $comment_status,
				'ping_status' 		=> $ping_status,
				'page_template' 	=> $page_template
			)
		) );

		IWP_Importer_Settings::clearImportSettings();

		$this->importer->importer 	= new IWP_Importer( $post_id );
		$test                     	= $this->importer->importer->run_import( 1 );
		$test 						= array_shift($test);

		$this->assertTrue(array_key_exists('post_status', $test['page']));
		$this->assertTrue(array_key_exists('post_author', $test['page']));
		$this->assertTrue(array_key_exists('post_parent', $test['page']));
		$this->assertTrue(array_key_exists('comment_status', $test['page']));
		$this->assertTrue(array_key_exists('ping_status', $test['page']));
		$this->assertFalse(array_key_exists('menu_order', $test['page']));
		$this->assertFalse(array_key_exists('post_password', $test['page']));
		$this->assertFalse(array_key_exists('post_date', $test['page']));
		$this->assertFalse(array_key_exists('page_template', $test['page']));

		$this->assertEquals('S', $test['_jci_status']);
	}

	/**
	 * Test enabled Optional Fields
	 */
	public function testEnableOptionalFields(){

		$post_title		= 'post_title';
		$post_content 	= 'post_content';
		$post_excerpt 	= 'post_excerpt';
		$post_name 		= 'post_name';
		$post_status 	= 'post_status';
		$post_author 	= 'post_author';
		$post_parent 	= 11;
		$menu_order 	= 'menu_order';
		$post_password 	= 'post_password';
		$post_date 		= date('Y-m-d H:i:s');
		$comment_status = 'comment_status';
		$ping_status 	= 'ping_status';
		$page_template 	= 'page_template';

		$post_id = create_csv_importer( null, 'page', $this->importer->get_plugin_dir() . '/tests/data/data-pages.csv', array(
			'page' => array(
				'post_title' 		=> $post_title,
				'post_content' 		=> $post_content,
				'post_excerpt' 		=> $post_excerpt,
				'post_name' 		=> $post_name,
				'post_status' 		=> $post_status,
				'post_author' 		=> $post_author,
				'post_parent' 		=> $post_parent,
				'menu_order' 		=> $menu_order,
				'post_password' 	=> $post_password,
				'post_date' 		=> $post_date,
				'comment_status' 	=> $comment_status,
				'ping_status' 		=> $ping_status,
				'_wp_page_template' 	=> $page_template
			)
		) );

		IWP_Importer_Settings::setImporterMeta( $post_id, array( '_template_settings', 'enable_menu_order' ), 1 );
		IWP_Importer_Settings::setImporterMeta( $post_id, array( '_template_settings', 'enable_post_password' ), 1 );
		IWP_Importer_Settings::setImporterMeta( $post_id, array( '_template_settings', 'enable_post_date' ), 1 );
		IWP_Importer_Settings::setImporterMeta( $post_id, array( '_template_settings', 'enable_page_template' ), 1 );

		IWP_Importer_Settings::clearImportSettings();

		$this->importer->importer 	= new IWP_Importer( $post_id );
		$test                     	= $this->importer->importer->run_import( 1 );
		$test 						= array_shift($test);

		$this->assertEquals($post_title, $test['page']['post_title']);
		$this->assertEquals($post_content, $test['page']['post_content']);
		$this->assertEquals($post_excerpt, $test['page']['post_excerpt']);
		$this->assertEquals($post_name, $test['page']['post_name']);
		$this->assertEquals($post_status, $test['page']['post_status']);
		$this->assertEquals($post_author, $test['page']['post_author']);
		$this->assertEquals($post_parent, $test['page']['post_parent']);
		$this->assertEquals($menu_order, $test['page']['menu_order']);
		$this->assertEquals($post_password, $test['page']['post_password']);
		$this->assertEquals($post_date, $test['page']['post_date']);
		$this->assertEquals($comment_status, $test['page']['comment_status']);
		$this->assertEquals($ping_status, $test['page']['ping_status']);
		$this->assertEquals($page_template, $test['page']['_wp_page_template']);

		$this->assertEquals('S', $test['_jci_status']);
	}

	/**
	 * Test to see if page id passed to post parent returns page_id
	 */
	public function testPageParentFieldId(){

		$title = 'Page Title 01';
		$slug = 'page-title-01';
		$parent_id = wp_insert_post( array( 
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_type'   => 'page',
			'post_status' => 'publish'
		));

		$post_id = create_csv_importer( null, 'page', $this->importer->get_plugin_dir() . '/tests/data/data-pages.csv', array(
			'page' => array(
				'post_title' 		=> 'Child 01',
				'post_content'  	=> 'child 01',
				'post_name' 		=> 'child-01',
				'post_status' 		=> 'publish',
				'post_author' 		=> 'admin',
				'post_parent' 		=> $parent_id,
				'comment_status' 	=> '0',
				'ping_status' 		=> 'closed',
			)
		));

		IWP_Importer_Settings::clearImportSettings();
		$this->importer->importer 	= new IWP_Importer( $post_id );
		$import_data              	= $this->importer->importer->run_import( 1 );
		$import_data 				= array_shift($import_data);

		$this->assertEquals($import_data['page']['post_parent'], $parent_id);
	}

	/**
	 * Test to see if page title passed to post parent returns page_id
	 */
	public function testPageParentFieldTitle(){

		$title = 'Page Title 01';
		$slug = 'page-title-01';
		$parent_id = wp_insert_post( array( 
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_type'   => 'page',
			'post_status' => 'publish'
		));

		$post_id = create_csv_importer( null, 'page', $this->importer->get_plugin_dir() . '/tests/data/data-pages.csv', array(
			'page' => array(
				'post_title' 		=> 'Child 01',
				'post_content'  	=> 'child 01',
				'post_name' 		=> 'child-01',
				'post_status' 		=> 'publish',
				'post_author' 		=> 'admin',
				'post_parent' 		=> $title,
				'comment_status' 	=> '0',
				'ping_status' 		=> 'closed',
			)
		));

		IWP_Importer_Settings::setImporterMeta( $post_id, array(
					'_template_settings',
					'enable_post_parent'
				), 1 );

		IWP_Importer_Settings::setImporterMeta( $post_id, array(
				'_template_settings',
				'_field_type',
				'post_parent'
			), 'name' );

		IWP_Importer_Settings::clearImportSettings();
		$this->importer->importer 	= new IWP_Importer( $post_id );
		$import_data              	= $this->importer->importer->run_import( 1 );
		$import_data 				= array_shift($import_data);

		$this->assertEquals($import_data['page']['post_parent'], $parent_id);
	}

	/**
	 * Test to see if page slug passed to post parent returns page_id
	 */
	public function testPageParentFieldSlug(){

		$title = 'Page Title 01';
		$slug = 'page-title-01';
		$parent_id = wp_insert_post( array( 
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_type'   => 'page',
			'post_status' => 'publish'
		));

		$post_id = create_csv_importer( null, 'page', $this->importer->get_plugin_dir() . '/tests/data/data-pages.csv', array(
			'page' => array(
				'post_title' 		=> 'Child 01',
				'post_content'  	=> 'child 01',
				'post_name' 		=> 'child-01',
				'post_status' 		=> 'publish',
				'post_author' 		=> 'admin',
				'post_parent' 		=> $slug,
				'comment_status' 	=> '0',
				'ping_status' 		=> 'closed',
			)
		));

		IWP_Importer_Settings::setImporterMeta( $post_id, array(
					'_template_settings',
					'enable_post_parent'
				), 1 );

		IWP_Importer_Settings::setImporterMeta( $post_id, array(
				'_template_settings',
				'_field_type',
				'post_parent'
			), 'slug' );

		IWP_Importer_Settings::clearImportSettings();
		$this->importer->importer 	= new IWP_Importer( $post_id );
		$import_data              	= $this->importer->importer->run_import( 1 );
		$import_data 				= array_shift($import_data);

		$this->assertEquals($import_data['page']['post_parent'], $parent_id);
	}
}

?>