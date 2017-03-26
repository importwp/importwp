<?php

class PostImporterTest extends WP_UnitTestCase {

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
	public function testCSVPostImporter() {

		$post_id = create_csv_importer( null, 'post', $this->importer->get_plugin_dir() . '/tests/data/data-posts.csv', array(
			'post' => array(
				'post_title'   => '{0}',
				'post_name'    => '{1}',
				'post_excerpt' => '{3}',
				'post_content' => '{2}',
				// 'post_author' => '',
				'post_status'  => '{4}',
				// 'post_date' => '',
			)
		) );

		// setup taxonomies
		ImporterModel::setImporterMeta( $post_id, array( '_taxonomies', 'post' ), array(
			'tax'         => array( 'post_tag' ),
			'term'        => array( '{6}' ),
			'permissions' => array( 'create' )
		) );

		ImporterModel::setImporterMeta( $post_id, array( '_template_settings', 'enable_post_status' ), 1 );

		/**
		 * Test: Check if one record is returned
		 */
		ImporterModel::clearImportSettings();
		$this->importer->importer = new JC_Importer_Core( $post_id );
		$import_data              = $this->importer->importer->run_import( 1 );
		$this->assertEquals( 1, count( $import_data ) );

		$import_data = array_shift( $import_data );
		// $this->assertEquals('title', $import_data['post']['post_title']);
		$this->assertEquals( 'slug', $import_data['post']['post_name'] );
		$this->assertEquals( 'excerpt', $import_data['post']['post_excerpt'] );
		$this->assertEquals( 'content', $import_data['post']['post_content'] );
		$this->assertEquals( 'status', $import_data['post']['post_status'] );
		$this->assertEquals( 'I', $import_data['post']['_jci_type'] );
		$this->assertEquals( 'S', $import_data['post']['_jci_status'] );
		$this->assertEquals( 'S', $import_data['_jci_status'] );

		//  Test Taxonomies
		$this->assertEquals( 'tags', $import_data['taxonomies']['post_tag'][0] );
	}

	/**
	 * @group core
	 * @group template
	 */
	public function testXMLPostImporter() {

		$post_id = create_xml_importer( null, 'post', $this->importer->get_plugin_dir() . '/tests/data/data-posts.xml', array(
			'post' => array(
				'post_title'   => '{/title}',
				'post_name'    => '{/slug}',
				'post_excerpt' => '{/excerpt}',
				'post_content' => '{/content}',
				// 'post_author' => '',
				'post_status'  => '{/status}',
				// 'post_date' => '',
			)
		), array(
			'import_base' => '/posts/post',
			'group_base'  => array(
				'post' => ''
			)
		) );

		// setup taxonomies
		ImporterModel::setImporterMeta( $post_id, array( '_taxonomies', 'post' ), array(
			'tax'         => array( 'category', 'post_tag' ),
			'term'        => array( '{/categories[1]/category}', '{/tags[1]}' ),
			'permissions' => array( 'overwrite', 'overwrite' )
		) );

		ImporterModel::setImporterMeta( $post_id, array( '_template_settings', 'enable_post_status' ), 1 );

		/**
		 * Test: Check if one record is returned
		 */
		ImporterModel::clearImportSettings();
		$this->importer->importer = new JC_Importer_Core( $post_id );
		$import_data              = $this->importer->importer->run_import( 1 );
		$this->assertEquals( 1, count( $import_data ) );
		$import_data = array_shift( $import_data );

		// assetions
		$this->assertEquals( 'Post One', $import_data['post']['post_title'] );
		$this->assertEquals( 'post-one-123', $import_data['post']['post_name'] );
		$this->assertEquals( 'This is the post one\'s excerpt', $import_data['post']['post_excerpt'] );
		$this->assertEquals( 'This is the post one\'s content', $import_data['post']['post_content'] );
		$this->assertEquals( 'publish', $import_data['post']['post_status'] );
		$this->assertEquals( 'I', $import_data['post']['_jci_type'] );
		$this->assertEquals( 'S', $import_data['post']['_jci_status'] );

		// need to fix failing test, but not failing on real import
		$this->assertEquals( 'S', $import_data['_jci_status'] );

	}

	/**
	 * Test unenabled Optional Fields
	 */
	public function testUnEnableOptionalFields(){

		$ID 			= 'ID';
		$post_title 	= 'post_title';
		$post_content 	= 'post_content';
		$post_excerpt 	= 'post_excerpt';
		$post_name 		= 'post_name';
		$post_status 	= 'post_status';
		$post_author 	= 'post_author';
		$post_parent 	= 'post_parent';
		$menu_order 	= 'menu_order';
		$post_password 	= 'post_password';
		$post_date 		= date('Y-m-d H:i:s');
		$comment_status = 'comment_status';
		$ping_status 	= 'ping_status';

		$post_id = create_csv_importer( null, 'post', $this->importer->get_plugin_dir() . '/tests/data/data-posts.csv', array(
			'post' => array(
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
			)
		) );

		ImporterModel::clearImportSettings();

		$this->importer->importer 	= new JC_Importer_Core( $post_id );
		$test                     	= $this->importer->importer->run_import( 1 );
		$test 						= array_shift($test);

		$this->assertTrue(array_key_exists('post_status', $test['post']));
		$this->assertTrue(array_key_exists('post_author', $test['post']));
		$this->assertTrue(array_key_exists('post_parent', $test['post']));
		$this->assertTrue(array_key_exists('comment_status', $test['post']));
		$this->assertTrue(array_key_exists('ping_status', $test['post']));
		$this->assertFalse(array_key_exists('menu_order', $test['post']));
		$this->assertFalse(array_key_exists('post_password', $test['post']));
		$this->assertFalse(array_key_exists('post_date', $test['post']));

		$this->assertEquals('S', $test['_jci_status']);
	}

