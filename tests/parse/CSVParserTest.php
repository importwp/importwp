<?php 
/**
 * XML Parser Unit Tests
 */
class CSVParserTest extends WP_UnitTestCase{

	public function setUp(){
        parent::setUp();
        $this->importer = $GLOBALS['jcimporter'];
    }

    /**
     * Test CSV field parser
     * 
     * @group core
     * @group parser
     */
    public function testParseCSVField(){

        $row = array('Ted', 'Steve','Jeff');
        $parser = new JC_CSV_ParseField($row);

        $this->assertEquals('Ted', $parser->parse_field('{0}'));
        $this->assertEquals('Jeff', $parser->parse_field('{2}'));
        $this->assertEquals('TedJeff', $parser->parse_field('{0}{2}'));
        $this->assertEquals('{3}', $parser->parse_field('{3}'));
        $this->assertEquals('{3}{4}', $parser->parse_field('{3}{4}'));
        $this->assertEquals('{3}-Ted', $parser->parse_field('{3}-{0}'));
    }
}
?>