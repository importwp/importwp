<?php
class UserImporterTest extends WP_UnitTestCase{

	var $importer;

	public function setUp(){
        parent::setUp();
        $this->importer = $GLOBALS['jcimporter'];
    }

     /**
     * Test CSV User Importer
     * 
     * @group core
     * @group template
     */
    public function testCSVUserImporter(){

        $post_id = create_csv_importer(null, 'user', $this->importer->plugin_dir . '/tests/data/data-users.csv', array(
            'user' => array(
                'first_name' => '{0}',
                'last_name' => '{1}',
                'user_email' => '{2}',
                'user_login' => '{0}{1}'
            )
        ));

        ImporterModel::setImporterMeta($post_id, array('_template_settings','generate_pass'), 1);

    	/**
    	 * Test: Check if one record is returned
    	 */
    	ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $import_data = $this->importer->importer->run_import(1);
    	$this->assertEquals(1, count($import_data));

        $import_data = array_shift($import_data);
        $this->assertEquals('First1', $import_data['user']['first_name']);
        $this->assertEquals('Last1', $import_data['user']['last_name']);
        $this->assertEquals('email1@test.com', $import_data['user']['user_email']);
        $this->assertEquals('First1Last1', $import_data['user']['user_login']);
        $this->assertEquals('I', $import_data['user']['_jci_type']);
        $this->assertEquals('S', $import_data['user']['_jci_status']);

    	/**
    	 * Test: Check if one record is returned
    	 */
    	ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
    	$import_data = $this->importer->importer->run_import(7);
        $this->assertEquals(1, count($import_data));
        $import_data = array_shift($import_data);
        $this->assertEquals('First7', $import_data['user']['first_name']);
        $this->assertEquals('Last7', $import_data['user']['last_name']);
        $this->assertEquals('email7@test.com', $import_data['user']['user_email']);
        $this->assertEquals('First7Last7', $import_data['user']['user_login']);
        $this->assertEquals('I', $import_data['user']['_jci_type']);
        $this->assertEquals('S', $import_data['user']['_jci_status']);

    	/**
    	 * Test: Check if row doesn't exist false is returned
    	 */
    	ImporterModel::clearImportSettings();
    	$this->assertEquals(false, $this->importer->importer->run_import(8));

    	/**
    	 * Test: Check if import was successful, with correct response
    	 */
    	ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
    	$test = $this->importer->importer->run_import();
        $this->assertEquals(7, count($test));
        foreach($test as $response){
        	$this->assertArrayHasKey('ID', $response['user']);
        	$this->assertArrayHasKey('_jci_status', $response);
        	$this->assertEquals('S', $response['_jci_status']);
        }

        $post_id = create_csv_importer($post_id, 'user', $this->importer->plugin_dir . '/tests/data/data-users.csv', array(
            'user' => array(
                'first_name' => '{0}',
                'last_name' => '{1}',
                'user_email' => '',
                'user_login' => ''
            )
        ));

        /**
    	 * Test: Check to see if row return error status if no user_login is present
    	 */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $test = $this->importer->importer->run_import(1);
        $test = array_shift($test);
        $this->assertArrayHasKey('_jci_status', $test);
        $this->assertEquals('E', $test['_jci_status']);

        /**
    	 * Test: Check to see if all rows return error status if no user_login is present
    	 */
        $test = $this->importer->importer->run_import();
        foreach($test as $response){

        	$this->assertArrayHasKey('_jci_status', $response);
	        $this->assertEquals('E', $response['_jci_status']);
        }

        $post_id = create_csv_importer($post_id, 'user', $this->importer->plugin_dir . '/tests/data/data-users.csv', array(
            'user' => array(
                'first_name' => '{0}',
                'last_name' => '{1}',
                'user_email' => '',
                'user_login' => '{2}'
            )
        ));

        /**
    	 * Test: Check to see if row return error status if no user_email is present
    	 */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $test = $this->importer->importer->run_import(1);
        $test = array_shift($test);
        $this->assertArrayHasKey('_jci_status', $test);
        $this->assertEquals('E', $test['_jci_status']);

        /**
    	 * Test: Check to see if all rows return error status if no user_email is present
    	 */
        $test = $this->importer->importer->run_import();
        foreach($test as $response){

        	$this->assertArrayHasKey('_jci_status', $response);
	        $this->assertEquals('E', $response['_jci_status']);
        }

    }

