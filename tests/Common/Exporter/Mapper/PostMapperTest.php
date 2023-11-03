<?php

namespace ImportWPTests\Common\Exporter\Mapper;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Exporter\Mapper\PostMapper;

class PostMapperTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;

    public function get_fields()
    {
        return [
            'ID',
            'post_author',
            'post_date',
            'post_date_gmt',
            'post_content',
            'post_title',
            'post_excerpt',
            'post_status',
            'comment_status',
            'ping_status',
            'post_password',
            'post_name',
            'to_ping',
            'pinged',
            'post_modified',
            'post_modified_gmt',
            'post_content_filtered',
            'post_parent',
            'guid',
            'menu_order',
            'post_type',
            'post_mime_type',
            'comment_count'
        ];
    }

    /**
     * 
     * @return \PHPUnit\Framework\MockObject\MockObject | PostMapper
     */
    public function create_mock_mapper($constructor_args = 'post', $methods = [])
    {
        return $this->getMockBuilder(PostMapper::class)
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
            'label' => 'Post',
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
        $mock_post_mapper = $this->create_mock_mapper();

        $this->assertFalse($mock_post_mapper->have_records(1));

        $this->factory()->post->create_many(5, ['post_type' => 'page']);
        $this->assertFalse($mock_post_mapper->have_records(1));

        $this->factory()->post->create_many(5, ['post_type' => 'post']);
        $this->assertTrue($mock_post_mapper->have_records(1));
    }

    public function test_found_records()
    {
        $mock_post_mapper = $this->create_mock_mapper();

        $this->assertEquals(0, $mock_post_mapper->found_records());

        $mock_post_mapper->set_records([1, 2, 3, 4, 5]);

        $this->assertEquals(5, $mock_post_mapper->found_records());
    }

    public function test_get_records()
    {
        $mock_post_mapper = $this->create_mock_mapper();

        $this->assertEmpty($mock_post_mapper->get_records());

        $records = [1, 2, 3, 4, 5];
        $mock_post_mapper->set_records($records);

        $this->assertEquals($records, $mock_post_mapper->get_records());
    }

    public function test_setup()
    {
        $mock_post_mapper = $this->create_mock_mapper();

        $post_ids = $this->factory()->post->create_many(5);

        $mock_post_mapper->set_records($post_ids);

        $mock_post_mapper->setup(0);
        $record = $mock_post_mapper->record();
        $this->assertArraySubset(get_post($post_ids[0], ARRAY_A), $record);

        add_post_meta($post_ids[1], 'test_key_one', 'test_value_one');
        add_post_meta($post_ids[1], 'test_key_one', 'test_value_two');

        $mock_post_mapper->setup(1);

        $expected = array_merge(get_post($post_ids[1], ARRAY_A), [
            'custom_fields' => ['test_key_one' => ['test_value_one', 'test_value_two'], '_pingme' => [1], '_encloseme' => [1]]
        ]);
        $actual = $mock_post_mapper->record();

        foreach ($expected as $k => $v) {
            $this->assertEquals($v, $actual[$k]);
        }
    }
}
