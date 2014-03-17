<?php 
/**
 * XML Parser Unit Tests
 */
class XMLParserTest extends WP_UnitTestCase{

	public function setUp(){
        parent::setUp();
        $this->importer = $GLOBALS['jcimporter'];
    }

    /**
     * Test XML field parser
     * 
     * @group core
     * @group parser
     */
    public function testParseXMLField(){

        $xml = simplexml_load_string('<xml><cows><cow id="123">Ted</cow><cow id="456">Steve</cow><cow id="789">Jeff</cow></cows></xml>');
        $parser = new JC_XML_ParseField($xml);
        
        $this->assertEquals('Ted', $parser->parse_field('{/cow[1]}', '/xml/cows[1]'));
        $this->assertEquals('Ted', $parser->parse_field('{/cow[1]}', '/xml[1]/cows[1]'));
        $this->assertEquals('123', $parser->parse_field('{/cow[1]/@id}', '/xml/cows'));
        $this->assertEquals('Jeff', $parser->parse_field('{//cow[3]}', ''));
        $this->assertEquals('', $parser->parse_field('', '/'));
        $this->assertEquals('', $parser->parse_field('{}', '/'));
    }

    /**
     * Test parse single xml group
     * 
     * @group core
     * @group parser
     */
    public function test_parse_single_xml(){
        $xml = simplexml_load_string('<xml><cows><cow id="1">Ted1</cow><cow id="2">Steve1</cow><cow id="3">Jeff1</cow></cows><cows><cow id="4">Ted2</cow><cow id="5">Steve2</cow><cow id="6">Jeff2</cow></cows></xml>');
        $parser = new JC_XML_Parser();

        $result = $parser->parse_xml($xml, 0, '/xml/cows', array(
            'cows' => array(
                'type' => 'single',
                'base_node' => '',
                'fields' => array(
                    '{/cow[1]/@id}',
                    '{/cow[1]}',
                )
            )
        ));

        $this->assertEquals(1, $result[0]['cows'][0]);
        $this->assertEquals("Ted1", $result[0]['cows'][1]);
        $this->assertEquals(4, $result[1]['cows'][0]);
        $this->assertEquals("Ted2", $result[1]['cows'][1]);

    }

    /**
     * Test parse repeater xml group
     * 
     * @group core
     * @group parser
     */
    public function test_parse_repeater_xml(){        
        $xml = simplexml_load_file($this->importer->plugin_dir . '/tests/data/data-order.xml');
        $parser = new JC_XML_Parser();
        
        $result = $parser->parse_xml($xml, 0, '/orders/order', array(
            'order' => array(
                'type' => 'single',
                'base_node' => '',
                'fields' => array(
                    '{/orderId}',
                    '{/orderDate}',
                    '{/orderTime}',
                    '{/orderTotal}',
                    '{/customer/title}',
                    '{/customer/forename}',
                    '{/customer/surname}',
                    '{/customer/phone}',
                    '{/customer/email}',
                )
            ),
            'order_items' => array(
                'type' => 'repeater',
                'base_node' => '/items/item',
                'fields' => array(
                    '{/isbn}',
                    '{/qty}',
                    '{/vat}',
                    '{/total}'
                )
            )
        ));

        $this->assertEquals(12345678, $result[0]['order'][0]);
        $this->assertEquals(10, $result[0]['order_items'][0][3]);
        $this->assertEquals(18, $result[0]['order_items'][1][3]);

        $this->assertEquals(12345679, $result[1]['order'][0]);
        $this->assertEquals(12, $result[1]['order_items'][0][3]);
        $this->assertEquals(15, $result[1]['order_items'][1][3]);
    }
}
?>