	/**
	 * Test enabled Optional Fields
	 */
	public function testEnableOptionalFields(){

		$post_title 	= 'post_title';
		$post_content 	= 'post_content';
		$post_excerpt 	= 'post_excerpt';
		$post_name 		= 'post_name';
		$post_status 	= 'post_status';
		$post_author 	= 'post_author';
		$post_parent 	= 'post_parent';
		$menu_order 	= 'menu_order';
		$post_password 	= 'post_password';
		$post_date 		= date('Y-m-d H:i:s');
		$comment_status = 'comment_status';
		$ping_status 	= 'ping_status';

		$post_id = create_csv_importer( null, 'post', $this->importer->get_plugin_dir() . '/tests/data/data-posts.csv', array(
			'post' => array(
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
			)
		) );

		ImporterModel::setImporterMeta( $post_id, array( '_template_settings', 'enable_menu_order' ), 1 );
		ImporterModel::setImporterMeta( $post_id, array( '_template_settings', 'enable_post_password' ), 1 );
		ImporterModel::setImporterMeta( $post_id, array( '_template_settings', 'enable_post_date' ), 1 );

		ImporterModel::clearImportSettings();

		$this->importer->importer 	= new JC_Importer_Core( $post_id );
		$test                     	= $this->importer->importer->run_import( 1 );
		$test 						= array_shift($test);

		$this->assertEquals($post_title, $test['post']['post_title']);
		$this->assertEquals($post_content, $test['post']['post_content']);
		$this->assertEquals($post_excerpt, $test['post']['post_excerpt']);
		$this->assertEquals($post_name, $test['post']['post_name']);
		$this->assertEquals($post_status, $test['post']['post_status']);
		$this->assertEquals($post_author, $test['post']['post_author']);
		$this->assertEquals($post_parent, $test['post']['post_parent']);
		$this->assertEquals($menu_order, $test['post']['menu_order']);
		$this->assertEquals($post_password, $test['post']['post_password']);
		$this->assertEquals($post_date, $test['post']['post_date']);
		$this->assertEquals($comment_status, $test['post']['comment_status']);
		$this->assertEquals($ping_status, $test['post']['ping_status']);

		$this->assertEquals('S', $test['_jci_status']);
	}

	/**
	 * Test to see if page id passed to post parent returns page_id
	 */
	public function testPostParentFieldId(){

		$title = 'Post Title 01';
		$slug = 'post-title-01';
		$parent_id = wp_insert_post( array( 
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_type'   => 'post',
			'post_status' => 'publish'
		));

		$post_id = create_csv_importer( null, 'post', $this->importer->get_plugin_dir() . '/tests/data/data-posts.csv', array(
			'post' => array(
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

		ImporterModel::clearImportSettings();
		$this->importer->importer 	= new JC_Importer_Core( $post_id );
		$import_data              	= $this->importer->importer->run_import( 1 );
		$import_data 				= array_shift($import_data);

		$this->assertEquals($import_data['post']['post_parent'], $parent_id);
	}

	/**
	 * Test to see if post title passed to post parent returns post_id
	 */
	public function testPostParentFieldTitle(){

		$title = 'Post Title 01';
		$slug = 'post-title-01';
		$parent_id = wp_insert_post( array( 
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_type'   => 'post',
			'post_status' => 'publish'
		));

		$post_id = create_csv_importer( null, 'post', $this->importer->get_plugin_dir() . '/tests/data/data-posts.csv', array(
			'post' => array(
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

		ImporterModel::setImporterMeta( $post_id, array(
					'_template_settings',
					'enable_post_parent'
				), 1 );

		ImporterModel::setImporterMeta( $post_id, array(
				'_template_settings',
				'_field_type',
				'post_parent'
			), 'name' );

		ImporterModel::clearImportSettings();
		$this->importer->importer 	= new JC_Importer_Core( $post_id );
		$import_data              	= $this->importer->importer->run_import( 1 );
		$import_data 				= array_shift($import_data);

		$this->assertEquals($import_data['post']['post_parent'], $parent_id);
	}

	/**
	 * Test to see if post slug passed to post parent returns post_id
	 */
	public function testPostParentFieldSlug(){

		$title = 'Post Title 01';
		$slug = 'post-title-01';
		$parent_id = wp_insert_post( array( 
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_type'   => 'post',
			'post_status' => 'publish'
		));

		$post_id = create_csv_importer( null, 'post', $this->importer->get_plugin_dir() . '/tests/data/data-posts.csv', array(
			'post' => array(
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

		ImporterModel::setImporterMeta( $post_id, array(
					'_template_settings',
					'enable_post_parent'
				), 1 );

		ImporterModel::setImporterMeta( $post_id, array(
				'_template_settings',
				'_field_type',
				'post_parent'
			), 'slug' );

		ImporterModel::clearImportSettings();
		$this->importer->importer 	= new JC_Importer_Core( $post_id );
		$import_data              	= $this->importer->importer->run_import( 1 );
		$import_data 				= array_shift($import_data);

		$this->assertEquals($import_data['post']['post_parent'], $parent_id);
	}
}

?>