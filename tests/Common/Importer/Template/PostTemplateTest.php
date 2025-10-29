<?php

namespace ImportWPTests\Common\Importer\Template;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Importer\Mapper\PostMapper;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Permission\Permission;
use ImportWP\Common\Importer\Template\PostTemplate;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPTests\Utils\MockDataTrait;

/**
 * @group Template
 * @group Core
 */
class PostTemplateTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;
    use MockDataTrait;

    public function testProcessTaxonomies()
    {
        $taxonomy_data = array_merge([
            'taxonomies._index' => 2,
        ], $this->create_taxonomy_mock(0, [
            'tax' => 'category',
            'term' => 'Category 1'
        ]), $this->create_taxonomy_mock(1, [
            'tax' => 'post_tag',
            'term' => 'Tag 1, Tag Parent > Tag Child'
        ]));

        $template = new PostTemplate(new EventHandler());
        $post_id = $this->factory()->post->create();

        $post_mapper = $this->createMock(PostMapper::class);

        $parsed_data = new ParsedData($this->createMock(PostMapper::class));
        $parsed_data->add($taxonomy_data, 'taxonomies');

        $template->process_taxonomies($post_id, $parsed_data);

        $post_categories = wp_get_object_terms($post_id, 'category');
        $this->assertEquals('Category 1', $post_categories[0]->name);

        $post_tags = wp_get_object_terms($post_id, 'post_tag');
        $this->assertEquals('Tag 1', $post_tags[0]->name);
        $this->assertEquals(esc_html('Tag Parent > Tag Child'), $post_tags[1]->name);
    }

    public function testProcessTaxonomiesWithHierarchy()
    {
        $taxonomy_data = array_merge([
            'taxonomies._index' => 2,
        ], $this->create_taxonomy_mock(0, [
            'tax' => 'category',
            'term' => 'Category 1 > Category 2',
            'hierarchy' => 'yes',
            'hierarchy_character' => '>'
        ]), $this->create_taxonomy_mock(1, [
            'tax' => 'post_tag',
            'term' => 'Tag 1, Tag Parent > Tag Child',
            'hierarchy' => 'yes',
            'hierarchy_character' => '>'
        ]));

        $template = new PostTemplate(new EventHandler());
        $post_id = $this->factory()->post->create();

        $parsed_data = new ParsedData($this->createMock(PostMapper::class));
        $parsed_data->add($taxonomy_data, 'taxonomies');

        $template->process_taxonomies($post_id, $parsed_data);

        $post_categories = wp_get_object_terms($post_id, 'category');
        $this->assertEquals('Category 1', $post_categories[0]->name);

        $post_tags = wp_get_object_terms($post_id, 'post_tag');
        $this->assertEquals(3, count($post_tags));

        $names = [];
        $parents = [];
        foreach ($post_tags as $tag) {
            $names[] = $tag->name;
            $parents[$tag->name] = $tag->parent;
        }

        $this->assertEqualSets(['Tag 1', 'Tag Child', 'Tag Parent'], $names);
        $this->assertGreaterThan(0, $parents['Tag Child']);
        $this->assertEquals(0, $parents['Tag 1']);
        $this->assertEquals(0, $parents['Tag Parent']);
    }

    public function testProcessAttachmentsRemote()
    {
        $attachment_data = array_merge([
            'attachments._index' => 1,
        ], $this->create_attachment_mock(0));

        $template = new PostTemplate(new EventHandler());
        $post_id = $this->factory()->post->create();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | PostMapper
         */
        $mock_mapper = $this->createPartialMock(PostMapper::class, ['permission']);
        $parsed_data = new ParsedData($mock_mapper);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Permission
         */
        $mock_permissions = $this->createPartialMock(Permission::class, ['validate']);
        $mock_permissions->method('validate')->willReturnArgument(0);

        $mock_mapper->method('permission')->willReturn($mock_permissions);
        $parsed_data->add($attachment_data, 'attachments');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Filesystem
         */
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('download_file');

        $ftp = $this->createMock(Ftp::class);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Attachment
         */
        $attachment = $this->createMock(Attachment::class);
        $attachment->method('get_attachment_by_hash')->willReturn(0);

        $template->process_attachments($post_id, $parsed_data, $filesystem, $ftp, $attachment);
    }

    public function testProcessAttachmentsRemoteMultiple()
    {
        $attachment_data = array_merge([
            'attachments._index' => 1,
        ], $this->create_attachment_mock(0, ['location' => 'test2.png, test1.png']));

        $template = new PostTemplate(new EventHandler());
        $post_id = $this->factory()->post->create();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | PostMapper
         */
        $mock_mapper = $this->createPartialMock(PostMapper::class, ['permission']);

        $parsed_data = new ParsedData($mock_mapper);
        $parsed_data->add($attachment_data, 'attachments');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Permission
         */
        $mock_permissions = $this->createPartialMock(Permission::class, ['validate']);
        $mock_permissions->method('validate')->willReturnArgument(0);

        $mock_mapper->method('permission')->willReturn($mock_permissions);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Filesystem
         */
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->exactly(2))
            ->method('download_file')
            ->withConsecutive(
                [$this->equalTo('test2.png')],
                [$this->equalTo('test1.png')]
            );
        // $filesystem->expects($this->at(0))->method('download_file')->with('test1.png');
        // $filesystem->expects($this->at(1))->method('download_file')->with('test2.png');

        $ftp = $this->createMock(Ftp::class);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Attachment
         */
        $attachment = $this->createMock(Attachment::class);
        $attachment->method('get_attachment_by_hash')->willReturn(0);

        $template->process_attachments($post_id, $parsed_data, $filesystem, $ftp, $attachment);
    }

    public function testProcessAttachmentsLocal()
    {
        $attachment_data = array_merge([
            'attachments._index' => 1,
        ], $this->create_attachment_mock(0, [], 'local'));

        $template = new PostTemplate(new EventHandler());
        $post_id = $this->factory()->post->create();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | PostMapper
         */
        $mock_mapper = $this->createPartialMock(PostMapper::class, ['permission']);

        $parsed_data = new ParsedData();
        $parsed_data->add($attachment_data, 'attachments');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Permission
         */
        $mock_permissions = $this->createPartialMock(Permission::class, ['validate']);
        $mock_permissions->method('validate')->willReturnArgument(0);

        $mock_mapper->method('permission')->willReturn($mock_permissions);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Filesystem
         */
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('copy_file');

        $ftp = $this->createMock(Ftp::class);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Attachment
         */
        $attachment = $this->createMock(Attachment::class);
        $attachment->method('get_attachment_by_hash')->willReturn(0);

        $template->process_attachments($post_id, $parsed_data, $filesystem, $ftp, $attachment);
    }

    public function testProcessAttachmentsFTP()
    {
        $attachment_data = array_merge([
            'attachments._index' => 1,
        ], $this->create_attachment_mock(0, [], 'ftp'));

        $template = new PostTemplate(new EventHandler());
        $post_id = $this->factory()->post->create();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | PostMapper
         */
        $mock_mapper = $this->createPartialMock(PostMapper::class, ['permission']);

        $parsed_data = new ParsedData($mock_mapper);
        $parsed_data->add($attachment_data, 'attachments');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Permission
         */
        $mock_permissions = $this->createPartialMock(Permission::class, ['validate']);
        $mock_permissions->method('validate')->willReturnArgument(0);

        $mock_mapper->method('permission')->willReturn($mock_permissions);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Filesystem
         */
        $filesystem = $this->createMock(Filesystem::class);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Ftp
         */
        $ftp = $this->createMock(Ftp::class);
        $ftp->expects($this->once())->method('download_file');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Attachment
         */
        $attachment = $this->createMock(Attachment::class);
        $attachment->method('get_attachment_by_hash')->willReturn(0);

        $template->process_attachments($post_id, $parsed_data, $filesystem, $ftp, $attachment);
    }

    public function testGetPostByCustomField()
    {
        $post_type = 'post';
        $post1 = $this->factory()->post->create_and_get(['post_type' => $post_type]);
        $post2 = $this->factory()->post->create_and_get(['post_type' => $post_type]);

        $field = 'test';
        $value = 'one';

        update_post_meta($post1->ID, '_iwp_ref_' . $field, $value);
        update_post_meta($post2->ID, '_iwp_ref_' . $field, 'two');

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($post_type) {
            return $key === 'post_type' ? $post_type : null;
        }));

        $template = new PostTemplate(new EventHandler());
        $template->register_hooks($importer_model);
        $result_id = $template->get_post_by_cf($field, $value);
        $this->assertEquals($post1->ID, $result_id);
    }

    public function testGetPostParentOptions()
    {
        $post_type = 'post';
        $post1 = $this->factory()->post->create_and_get(['post_type' => $post_type]);
        $post2 = $this->factory()->post->create_and_get(['post_type' => $post_type]);
        $post3 = $this->factory()->post->create_and_get(['post_type' => 'page']);
        $post4 = $this->factory()->post->create_and_get(['post_type' => 'page']);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($post_type) {
            return $key === 'post_type' ? $post_type : null;
        }));

        $template = new PostTemplate(new EventHandler());
        $parent_options = $template->get_post_parent_options($importer_model);
        $this->assertEqualSets([
            ['value' => $post1->ID, 'label' => $post1->post_title],
            ['value' => $post2->ID, 'label' => $post2->post_title],
        ], $parent_options);
        $this->assertEquals(2, count($parent_options));
    }

    public function testGetTaxonomyOptions()
    {
        $post_type = 'post';

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($post_type) {
            return $key === 'post_type' ? $post_type : null;
        }));

        $template = new PostTemplate(new EventHandler());
        $parent_options = $template->get_taxonomy_options($importer_model);
        $this->assertEqualSets([
            ['value' => 'category', 'label' => 'Categories'],
            ['value' => 'post_format', 'label' => 'Formats'],
            ['value' => 'post_tag', 'label' => 'Tags'],
        ], $parent_options);
        $this->assertEquals(3, count($parent_options));
    }

    /**
     * @dataProvider provide_test_process_taxonomies_term_type_data
     */
    public function test_process_taxonomies_term_type($taxonomy, $type, $data)
    {

        [$term, $expected_ids, $not_expected_ids] = $data();

        $taxonomy_data = array_merge([
            'taxonomies._index' => 1,
        ], $this->create_taxonomy_mock(0, [
            'tax' => $taxonomy,
            'term' => $term,
            'type' => $type
        ]));

        $template = new PostTemplate(new EventHandler());
        $post_id = $this->factory()->post->create();

        $parsed_data = new ParsedData($this->createMock(PostMapper::class));
        $parsed_data->add($taxonomy_data, 'taxonomies');

        $template->process_taxonomies($post_id, $parsed_data);

        $term_ids = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);

        $this->assertArraySubset($expected_ids, $term_ids);

        if (!empty($not_expected_ids)) {
            foreach ($not_expected_ids as $id) {
                $this->assertNotContains($id, $term_ids);
            }
        }
    }

    public function provide_test_process_taxonomies_term_type_data()
    {
        return [

            'Term id' => ['category', 'term_id', function () {

                $test_cat_1 = $this->factory()->category->create_and_get();
                $test_cat_2 = $this->factory()->category->create_and_get();
                $test_tag_1 = $this->factory()->tag->create_and_get();

                $expected_ids = [$test_cat_1->term_id, $test_cat_2->term_id];
                $not_expected_ids = [$test_tag_1->term_id];

                return [
                    implode(',', [$test_cat_1->term_id, $test_cat_2->term_id, $test_tag_1->term_id]),
                    $expected_ids,
                    $not_expected_ids
                ];
            }],
            'Term name' => ['category', 'name', function () {

                $test_cat_1 = $this->factory()->category->create_and_get();
                $test_cat_2 = $this->factory()->category->create_and_get();
                $test_tag_1 = $this->factory()->tag->create_and_get();

                $expected_ids = [$test_cat_1->term_id, $test_cat_2->term_id];
                $not_expected_ids = [$test_tag_1->term_id];

                return [
                    implode(',', [$test_cat_1->name, $test_cat_2->name, $test_tag_1->name]),
                    $expected_ids,
                    $not_expected_ids
                ];
            }],
            'Term slug' => ['category', 'slug', function () {

                $test_cat_1 = $this->factory()->category->create_and_get();
                $test_cat_2 = $this->factory()->category->create_and_get();
                $test_tag_1 = $this->factory()->tag->create_and_get();

                $expected_ids = [$test_cat_1->term_id, $test_cat_2->term_id];
                $not_expected_ids = [$test_tag_1->term_id];

                return [
                    implode(',', [$test_cat_1->slug, $test_cat_2->slug, $test_tag_1->slug]),
                    $expected_ids,
                    $not_expected_ids
                ];
            }],
        ];
    }

    public function test_create_or_get_taxonomy_term_disable_post_create_term_filter()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | PostTemplate
         */
        $mock_post_template = $this->createPartialMock(PostTemplate::class, []);

        $test_cat_name = 'Test Cat 1';
        $post = $this->factory()->post->create_and_get();

        add_filter('iwp/importer/template/post_create_term', '__return_false');
        $result = $mock_post_template->create_or_get_taxonomy_term($post->ID, 'category', $test_cat_name, null);
        $this->assertFalse($result);

        $post_term = $this->factory()->category->create_and_get([
            'name' => $test_cat_name
        ]);

        $result = $mock_post_template->create_or_get_taxonomy_term($post->ID, 'category', $test_cat_name, null);
        $this->assertEquals($post_term, $result);
    }

    public function test_create_or_get_taxonomy_term_by_custom_field()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | PostTemplate
         */
        $mock_post_template = $this->createPartialMock(PostTemplate::class, []);

        $post = $this->factory()->post->create_and_get();
        $cf_key = '_iwp_flag_1';
        $cf_value = 'test_1';

        // 1. Make sure if no result return false
        $result = $mock_post_template->create_or_get_taxonomy_term($post->ID, 'category', [$cf_key, $cf_value], null, 'custom_field');
        $this->assertFalse($result);

        // 2. Check for matching term
        $post_term = $this->factory()->category->create_and_get();
        update_term_meta($post_term->term_id, $cf_key, $cf_value);
        $result = $mock_post_template->create_or_get_taxonomy_term($post->ID, 'category', [$cf_key, $cf_value], null, 'custom_field');
        $this->assertEquals($post_term, $result);

        // 3. Make sure empty value return false
        $result = $mock_post_template->create_or_get_taxonomy_term($post->ID, 'category', [$cf_key, ''], null, 'custom_field');
        $this->assertFalse($result);

        // 4. Make sure empty key return false
        $result = $mock_post_template->create_or_get_taxonomy_term($post->ID, 'category', ['', $cf_value], null, 'custom_field');
        $this->assertFalse($result);

        // 5. Make sure fails with no array
        $result = $mock_post_template->create_or_get_taxonomy_term($post->ID, 'category', $cf_key, null, 'custom_field');
        $this->assertFalse($result);
    }

    public function test_create_or_get_taxonomy_term_by_custom_field_with_parent()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | PostTemplate
         */
        $mock_post_template = $this->createPartialMock(PostTemplate::class, []);

        $post = $this->factory()->post->create_and_get();
        $cf_key = '_iwp_flag_1';
        $cf_value = 'test_1';

        $post_term_parent = $this->factory()->category->create_and_get();

        // 1. Make sure if no result return false
        $result = $mock_post_template->create_or_get_taxonomy_term($post->ID, 'category', [$cf_key, $cf_value], $post_term_parent->term_id, 'custom_field');
        $this->assertFalse($result);
    }

    public function test_empty_taxonomy_term_with_append_enabled()
    {
        $taxonomy_data = array_merge([
            'taxonomies._index' => 1,
        ], $this->create_taxonomy_mock(0, [
            'tax' => 'category',
            'term' => '',
            'append' => 'yes'
        ]));

        $template = new PostTemplate(new EventHandler());

        $post = $this->factory()->post->create_and_get();
        $test_cat_1 = $this->factory()->category->create_and_get();
        wp_set_object_terms($post->ID, $test_cat_1->slug, 'category');

        $original = wp_get_object_terms($post->ID, 'category');
        $this->assertNotEmpty($original);

        $mock_permission = $this->createMock(Permission::class);
        $mock_permission->method('validate')->willReturn(['taxonomy.category' => true]);

        $mock_mapper = $this->createMock(PostMapper::class);
        $mock_mapper->method('permission')->willReturn($mock_permission);

        $parsed_data = new ParsedData($mock_mapper);
        $parsed_data->add($taxonomy_data, 'taxonomies');

        $template->process_taxonomies($post->ID, $parsed_data);

        $result = wp_get_object_terms($post->ID, 'category');
        $this->assertEquals($result, $original);
    }

    public function test_empty_taxonomy_term_with_append_disabled()
    {
        $taxonomy_data = array_merge([
            'taxonomies._index' => 1,
        ], $this->create_taxonomy_mock(0, [
            'tax' => 'category',
            'term' => '',
            'append' => 'no'
        ]));

        $template = new PostTemplate(new EventHandler());

        $post = $this->factory()->post->create_and_get();
        $test_cat_1 = $this->factory()->category->create_and_get();
        wp_set_object_terms($post->ID, $test_cat_1->slug, 'category');

        $this->assertNotEmpty(wp_get_object_terms($post->ID, 'category'));

        $mock_permission = $this->createMock(Permission::class);
        $mock_permission->method('validate')->willReturn(['taxonomy.category' => true]);

        $mock_mapper = $this->createMock(PostMapper::class);
        $mock_mapper->method('permission')->willReturn($mock_permission);

        $parsed_data = new ParsedData($mock_mapper);
        $parsed_data->add($taxonomy_data, 'taxonomies');

        $template->process_taxonomies($post->ID, $parsed_data);

        $result = wp_get_object_terms($post->ID, 'category');
        $this->assertEmpty($result);
    }
}
