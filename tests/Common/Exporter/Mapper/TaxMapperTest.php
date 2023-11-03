<?php

namespace ImportWPTests\Common\Exporter\Mapper;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Exporter\Mapper\TaxMapper;

class TaxMapperTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;

    public function get_fields()
    {
        return [
            'term_id',
            'name',
            'slug',
            'description',
        ];
    }

    /**
     * 
     * @return \PHPUnit\Framework\MockObject\MockObject | TaxMapper
     */
    public function create_mock_mapper($constructor_args = 'category', $methods = [])
    {
        return $this->getMockBuilder(TaxMapper::class)
            ->setConstructorArgs([$constructor_args])
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMethods(empty($methods) ? null : $methods)
            ->getMock();
    }

    public function test_get_core_fields()
    {
        $this->assertEquals($this->get_fields(), $this->create_mock_mapper()->get_core_fields());
    }

    public function test_get_fields()
    {
        $fields = $this->create_mock_mapper()->get_fields();

        $this->assertArraySubset([
            'key' => 'main',
            'label' => 'category',
            'loop' => true,
            'fields' => $this->get_fields(),
            'children' => [
                'custom_fields' => [
                    'key' => 'custom_fields',
                    'label' => 'Custom Fields',
                    'loop_fields' => ['meta_key', 'meta_value']
                ]
            ]
        ], $fields);
    }

    public function test_have_records()
    {
        $mock_tax_mapper = $this->create_mock_mapper('post_tag');

        $this->assertFalse($mock_tax_mapper->have_records(1));

        $this->factory()->category->create_many(5);
        $this->assertFalse($mock_tax_mapper->have_records(1));

        $this->factory()->tag->create_many(5);
        $this->assertTrue($mock_tax_mapper->have_records(1));
    }

    public function test_found_records()
    {
        $mock_tax_mapper = $this->create_mock_mapper();

        $this->assertEquals(0, $mock_tax_mapper->found_records());

        $mock_tax_mapper->set_records([1, 2, 3, 4, 5]);

        $this->assertEquals(5, $mock_tax_mapper->found_records());
    }

    public function test_get_records()
    {
        $mock_tax_mapper = $this->create_mock_mapper();

        $this->assertEmpty($mock_tax_mapper->get_records());

        $records = [1, 2, 3, 4, 5];
        $mock_tax_mapper->set_records($records);

        $this->assertEquals($records, $mock_tax_mapper->get_records());
    }

    public function test_setup()
    {
        $mock_tax_mapper = $this->create_mock_mapper();

        $category_ids = $this->factory()->category->create_many(5);

        $mock_tax_mapper->set_records($category_ids);

        $mock_tax_mapper->setup(0);
        $record = $mock_tax_mapper->record();
        $expected = get_term($category_ids[0], '', ARRAY_A);
        unset($expected['parent']);
        $this->assertArraySubset($expected, $record);

        add_term_meta($category_ids[1], 'test_key_one', 'test_value_one');
        add_term_meta($category_ids[1], 'test_key_one', 'test_value_two');

        $mock_tax_mapper->setup(1);
        $expected = array_merge(get_term($category_ids[1], '', ARRAY_A), [
            'custom_fields' => ['test_key_one' => ['test_value_one', 'test_value_two']]
        ]);

        $actual = $mock_tax_mapper->record();
        foreach ($expected as $k => $v) {
            $this->assertEquals($v, $actual[$k]);
        }
    }
}
