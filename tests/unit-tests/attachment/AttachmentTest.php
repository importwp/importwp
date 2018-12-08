<?php

class AttachmentTest extends WP_UnitTestCase {

	var $importer;

	public function setUp() {
		parent::setUp();
		$this->importer = $GLOBALS['jcimporter'];
	}

	public function test_wp_insert_attachment(){

		$parent_id = wp_insert_post( array( 'post_title'  => 'INSERTED',
		                                    'post_type'   => 'post',
		                                    'post_status' => 'publish'
		) );

		$attachment = new IWP_Attachment();
		$post_id = $attachment->wp_insert_attachment($parent_id, $this->importer->get_plugin_dir() . '/tests/data/data-pages.xml');

		$post = get_post($post_id);
		$this->assertEquals(1, preg_match('/\/data-pages\.xml$/', $post->guid));
		$this->assertEquals('attachment', $post->post_type);
		$this->assertEquals($parent_id, $post->post_parent);
		$this->assertEquals('inherit', $post->post_status);
	}

}