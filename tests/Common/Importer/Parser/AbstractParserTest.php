<?php

namespace ImportWPTests\Common\Importer\Parser;

use ImportWP\Common\Importer\FileInterface;
use ImportWP\Common\Importer\Parser\AbstractParser;

global $iwptm_callback_data;
$iwptm_callback_data = [];

class AbstractParserTest extends \WP_UnitTestCase
{

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|AbstractParser $abstract_parser
     */
    protected $abstract_parser;

    public function set_up()
    {
        parent::set_up();
        $this->mock_abstract_parser();

        global $iwptm_callback_data;
        $iwptm_callback_data = [];
    }

    public function tear_down()
    {
        parent::tear_down();
        $this->abstract_parser = null;

        global $iwptm_callback_data;
        $iwptm_callback_data = null;
    }

    private function mock_abstract_parser($methods = [])
    {
        $file_interface = $this->createMock(FileInterface::class);
        $this->abstract_parser = $this->getMockForAbstractClass(AbstractParser::class, [$file_interface], '', true, true, true, $methods);
    }

    /**
     * @dataProvider provide_handle_custom_methods
     */
    public function test_handle_custom_methods($expected, $map)
    {
        $result = $this->abstract_parser->handle_custom_methods($map);
        $this->assertEquals($expected, $result);
    }

    public function provide_handle_custom_methods()
    {
        return [
            'Basic custom method' => ['HELLO', '[strtoupper("hello")]'],
            'Multiple custom methods' => ['HELLO WORLD', '[strtoupper("hello")] [strtoupper("world")]'],
            'Multi line argument' => ["HE\nLLO", "[strtoupper(\"he\nllo\")]"],
            'Multi line argument with ()' => ["(HE\nLLO)", "[strtoupper(\"(he\nllo)\")]"],
            'Multiple custom methods with multiple arguments' => [str_pad("he\nllo", 12, "0") . ' WORLD', "[str_pad(\"he\nllo\", \"12\",\"0\")] [strtoupper(\"world\")]"]
        ];
    }

    /**
     * @dataProvider provide_map_field_data
     */
    public function test_map_field_data($expected, $input, $map)
    {
        $result = $this->abstract_parser->map_field_data($input, $map);
        $this->assertEquals($expected, $result);
    }

    public function provide_map_field_data()
    {
        $if_else = [
            ['_condition' => 'equal', 'key' => 'yes', 'value' => '1'],
            ['_condition' => 'not-equal', 'key' => 'yes', 'value' => '0'],
        ];

        $gt_lt = [
            ['_condition' => 'gte', 'key' => '10', 'value' => 'instock'],
            ['_condition' => 'lt', 'key' => '10', 'value' => 'outofstock'],
        ];

        return [
            'Empty map' => ['yes', 'yes', []],
            // Equal | Not Equal
            'Equal Match' => ['1', 'yes', [['_condition' => 'equal', 'key' => 'yes', 'value' => '1']]],
            'Equal Not Matching' => ['no', 'no', [['_condition' => 'equal', 'key' => 'yes', 'value' => '1']]],
            'Not Equal Match' => ['1', 'yes', [['_condition' => 'not-equal', 'key' => 'no', 'value' => '1']]],
            'Not Equal Not Match' => ['no', 'no', [['_condition' => 'not-equal', 'key' => 'no', 'value' => '1']]],
            'If Else Match' => ['1', 'yes', $if_else],
            'If Else Not Match' => ['0', 'no', $if_else],
            // GT | LT
            'Greater Than' => ['instock', '10', [['_condition' => 'gt', 'key' => '8', 'value' => 'instock']]],
            'Greater Than or Equal' => ['instock', '10', [['_condition' => 'gte', 'key' => '10', 'value' => 'instock']]],
            'Less Than' => ['outofstock', '6', [['_condition' => 'lt', 'key' => '8', 'value' => 'outofstock']]],
            'Less Than or Equal' => ['outofstock', '8', [['_condition' => 'lte', 'key' => '8', 'value' => 'outofstock']]],
            'Greater Than and Equal or Less Than | >' => ['instock', '11', $gt_lt],
            'Greater Than and Equal or Less Than | =' => ['instock', '10', $gt_lt],
            'Greater Than and Equal or Less Than | <' => ['outofstock', '9', $gt_lt],
        ];
    }
}
