<?php

/**
 * XML Parser Unit Tests
 */
class CSVParserTest extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->importer = $GLOBALS['jcimporter'];
	}

	/**
	 * Test CSV field parser
	 *
	 * @group core
	 * @group parser
	 */
	public function testParseCSVField() {

		$row    = array( 'Ted', 'Steve', 'Jeff' );
		$parser = new JCI_CSV_ParseField( $row );

		$this->assertEquals( 'Ted', $parser->parse_field( '{0}' ) );
		$this->assertEquals( 'Jeff', $parser->parse_field( '{2}' ) );
		$this->assertEquals( 'TedJeff', $parser->parse_field( '{0}{2}' ) );
		$this->assertEquals( '{3}', $parser->parse_field( '{3}' ) );
		$this->assertEquals( '{3}{4}', $parser->parse_field( '{3}{4}' ) );
		$this->assertEquals( '{3}-Ted', $parser->parse_field( '{3}-{0}' ) );
		$this->assertEquals( 'ted', $parser->parse_field( '[jci::strtolower({0})]' ) );
		$this->assertEquals( 'Ted steve', $parser->parse_field( '{0} [jci::strtolower({1})]' ) );
		$this->assertEquals( '[Ted]steve', $parser->parse_field( '[{0}][jci::strtolower({1})]' ) );
		$this->assertEquals( 'steve[Ted]steve', $parser->parse_field( '[jci::strtolower({1})][{0}][jci::strtolower({1})]' ) );
		$this->assertEquals( 'STEVE', $parser->parse_field( '[jci::strtoupper({1})]' ) );
	}

	public function testRandomCSVField(){

		$row    = array( '0', '1', '2' );
		$parser = new JCI_CSV_ParseField( $row );

		$this->assertEquals( '0', $parser->parse_field( '{0}' ) );
		$this->assertEquals( '1', $parser->parse_field( '{1}' ) );
		$this->assertEquals( '2', $parser->parse_field( '{2}' ) );
	}
}

?>