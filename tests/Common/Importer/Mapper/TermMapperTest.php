<?php

namespace ImportWPTests\Common\Importer\Mapper;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Mapper\TermMapper;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\TermTemplate;
use ImportWP\Common\Model\ImporterModel;
use ImportWPTests\Utils\ProtectedPropertyTrait;

/**
 * @group Mapper
 * @group Core
 */
class TermMapperTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;
    use ProtectedPropertyTrait;

    /**
     * @dataProvider providerExistsData
     */
    public function testExists($taxonomy, $fields, $expected)
    {
        wp_insert_term('category-2', 'category');

        $term_data = wp_insert_term('category-1', 'category');
        $term = get_term($term_data['term_id'], 'category', ARRAY_A);

        foreach ($fields as &$val) {

            if (strpos($val, 'category.') === false) {
                continue;
            }
            $val = $term[substr($val, strlen('category.'))];
        }

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, ['getSetting']);
        $importer_model->method('getSetting')->willReturn($taxonomy);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $data = new ParsedData($mapper);
        $data->update($fields);

        $result = $mapper->exists($data);
        $this->assertEquals($expected, $result);
    }

    public function providerExistsData()
    {
        return [
            'no data' => ['category', [], false],

            // term_id
            'term_id' => ['category', ['term_id' => 'category.term_id'], true],
            'No term_id' => ['category', ['term_id' => ''], false],
            'Wrong term_id' => ['category', ['term_id' => 'ABC'], false],
            'term_id but wrong taxonomy' => ['post_tag', ['term_id' => 'category.term_id'], false],

            // slug
            'slug' => ['category', ['slug' => 'category.slug'], true],
            'No slug' => ['category', ['slug' => ''], false],
            'Wrong slug' => ['category', ['slug' => 'ABC'], false],
            'slug but wrong taxonomy' => ['post_tag', ['slug' => 'category.slug'], false],

            // name
            'name' => ['category', ['name' => 'category.name'], true],
            'No name' => ['category', ['name' => ''], false],
            'Wrong name' => ['category', ['name' => 'ABC'], false],
            'name but wrong taxonomy' => ['post_tag', ['name' => 'category.name'], false],

            // Order of existance
            'use term_id if name, slug and term_id are present' => ['category', ['name' => 'ABC', 'slug' => 'ABC', 'term_id' => 'category.term_id'], true],
            'use slug if name and slug are present' => ['category', ['name' => 'ABC', 'slug' => 'category.slug', 'term_id' => ''], true],
            'use name if slug and term_id are not present' => ['category', ['name' => 'category.name', 'slug' => '', 'term_id' => ''], true],
        ];
    }

    /**
     * Test cant be in the dataProvider as we need to set the generated flag
     */
    public function testExistsWithNameAndGeneratedSlug()
    {
        $taxonomy = 'category';
        $fields = ['name' => 'category.name', 'slug' => 'ABC', 'term_id' => ''];
        wp_insert_term('category-2', 'category');

        $term_data = wp_insert_term('category-1', 'category');
        $term = get_term($term_data['term_id'], 'category', ARRAY_A);

        foreach ($fields as &$val) {

            if (strpos($val, 'category.') === false) {
                continue;
            }
            $val = $term[substr($val, strlen('category.'))];
        }

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, ['getSetting']);
        $importer_model->method('getSetting')->willReturn($taxonomy);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $data = new ParsedData($mapper);
        $data->update($fields);

        $result = $mapper->exists($data);
        $this->assertFalse($result);

        $data->update(['slug' => 'yes'], '_generated');
        $result = $mapper->exists($data);
        $this->assertTrue($result);
    }

    /**
     * @dataProvider provideInsertData
     */
    public function testInsert(string $taxonomy, \Closure $setData, array $expected)
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, ['getSetting']);
        $importer_model->method('getSetting')->willReturn($taxonomy);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $template = $this->createMock(TermTemplate::class, []);
        $this->setProtectedProperty($mapper, 'template', $template);


        $data = new ParsedData($mapper);
        $setData($data);

        $id = $mapper->insert($data);
        $this->assertGreaterThan(0, $id);

        $term = get_term($id, 'category', ARRAY_A);

        $this->assertArraySubset($expected, $term);
    }

    function provideInsertData()
    {
        return [
            'Insert with name' => [
                'category',
                function (ParsedData $data) {
                    $data->update([
                        'name' => 'Term 1'
                    ]);
                },
                [
                    'name' => 'Term 1',
                    'slug' => sanitize_title('Term-1')
                ]
            ],
            'Insert with name and slug' => [
                'category',
                function (ParsedData $data) {
                    $data->update([
                        'name' => 'Term 1',
                        'slug' => 'term-abc'
                    ]);
                },
                [
                    'name' => 'Term 1',
                    'slug' => 'term-abc'
                ]
            ],
            'Insert all fields' => [
                'category',
                function (ParsedData $data) {
                    $data->update([
                        'name' => 'Term 1',
                        'slug' => 'term-abc',
                        'description' => 'Term description',
                    ]);
                },
                [
                    'name' => 'Term 1',
                    'slug' => 'term-abc',
                    'description' => 'Term description',
                ]
            ]
        ];
    }

    public function testInsertWithNoName()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        $data = new ParsedData($mapper);
        $data->update([]);

        $this->expectException(MapperException::class);
        $mapper->insert($data);
    }

    public function testInsertWithParent()
    {
        $term = $this->factory()->term->create_and_get();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getSetting')->willReturn('category');
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermTemplate
         */
        $template = $this->createMock(TermTemplate::class);
        $this->setProtectedProperty($mapper, 'template', $template);

        $data = new ParsedData($mapper);
        $data->update([
            'name' => 'Term ABC',
            'parent' => $term->term_id
        ]);

        $id = $mapper->insert($data);

        $import_term = get_term($id, 'category', ARRAY_A);
        $this->assertArraySubset([
            'name' => 'Term ABC',
            'parent' => $term->term_id
        ], $import_term);
    }

    public function testInsertWithInvalidParent()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getSetting')->willReturn('category');
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermTemplate
         */
        $template = $this->createMock(TermTemplate::class);
        $this->setProtectedProperty($mapper, 'template', $template);

        $data = new ParsedData($mapper);
        $data->update([
            'name' => 'Term 1',
            'parent' => 99
        ]);

        $this->expectException(MapperException::class);
        $mapper->insert($data);
    }

    public function testInsertWithCustomFields()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getSetting')->willReturn('category');
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermTemplate
         */
        $template = $this->createMock(TermTemplate::class);
        $this->setProtectedProperty($mapper, 'template', $template);

        $data = new ParsedData($mapper);
        $data->update([
            'name' => 'Term 1',
            '_term_meta_1' => '',
            'term_meta_2' => '1',
            'term_meta_3' => 'yes',
        ]);

        $term_id = $mapper->insert($data);
        $this->assertGreaterThan(0, $term_id);
        $this->assertEquals('', get_term_meta($term_id, '_term_meta_1', true));
        $this->assertEquals('1', get_term_meta($term_id, 'term_meta_2', true));
        $this->assertEquals('yes', get_term_meta($term_id, 'term_meta_3', true));
    }

    public function testInsertWithCustomFieldsGroup()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getSetting')->willReturn('category');
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermTemplate
         */
        $template = $this->createMock(TermTemplate::class);
        $this->setProtectedProperty($mapper, 'template', $template);

        $data = new ParsedData($mapper);
        $data->update([
            'name' => 'Term 1',
        ]);

        $data->update([
            '_term_meta_1' => '',
            'term_meta_2' => '1',
            'term_meta_3' => 'yes',
        ], 'custom_fields');

        $term_id = $mapper->insert($data);
        $this->assertGreaterThan(0, $term_id);
        $this->assertEquals('', get_term_meta($term_id, '_term_meta_1', true));
        $this->assertEquals('1', get_term_meta($term_id, 'term_meta_2', true));
        $this->assertEquals('yes', get_term_meta($term_id, 'term_meta_3', true));
    }

    /**
     * @dataProvider provideTestUpdateData
     */
    public function testUpdate($term, $meta = array(), \Closure $setData, $expected)
    {
        // TODO: Test update method
        $existing_term = $this->factory()->category->create_and_get($term);
        // $inserted_term = wp_insert_term($existing_term->name, $existing_term->taxonomy);
        $inserted_term_id = $existing_term->term_id;

        if (count($meta) > 0) {
            foreach ($meta as $k => $v) {
                update_term_meta($inserted_term_id, $k, $v);
            }
        }

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);
        $this->setProtectedProperty($mapper, 'ID', $inserted_term_id);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getSetting')->willReturn('category');
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermTemplate
         */
        $template = $this->createMock(TermTemplate::class);
        $this->setProtectedProperty($mapper, 'template', $template);

        $data = new ParsedData($mapper);
        $setData($data);

        $mapper->update($data);

        $term = get_term($inserted_term_id, 'category', ARRAY_A);

        $this->assertArraySubset($expected['name'], $term);

        $imported_meta = [];
        if (isset($expected['meta']) && !empty($expected['meta'])) {
            foreach ($expected['meta'] as $k => $v) {
                $imported_meta[$k] = get_term_meta($inserted_term_id, $k, true);
            }
        }
        $this->assertArraySubset($expected['meta'], $imported_meta);
    }

    public function provideTestUpdateData()
    {
        $term1 = [
            'name' => 'Term One Test',
            'slug' => 'term-one-slug-123',
            'description' => 'Term one description'
        ];
        $term1_meta = [
            'name' => 'Name meta',
            '_name' => '_name meta',
            'empty_cf' => 'Empty',
            'boolean_cf' => 'false'
        ];
        $term_updates = [
            'name' => 'Term One Updated',
            'slug' => 'term-one-slug-123-updated',
            'description' => 'Term one description updated'
        ];
        $meta_updates = [
            'name' => 'Updated Name meta',
            '_name' => '_updated name meta',
            'empty_cf' => 'Not empty',
            'boolean_cf' => 'true'
        ];
        return [
            'Update nothing' => [
                $term1,
                [],
                function (ParsedData $data) use ($term_updates) {
                    $data->update([]);
                },
                [
                    'name' => [
                        'name' => $term1['name'],
                        'slug' => $term1['slug'],
                        'description' => $term1['description']
                    ],
                    'meta' => []
                ]
            ],
            'Update category name' => [
                $term1,
                [],
                function (ParsedData $data) use ($term_updates) {
                    $data->update([
                        'name' => $term_updates['name']
                    ]);
                },
                [
                    'name' => [
                        'name' => $term_updates['name'],
                        'slug' => $term1['slug'],
                        'description' => $term1['description']
                    ],
                    'meta' => []
                ]
            ],
            'Update name, description, and slug' => [
                $term1,
                $term1_meta,
                function (ParsedData $data) use ($term_updates) {
                    $data->update([
                        'name' => $term_updates['name'],
                        'slug' => $term_updates['slug'],
                        'description' => $term_updates['description']
                    ]);
                },
                [
                    'name' => [
                        'name' => $term_updates['name'],
                        'slug' => $term_updates['slug'],
                        'description' => $term_updates['description']
                    ],
                    'meta' => []
                ]
            ],
            'Update custom fields' => [
                $term1,
                $term1_meta,
                function (ParsedData $data) use ($meta_updates) {
                    $data->update($meta_updates, 'custom_fields');
                },
                [
                    'name' => $term1,
                    'meta' => $meta_updates
                ]
            ]
        ];
    }

    public function testAddVersionTag()
    {
        $existing_term = $this->factory()->category->create_and_get();
        $status_id = 'ABC123';
        $importer_id = 999;

        $taxonomy = 'category';

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);
        $this->setProtectedProperty($mapper, 'ID', $existing_term->term_id);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);
        $importer_model->method('getSetting')->willReturn($taxonomy);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $this->assertEmpty(get_term_meta($existing_term->term_id, '_iwp_session_' . $importer_id, true));
        $mapper->add_version_tag();
        $this->assertEquals($status_id, get_term_meta($existing_term->term_id, '_iwp_session_' . $importer_id, true));
    }

    public function testGetObjectsForRemoval()
    {
        $term1 = $this->factory()->category->create_and_get();
        $term2 = $this->factory()->category->create_and_get();
        $term3 = $this->factory()->category->create_and_get();
        $term4 = $this->factory()->category->create_and_get();
        $term5 = $this->factory()->category->create_and_get();

        $status_id = 'ABC123';
        $importer_id = 999;

        $taxonomy = 'category';

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);
        $importer_model->method('getSetting')->willReturn($taxonomy);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermTemplate
         */
        // $template = $this->createMock(TermTemplate::class);
        // $this->setProtectedProperty($mapper, 'template', $template);


        $this->setProtectedProperty($mapper, 'ID', $term2->term_id);
        $mapper->add_version_tag();
        $this->setProtectedProperty($mapper, 'ID', $term3->term_id);
        $mapper->add_version_tag();

        $status_id = 'ABC456';

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);
        $importer_model->method('getSetting')->willReturn($taxonomy);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $this->setProtectedProperty($mapper, 'ID', $term1->term_id);
        $mapper->add_version_tag();
        $this->setProtectedProperty($mapper, 'ID', $term4->term_id);
        $mapper->add_version_tag();

        $ids = $mapper->get_objects_for_removal();
        $this->assertContains($term2->term_id, $ids);
        $this->assertContains($term3->term_id, $ids);
        $this->assertNotContains($term1->term_id, $ids);
        $this->assertNotContains($term4->term_id, $ids);
        $this->assertNotContains($term5->term_id, $ids);
    }

    public function testDelete()
    {
        $term1 = $this->factory()->category->create_and_get();
        $term2 = $this->factory()->category->create_and_get();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getSetting')->willReturn('category');
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $mapper->delete($term1->term_id);

        $this->assertNull(get_term($term1->term_id, 'category'));
        $this->assertEquals($term2, get_term($term2->term_id, 'category'));
    }

    public function testUpdateCustomField()
    {
        $existing_term = $this->factory()->category->create_and_get();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mapper = $this->createPartialMock(TermMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class, []);
        $importer_model->method('getSetting')->willReturn('category');
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $this->assertEmpty(get_term_meta($existing_term->term_id, '_test_meta', true));

        $mapper->update_custom_field($existing_term->term_id, '_test_meta', 'yes');
        $this->assertEquals('yes', get_term_meta($existing_term->term_id, '_test_meta', true));

        $mapper->update_custom_field($existing_term->term_id, '_test_meta', 'no');
        $this->assertEquals('no', get_term_meta($existing_term->term_id, '_test_meta', true));

        $mapper->update_custom_field($existing_term->term_id, '_test_meta', '');
        $this->assertEquals('', get_term_meta($existing_term->term_id, '_test_meta', true));
    }
}
