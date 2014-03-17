<?php
class MapperTest extends WP_UnitTestCase{

	var $importer;

	public function setUp(){
		parent::setUp();        
        $this->importer = $GLOBALS['jcimporter'];
	}

    /**
     * @group core
     * @group mapper
     */
    public function test_parse_keys(){

        $mapper = new TEST_BaseMapper();
        $mapper->set_var('_key', array('group1' => array(), 'group2' => array()));

        $data = array(
            'key' => array('KEY1', 'KEY2'),
            'group' => 'group1'
        );
        $mapper->parseKeys($data);

        $data = array(
            'key' => array('KEY3', 'KEY4'),
            'group' => 'group2'
        );
        $mapper->parseKeys($data);

        $result = $mapper->get_var('_key');
        $this->assertEquals(array(
            'group1' => array('KEY1', 'KEY2'),
            'group2' => array('KEY3','KEY4')
        ), $result);
    }

    /**
     * @group core
     * @group mapper
     */
    public function test_parse_relationship(){
        
        $mapper = new TEST_BaseMapper();
        $mapper->parseRelationship(array(
            'group' => 'group1',
            'relationship' => array( 'post_id' => '{group2.ID}')
        ));

        $result = $mapper->get_var('_relationship');
        $this->assertEquals(array(
            'group1' => array('post_id' => '{group2.ID}')
        ), $result);
    }

    /**
     * @group core
     * @group mapper
     */
    public function test_parse_field_type(){
        
        $mapper = new TEST_BaseMapper();
        $mapper->parseFieldType(array(
            'group' => 'group1',
            'field_type' => 'single' 
        ));

        $result = $mapper->get_var('_field_types');
        $this->assertEquals(array(
            'group1' => 'single'
        ), $result);
    }

    /**
     * @group core
     * @group mapper
     */
    public function test_parse_unique_field(){
        
        $mapper = new TEST_BaseMapper();
        $mapper->parseUniqueField(array(
            'group' => 'group1',
            'unique' => array('ID')
        ));

        $result = $mapper->get_var('_unique');
        $this->assertEquals(array(
            'group1' => array('ID')
        ), $result);
    }

    /**
     * @group core
     * @group mapper
     */
    public function test_process_field(){

        $mapper = new TEST_BaseMapper();

        $mapper->set_var('_current_group', array('ID' => 1, 'post_name' => 'the-slug', 'post_title' => 'The Slug'));
        $mapper->set_var('_insert', array(
            0 => array(
                'group1' => array('ID' => 1, 'post_name' => 'the-slug', 'post_title' => 'The Slug')
            ),
            1 => array(
                'group1' => array('ID' => 2, 'post_name' => 'the-slug-two', 'post_title' => 'The Slug Two')
            )
        ));
        $mapper->set_var('_current_row', 1);

        $this->assertEquals('', $mapper->processField(''));
        $this->assertEquals('', $mapper->processField('{}'));
        $this->assertEquals(1, $mapper->processField('{this.ID}'));
        $this->assertEquals('the-slug-1', $mapper->processField('{this.post_name}-{this.ID}'));
        $this->assertEquals(2, $mapper->processField('{group1.ID}'));
        $this->assertEquals('the-slug-two-2', $mapper->processField('{group1.post_name}-{group1.ID}'));

    }
}

/**
 * Class to access variables
 */
class TEST_BaseMapper extends JC_BaseMapper{

    /**
     * Testing Parse Keys
     */
    public function set_var($var, $val){
        $this->$var = $val;
    }
    public function get_var($var){
        return $this->$var;
    }
}