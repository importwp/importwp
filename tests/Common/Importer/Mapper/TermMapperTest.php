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
    public function testExists($expected, $taxonomy, \Closure $setData, $terms)
    {
        $existing_terms = [];

        if (!empty($terms)) {
            foreach ($terms as $term) {
                $existing_terms[] = $this->factory()->term->create_and_get($term);
            }
        }

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $mock_importer_model = $this->createPartialMock(ImporterModel::class, ['getSetting']);
        $mock_importer_model->method('getSetting')
            ->willReturnCallback(function ($arg) use ($taxonomy) {
                switch ($arg) {
                    case 'unique_field':
                        return null;
                    case 'taxonomy':
                        return $taxonomy;
                }
            });

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TermMapper
         */
        $mock_mapper = $this->createPartialMock(TermMapper::class, []);
        $this->setProtectedProperty($mock_mapper, 'importer', $mock_importer_model);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ParsedData
         */
        // $mock_parsed_data = $this->createPartialMock(ParsedData::class, []);
        $parsed_data = new ParsedData($mock_mapper);
        $setData($parsed_data, $existing_terms);

        if ($expected === -1) {
            $this->expectException(MapperException::class);
        }

        $result = $mock_mapper->exists($parsed_data);

        if ($expected !== -1) {
            $this->assertEquals($expected, $result);
        }
    }

    public function providerExistsData()
    {
        return [
            'No Data' => [
                -1,
                'category',
                function (ParsedData $data, $terms) {
                },
                []
            ],

            // ID
            'Valid Term ID' => [
                true,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'term_id' => $terms[0]->term_id
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],

            'Valid Term ID, Wrong Taxonomy' => [
                false,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'term_id' => $terms[0]->term_id
                    ]);
                },
                [
                    [
                        'taxonomy' => 'post_tag'
                    ],
                    [
                        'taxonomy' => 'post_tag'
                    ]
                ]
            ],

            'Wrong Term ID' => [
                false,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'term_id' => 9999
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],

            'No Term ID' => [
                -1,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'term_id' => ''
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],

            // SLUG
            'Valid Term Slug' => [
                true,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'slug' => $terms[0]->slug
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],

            'Valid Term Slug, Wrong Taxonomy' => [
                false,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'slug' => $terms[0]->slug
                    ]);
                },
                [
                    [
                        'taxonomy' => 'post_tag'
                    ],
                    [
                        'taxonomy' => 'post_tag'
                    ]
                ]
            ],

            'Wrong Term Slug' => [
                false,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'slug' => 9999
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],

            'No Term Slug' => [
                -1,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'slug' => ''
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],

            // Name
            'Valid Term name' => [
                true,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'name' => $terms[0]->name
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],

            'Valid Term name, Wrong Taxonomy' => [
                false,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'name' => $terms[0]->name
                    ]);
                },
                [
                    [
                        'taxonomy' => 'post_tag'
                    ],
                    [
                        'taxonomy' => 'post_tag'
                    ]
                ]
            ],

            'Wrong Term name' => [
                false,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'name' => 9999
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],

            'No Term name' => [
                -1,
                'category',
                function (ParsedData $data, $terms) {

                    $data->update([
                        'name' => ''
                    ]);
                },
                [
                    [
                        'taxonomy' => 'category'
                    ],
                    [
                        'taxonomy' => 'category'
                    ]
                ]
            ],
        ];
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