    /**
     * @group core
     * @group template
     */
    public function testXMLUserImporter(){

        $post_id = create_xml_importer(null, 'user', $this->importer->plugin_dir . '/tests/data/data-users.xml', array(
            'user' => array(
                'first_name' => '{/first_name}',
                'last_name' => '{/last_name}',
                'user_email' => '{/email}',
                'user_login' => '{/first_name}{/last_name}'
            )
        ), array(
            'import_base' => '/users/user',
            'group_base' => array(
                'user' => ''
            )
        ));   

        ImporterModel::setImporterMeta($post_id, array('_template_settings','generate_pass'), 1);

        /**
         * Test: Check if one record is returned
         */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $import_data = $this->importer->importer->run_import(1);
        $this->assertEquals(1, count($import_data));

        $import_data = array_shift($import_data);
        $this->assertEquals('First1', $import_data['user']['first_name']);
        $this->assertEquals('Last1', $import_data['user']['last_name']);
        $this->assertEquals('email1@test.com', $import_data['user']['user_email']);
        $this->assertEquals('First1Last1', $import_data['user']['user_login']);
        $this->assertEquals('I', $import_data['user']['_jci_type']);
        $this->assertEquals('S', $import_data['user']['_jci_status']);

        /**
         * Test: Check if one record is returned
         */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $import_data = $this->importer->importer->run_import(7);
        $this->assertEquals(1, count($import_data));
        $import_data = array_shift($import_data);
        $this->assertEquals('First7', $import_data['user']['first_name']);
        $this->assertEquals('Last7', $import_data['user']['last_name']);
        $this->assertEquals('email7@test.com', $import_data['user']['user_email']);
        $this->assertEquals('First7Last7', $import_data['user']['user_login']);
        $this->assertEquals('I', $import_data['user']['_jci_type']);
        $this->assertEquals('S', $import_data['user']['_jci_status']);

        /**
         * Test: Check if row doesn't exist false is returned
         */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $this->assertEquals(false, $this->importer->importer->run_import(8));

        /**
         * Test: Check if import was successful, with correct response
         */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $test = $this->importer->importer->run_import();
        $this->assertEquals(7, count($test));
        foreach($test as $response){
            $this->assertArrayHasKey('ID', $response['user']);
            $this->assertArrayHasKey('_jci_status', $response);
            $this->assertEquals('S', $response['_jci_status']);
        }

        $post_id = create_xml_importer( $post_id, 'user', $this->importer->plugin_dir . '/tests/data/data-users.xml', array(
            'user' => array(
                'first_name' => '{/first_name}',
                'last_name' => '{/last_name}',
                'user_email' => '',
                'user_login' => ''
            )
        ), array(
            'import_base' => '/users/user',
            'group_base' => array(
                'user' => ''
            )
        )); 

        /**
         * Test: Check to see if row return error status if no user_login is present
         */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $test = $this->importer->importer->run_import(1);
        $test = array_shift($test);
        $this->assertArrayHasKey('_jci_status', $test);
        $this->assertEquals('E', $test['_jci_status']);

        /**
         * Test: Check to see if all rows return error status if no user_login is present
         */
        $test = $this->importer->importer->run_import();
        foreach($test as $response){

            $this->assertArrayHasKey('_jci_status', $response);
            $this->assertEquals('E', $response['_jci_status']);
        }

        $post_id = create_xml_importer( $post_id, 'user', $this->importer->plugin_dir . '/tests/data/data-users.xml', array(
            'user' => array(
                'first_name' => '{/first_name}',
                'last_name' => '{/last_name}',
                'user_email' => '',
                'user_login' => '{/first_name}{/last_name}1'
            )
        ), array(
            'import_base' => '/users/user',
            'group_base' => array(
                'user' => ''
            )
        )); 

        /**
         * Test: Check to see if row return error status if no user_email is present
         */
        ImporterModel::clearImportSettings();
        $this->importer->importer = new JC_Importer_Core($post_id);
        $test = $this->importer->importer->run_import(1);
        $test = array_shift($test);
        $this->assertArrayHasKey('_jci_status', $test);
        $this->assertEquals('E', $test['_jci_status']);

        /**
         * Test: Check to see if all rows return error status if no user_email is present
         */
        $test = $this->importer->importer->run_import();
        foreach($test as $response){

            $this->assertArrayHasKey('_jci_status', $response);
            $this->assertEquals('E', $response['_jci_status']);
        }

    }
    
}
?>