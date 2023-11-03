<?php

namespace ImportWPTests\Common\Exporter\Mapper;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Exporter\Mapper\CommentMapper;

class CommentMapperTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;

    public function get_fields()
    {
        return [
            'comment_ID',
            'comment_post_ID',
            'comment_author',
            'comment_author_email',
            'comment_author_url',
            'comment_author_IP',
            'comment_date',
            'comment_date_gmt',
            'comment_content',
            'comment_karma',
            'comment_approved',
            'comment_agent',
            'comment_type',
            'comment_parent',
            'user_id',
        ];
    }

    /**
     * 
     * @return \PHPUnit\Framework\MockObject\MockObject | CommentMapper
     */
    public function create_mock_mapper($constructor_args = 'post', $methods = [])
    {
        return $this->getMockBuilder(CommentMapper::class)
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
            'label' => 'Comment',
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
        $mock_comment_mapper = $this->create_mock_mapper();

        $this->assertFalse($mock_comment_mapper->have_records(1));

        $post_id = $this->factory()->post->create(['post_type' => 'page']);
        $this->factory()->comment->create_post_comments($post_id, 5);
        $this->assertFalse($mock_comment_mapper->have_records(1));

        $post_id = $this->factory()->post->create(['post_type' => 'post']);
        $this->factory()->comment->create_post_comments($post_id, 5);

        $this->assertTrue($mock_comment_mapper->have_records(1));
    }

    public function test_found_records()
    {
        $mock_comment_mapper = $this->create_mock_mapper();

        $this->assertEquals(0, $mock_comment_mapper->found_records());

        $mock_comment_mapper->set_records([1, 2, 3, 4, 5]);

        $this->assertEquals(5, $mock_comment_mapper->found_records());
    }

    public function test_get_records()
    {
        $mock_comment_mapper = $this->create_mock_mapper();

        $this->assertEmpty($mock_comment_mapper->get_records());

        $records = [1, 2, 3, 4, 5];
        $mock_comment_mapper->set_records($records);

        $this->assertEquals($records, $mock_comment_mapper->get_records());
    }

    public function test_setup()
    {
        $mock_comment_mapper = $this->create_mock_mapper();

        $post_id = $this->factory()->post->create();
        $comment_ids = $this->factory()->comment->create_post_comments($post_id, 5);

        $mock_comment_mapper->set_records($comment_ids);

        $mock_comment_mapper->setup(0);
        $record = $mock_comment_mapper->record();
        $this->assertArraySubset(get_comment($comment_ids[0], ARRAY_A), $record);
        $this->assertEmpty($record['custom_fields']);

        add_comment_meta($comment_ids[1], 'test_key_one', 'test_value_one');
        add_comment_meta($comment_ids[1], 'test_key_one', 'test_value_two');

        $mock_comment_mapper->setup(1);

        $expected = array_merge(get_comment($comment_ids[1], ARRAY_A), [
            'custom_fields' => ['test_key_one' => ['test_value_one', 'test_value_two']]
        ]);
        $actual = $mock_comment_mapper->record();
        foreach ($expected as $k => $v) {
            $this->assertEquals($v, $actual[$k]);
        }
    }
}
