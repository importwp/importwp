<?php

namespace ImportWPTests\Common\Exporter\Mapper;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Exporter\Mapper\UserMapper;

class UserMapperTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;

    public function get_fields()
    {
        return [
            'ID',
            'user_login',
            'user_nicename',
            'user_email',
            'user_url',
            'user_registered',
            'user_status',
            'display_name',
            'first_name',
            'last_name'
        ];
    }

    /**
     * 
     * @return \PHPUnit\Framework\MockObject\MockObject | UserMapper
     */
    public function create_mock_mapper($methods = [])
    {
        return $this->createPartialMock(UserMapper::class, $methods);
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
            'label' => 'User',
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
        $mock_user_mapper = $this->create_mock_mapper();
        $this->assertTrue($mock_user_mapper->have_records(1));
    }

    public function test_found_records()
    {
        $mock_user_mapper = $this->create_mock_mapper();

        $this->assertEquals(0, $mock_user_mapper->found_records());

        $mock_user_mapper->set_records([1, 2, 3, 4, 5]);

        $this->assertEquals(5, $mock_user_mapper->found_records());
    }

    public function test_get_records()
    {
        $mock_user_mapper = $this->create_mock_mapper();

        $this->assertEmpty($mock_user_mapper->get_records());

        $records = [1, 2, 3, 4, 5];
        $mock_user_mapper->set_records($records);

        $this->assertEquals($records, $mock_user_mapper->get_records());
    }

    public function test_setup()
    {
        $mock_user_mapper = $this->create_mock_mapper();

        $user_ids = $this->factory()->user->create_many(5);

        $mock_user_mapper->set_records($user_ids);

        $mock_user_mapper->setup(0);
        $record = $mock_user_mapper->record();
        $expected = (array)get_user_by('id', $user_ids[0])->data;
        $this->assertArraySubset($expected, $record);

        add_user_meta($user_ids[1], 'test_key_one', 'test_value_one');
        add_user_meta($user_ids[1], 'test_key_one', 'test_value_two');

        $mock_user_mapper->setup(1);
        $expected = (array)get_user_by('id', $user_ids[1])->data;
        $this->assertArraySubset(array_merge($expected, [
            'custom_fields' => ['test_key_one' => ['test_value_one', 'test_value_two']]
        ]), $mock_user_mapper->record());
    }
}
