<?php
class PageImporterTest extends WP_UnitTestCase{

	var $importer;

	public function setUp(){
        parent::setUp();
        $this->importer = $GLOBALS['jcimporter'];
    }

    /**
     * @group core
     * @group template
     */
    public function testXMLPageImporter(){

        $post_id = create_xml_importer(null, 'page', $this->importer->plugin_dir . '/tests/data/data-pages.xml', array(
            'page' => array(
                'post_title' => '{/title}',
                'post_name' => '{/slug}',
                'post_excerpt' => '{/excerpt}',
                'post_content' => '{/content}',
                // 'post_author' => '',
                'post_status' => '{/status}',
                // 'post_date' => '',
            )
        ), array(
            'import_base' => '/pages/page',
            'group_base' => array(
                'page' => ''
            )
        ));   

        /**
         * Test: Check if one record is returned
         */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $import_data = $this->importer->importer->run_import(1);
        $this->assertEquals(1, count($import_data));

        $import_data = array_shift($import_data);
    }
}
?>