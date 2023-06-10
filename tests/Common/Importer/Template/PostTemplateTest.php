<?php

namespace ImportWPTests\Common\Importer\Template;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Attachment\Attachment;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Ftp\Ftp;
use ImportWP\Common\Importer\Mapper\PostMapper;
use ImportWP\Common\Importer\ParsedData;
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
        $parsed_data = new ParsedData($this->createMock(PostMapper::class));
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
        $parsed_data = new ParsedData($this->createMock(PostMapper::class));
        $parsed_data->add($attachment_data, 'attachments');

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
        $parsed_data = new ParsedData($this->createMock(PostMapper::class));
        $parsed_data->add($attachment_data, 'attachments');

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
        $parsed_data = new ParsedData($this->createMock(PostMapper::class));
        $parsed_data->add($attachment_data, 'attachments');

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
                    implode(',', [$test_cat_1->term_id, $test_cat_2->term_id, $test_tag_1->term_id]), $expected_ids, $not_expected_ids
                ];
            }],
            'Term name' => ['category', 'name', function () {

                $test_cat_1 = $this->factory()->category->create_and_get();
                $test_cat_2 = $this->factory()->category->create_and_get();
                $test_tag_1 = $this->factory()->tag->create_and_get();

                $expected_ids = [$test_cat_1->term_id, $test_cat_2->term_id];
                $not_expected_ids = [$test_tag_1->term_id];

                return [
                    implode(',', [$test_cat_1->name, $test_cat_2->name, $test_tag_1->name]), $expected_ids, $not_expected_ids
                ];
            }],
            'Term slug' => ['category', 'slug', function () {

                $test_cat_1 = $this->factory()->category->create_and_get();
                $test_cat_2 = $this->factory()->category->create_and_get();
                $test_tag_1 = $this->factory()->tag->create_and_get();

                $expected_ids = [$test_cat_1->term_id, $test_cat_2->term_id];
                $not_expected_ids = [$test_tag_1->term_id];

                return [
                    implode(',', [$test_cat_1->slug, $test_cat_2->slug, $test_tag_1->slug]), $expected_ids, $not_expected_ids
                ];
            }],
        ];
    }
}
