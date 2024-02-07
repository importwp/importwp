<?php

namespace ImportWPTests\Common\Importer\Mapper;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Mapper\PostMapper;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\PostTemplate;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Model\ImporterModel;
use ImportWPTests\Utils\ProtectedPropertyTrait;

/**
 * @group Mapper
 * @group Core
 */
class PostMapperTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;
    use ProtectedPropertyTrait;

    /**
     * @dataProvider provideExistsData
     */
    public function testExists($expected, \Closure $setData, $posts, $debug = false)
    {
        // Write exists tests
        if ($debug === true) {
            $debug = true;
        }
        $existing_posts = [];
        $post_type = 'post';

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $existing_posts[] = $this->factory()->post->create_and_get($post);
            }
        }

        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($post_type) {
            return $key === 'post_type' ? $post_type : null;
        }));



        $mapper = $this->createPartialMock(PostMapper::class, []);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        // template is now needed to get unique fields
        $template = $this->createPartialMock(PostTemplate::class, []);
        $this->setProtectedProperty($mapper, 'template', $template);

        $parsed_data = new ParsedData($mapper);
        $setData($parsed_data, $existing_posts);


        if ($expected === -1) {
            $this->expectException(MapperException::class);
        }

        $result = $mapper->exists($parsed_data);

        if ($expected !== -1) {
            $this->assertEquals($expected, $result);
        }
    }

    public function provideExistsData()
    {
        return [
            'No Data' => [
                -1,
                function (ParsedData $data, $existing_posts) {
                },
                []
            ],
            // ID
            'Valid Post Id' => [
                true,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'ID' => $existing_posts[0]->ID
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],
            'Valid Post Id, wrong post_type' => [
                false,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'ID' => $existing_posts[0]->ID,
                    ]);
                },
                [
                    ['post_type' => 'page'], // create random post
                    ['post_type' => 'page'], // create random post
                    ['post_type' => 'page'], // create random post
                ]
            ],
            'Wrong Post Id' => [
                false,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'ID' => 1
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],
            'No Post Id' => [
                -1,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'ID' => ''
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],

            // Slug
            'Valid Slug' => [
                true,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_name' => $existing_posts[0]->post_name
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ],
            ],
            'Valid Slug, wrong post_type' => [
                false,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_name' => $existing_posts[0]->post_name,
                    ]);
                },
                [
                    ['post_type' => 'page'], // create random post
                    ['post_type' => 'page'], // create random post
                    ['post_type' => 'page'], // create random post
                ]
            ],
            'Wrong Slug' => [
                false,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_name' => 1
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],
            'No Slug' => [
                -1,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_name' => ''
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],

            // Title
            'Valid Title' => [
                true,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_title' => $existing_posts[0]->post_title
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ],
            ],
            'Valid Title, wrong post_type' => [
                false,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_title' => $existing_posts[0]->post_title,
                    ]);
                },
                [
                    ['post_type' => 'page'], // create random post
                    ['post_type' => 'page'], // create random post
                    ['post_type' => 'page'], // create random post
                ]
            ],
            'Wrong Title' => [
                false,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_title' => 1
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],
            'No Title' => [
                -1,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_title' => ''
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],

            // Order of existance
            'Use ID, if ID, Name and Slug are present' => [
                true,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'ID' => $existing_posts[0]->ID,
                        'post_title' => 'ABC',
                        'post_name' => 'ABC',
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],
            'Use Slug, if only Slug and Name are present' => [
                true,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_title' => 'ABC',
                        'post_name' => $existing_posts[0]->post_name,
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ]
            ],
            'Use Name, if only Name is present' => [
                true,
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_title' => $existing_posts[0]->post_title,
                    ]);
                },
                [
                    [], // create random post
                    [], // create random post
                    [], // create random post
                ],
                true
            ],
        ];
    }

    public function testExistsWithCustomUniqueIdentifier()
    {
        $importer_model = $this->createPartialMock(ImporterModel::class, ['has_custom_unique_identifier', 'getSetting']);
        $importer_model->method('has_custom_unique_identifier')->willReturn(true);
        $importer_model->method('getSetting')->willReturnMap([
            ['post_type', 'post']
        ]);

        $mapper = $this->createPartialMock(PostMapper::class, []);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $uid_key = $importer_model->get_iwp_reference_meta_key();

        // Create mock data
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'post',
        ]);
        $post_with_cf = $this->factory()->post->create_and_get([
            'post_type' => 'post',
            'meta_input' => [
                'custom_field' => '123'
            ]
        ]);
        $post_with_title = $this->factory()->post->create_and_get([
            'post_type' => 'post',
            'post_title' => '123',
        ]);

        $post_with_slug = $this->factory()->post->create_and_get([
            'post_type' => 'post',
            'post_name' => '123',
        ]);

        // make sure no matches
        $data = $this->createPartialMock(ParsedData::class, []);
        $data->add([
            $uid_key => '123'
        ], 'iwp');
        $this->assertFalse($mapper->exists($data));

        $post_with_uid = $this->factory()->post->create_and_get([
            'post_type' => 'post',
            'meta_input' => [
                $uid_key => '123'
            ]
        ]);

        // should match
        $this->assertEquals($post_with_uid->ID, $mapper->exists($data));
    }

    public function testExistsWithFieldUniqueIdentifier()
    {
        $mock_importer = function ($settings) {

            $importer_model = $this->createPartialMock(ImporterModel::class, ['has_custom_unique_identifier', 'has_field_unique_identifier', 'getSetting']);
            $importer_model->method('has_custom_unique_identifier')->willReturn(false);
            $importer_model->method('has_field_unique_identifier')->willReturn(true);
            $importer_model->method('getSetting')->willReturnMap($settings);

            $mapper = $this->createPartialMock(PostMapper::class, []);
            $this->setProtectedProperty($mapper, 'importer', $importer_model);

            return [$importer_model, $mapper];
        };

        /**
         * @var ImporterModel|\PHPUnit\Framework\MockObject\MockObject $importer_model
         * @var PostMapper|\PHPUnit\Framework\MockObject\MockObject $mapper
         */
        list($importer_model, $mapper) = $mock_importer([
            ['post_type', 'post'],
        ]);

        $uid_key = $importer_model->get_iwp_reference_meta_key();

        // Create mock data
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'post',
        ]);
        $post_with_cf = $this->factory()->post->create_and_get([
            'post_type' => 'post',
            'meta_input' => [
                'custom_field' => '123'
            ]
        ]);
        $post_with_slug = $this->factory()->post->create_and_get([
            'post_type' => 'post',
            'post_name' => '123',
        ]);
        $post_with_title = $this->factory()->post->create_and_get([
            'post_type' => 'post',
            'post_title' => '123',
        ]);
        $post_with_uid = $this->factory()->post->create_and_get([
            'post_type' => 'post',
            'meta_input' => [
                $uid_key => '123'
            ]
        ]);

        try {
            $data = $this->createPartialMock(ParsedData::class, []);
            $mapper->exists($data);
            $this->fail('MapperException was not thrown');
        } catch (MapperException $e) {
            $this->assertSame('No Unique fields present.', $e->getMessage());
        }

        // if unique_identifier has a value, but it is not found in the template

        /**
         * @var ImporterModel|\PHPUnit\Framework\MockObject\MockObject $importer_model
         * @var PostMapper|\PHPUnit\Framework\MockObject\MockObject $mapper
         */
        list($importer_model, $mapper) = $mock_importer([
            ['post_type', 'post'],
            ['unique_identifier', 'post_title']
        ]);

        try {
            $data = $this->createPartialMock(ParsedData::class, []);
            $mapper->exists($data);
            $this->fail('MapperException was not thrown');
        } catch (MapperException $e) {
            $this->assertSame('No Unique fields present.', $e->getMessage());
        }

        // Match post_title

        /**
         * @var ImporterModel|\PHPUnit\Framework\MockObject\MockObject $importer_model
         * @var PostMapper|\PHPUnit\Framework\MockObject\MockObject $mapper
         */
        list($importer_model, $mapper) = $mock_importer([
            ['post_type', 'post'],
            ['unique_identifier', 'post_title']
        ]);

        $data = $this->createPartialMock(ParsedData::class, []);
        $data->add([
            'post_title' => '123'
        ]);

        $this->assertEquals($post_with_title->ID, $mapper->exists($data));

        // Match post_name

        /**
         * @var ImporterModel|\PHPUnit\Framework\MockObject\MockObject $importer_model
         * @var PostMapper|\PHPUnit\Framework\MockObject\MockObject $mapper
         */
        list($importer_model, $mapper) = $mock_importer([
            ['post_type', 'post'],
            ['unique_identifier', 'post_name']
        ]);

        $data = $this->createPartialMock(ParsedData::class, []);
        $data->add([
            'post_name' => '123'
        ]);

        $this->assertEquals($post_with_slug->ID, $mapper->exists($data));

        // Match custom field

        /**
         * @var ImporterModel|\PHPUnit\Framework\MockObject\MockObject $importer_model
         * @var PostMapper|\PHPUnit\Framework\MockObject\MockObject $mapper
         */
        list($importer_model, $mapper) = $mock_importer([
            ['post_type', 'post'],
            ['unique_identifier', 'custom_field']
        ]);

        $data = $this->createPartialMock(ParsedData::class, []);

        // no custom fields
        try {
            $data = $this->createPartialMock(ParsedData::class, []);
            $mapper->exists($data);
            $this->fail('MapperException was not thrown');
        } catch (MapperException $e) {
            $this->assertSame('No Unique fields present.', $e->getMessage());
        }

        // field but no match
        $data = $this->createPartialMock(ParsedData::class, []);
        $data->add([
            'custom_fields._index' => '1',
            'custom_fields.0.key' => 'custom_field',
            'custom_fields.0.value' => '1231',
        ], 'custom_fields');
        $this->assertFalse($mapper->exists($data));

        // with match
        $data = $this->createPartialMock(ParsedData::class, []);
        $data->add([
            'custom_fields._index' => '1',
            'custom_fields.0.key' => 'custom_field',
            'custom_fields.0.value' => '123',
        ], 'custom_fields');
        $this->assertEquals($post_with_cf->ID, $mapper->exists($data));
    }

    /**
     * @dataProvider provideInsertData
     */
    public function testInsert($expected, \Closure $setData, $posts, $post_type = 'post', $debug = false)
    {
        if ($debug === true) {
            $debug = true;
        }

        $existing_posts = [];

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $existing_posts[] = $this->factory()->post->create_and_get($post);
            }
        }

        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($post_type) {
            return $key === 'post_type' ? $post_type : null;
        }));

        $template = $this->createMock(PostTemplate::class);
        $mapper = $this->createPartialMock(PostMapper::class, []);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);
        $this->setProtectedProperty($mapper, 'template', $template);

        $parsed_data = new ParsedData($mapper);
        $setData($parsed_data, $existing_posts);

        $id = $mapper->insert($parsed_data);
        $this->assertGreaterThan(0, $id);

        $record = get_post($id, ARRAY_A);

        $this->assertArraySubset($expected, $record);
    }

    public function provideInsertData()
    {
        $post = [
            'post_title' => 'ABC',
            'post_name' => 'abc-123',
            'post_content' => 'ABC POST CONTENT',
            'post_excerpt' => 'ABC POST EXCERPT',
            'post_status' => 'publish',
            'menu_order' => 10,
            'post_author' => 1,
            'post_password' => 'abc',
            'post_date' => '2020-01-16 08:03:00',
            'comment_status' => 'open',
            'ping_status' => 'closed',
        ];
        return [
            'Insert basic record' => [
                [
                    'post_title' => $post['post_title'],
                    'post_name' => $post['post_name'],
                    'post_content' => $post['post_content'],
                    'post_excerpt' => $post['post_excerpt'],
                    'post_status' => $post['post_status'],
                    'menu_order' => $post['menu_order'],
                    'post_author' => $post['post_author'],
                    'post_password' => $post['post_password'],
                    'post_date' => $post['post_date'],
                    'comment_status' => $post['comment_status'],
                    'ping_status' => $post['ping_status'],
                ],
                function (ParsedData $data, $existing_posts) use ($post) {
                    $data->update([
                        'post_title' => $post['post_title'],
                        'post_name' => $post['post_name'],
                        'post_content' => $post['post_content'],
                        'post_excerpt' => $post['post_excerpt'],
                        'post_status' => $post['post_status'],
                        'menu_order' => $post['menu_order'],
                        'post_author' => $post['post_author'],
                        'post_password' => $post['post_password'],
                        'post_date' => $post['post_date'],
                        'comment_status' => $post['comment_status'],
                        'ping_status' => $post['ping_status'],
                    ]);
                },
                [
                    []
                ],
            ],
            'Generate post_name from title if published' => [
                [
                    'post_title' => 'ABC',
                    'post_name' => sanitize_title('ABC')
                ],
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_title' => 'ABC',
                        'post_status' => 'publish' // need this to set the post_name
                    ]);
                },
                [],
            ],
            'Dont Generate post_name if not published' => [
                [
                    'post_title' => 'ABC',
                    'post_name' => ''
                ],
                function (ParsedData $data, $existing_posts) {
                    $data->update([
                        'post_title' => 'ABC', // need this to set the post_name
                    ]);
                },
                [],
            ],
        ];
    }

    /**
     * @dataProvider provideUpdateData
     */
    public function testUpdate(\Closure $setExpected, \Closure $setData, $posts, $post_type = 'post', $debug = false)
    {
        if ($debug === true) {
            $debug = true;
        }

        $existing_posts = [];

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $existing_posts[] = $this->factory()->post->create_and_get($post);
            }
        }

        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($key) use ($post_type) {
            return $key === 'post_type' ? $post_type : null;
        }));

        $template = $this->createMock(PostTemplate::class);
        $mapper = $this->createPartialMock(PostMapper::class, []);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);
        $this->setProtectedProperty($mapper, 'template', $template);

        $parsed_data = new ParsedData($mapper);
        $setData($parsed_data, $existing_posts);

        $id = $existing_posts[0]->ID;

        $this->setProtectedProperty($mapper, 'ID', $id);
        $mapper->update($parsed_data);

        $expected = $setExpected($parsed_data, $existing_posts);

        $record = get_post($id, ARRAY_A);

        $this->assertArraySubset($expected, $record);
    }

    public function provideUpdateData()
    {
        $post = [
            'post_title' => 'ABC',
            'post_name' => 'abc-123',
            'post_content' => 'ABC POST CONTENT',
            'post_excerpt' => 'ABC POST EXCERPT',
            'post_status' => 'publish',
            'menu_order' => 10,
            'post_author' => 1,
            'post_password' => 'abc',
            'post_date' => '2020-01-16 08:03:00',
            'comment_status' => 'open',
            'ping_status' => 'closed',
        ];
        $updated_post = [
            'post_title' => 'Updated-ABC',
            'post_name' => 'abc-123-updated',
            'post_content' => 'ABC POST CONTENT UPDATED',
            'post_excerpt' => 'ABC POST EXCERPT UPDATED',
            'post_status' => 'draft',
            'menu_order' => 2,
            'post_author' => 2,
            'post_password' => 'd',
            'post_date' => '2020-01-15 08:03:00',
            'comment_status' => 'closed',
            'ping_status' => 'open',
        ];
        return [
            'Update no data' => [
                function (ParsedData $outputData, $mock_posts) {
                    return [];
                },
                function (ParsedData $inputData, $mock_posts) {
                    $inputData->update([]);
                },
                [
                    $post
                ]
            ],
            'Update core fields' => [
                function (ParsedData $outputData, $mock_posts) use ($updated_post) {
                    return $updated_post;
                },
                function (ParsedData $inputData, $mock_posts) use ($updated_post) {
                    $inputData->update($updated_post);
                },
                [
                    $post
                ]
            ],
            'Update Title and Slug' => [
                function (ParsedData $outputData, $mock_posts) use ($updated_post, $post) {
                    $expected = $post;
                    $expected['post_title'] = $updated_post['post_title'];
                    $expected['post_name'] = $updated_post['post_name'];
                    return $expected;
                },
                function (ParsedData $inputData, $mock_posts) use ($updated_post) {
                    $inputData->update([
                        'post_title' => $updated_post['post_title'],
                        'post_name' => $updated_post['post_name']
                    ]);
                },
                [
                    $post
                ]
            ],
            'Update Title' => [
                function (ParsedData $outputData, $mock_posts) use ($updated_post, $post) {
                    $expected = $post;
                    $expected['post_title'] = $updated_post['post_title'];
                    return $expected;
                },
                function (ParsedData $inputData, $mock_posts) use ($updated_post, $post) {
                    $inputData->update([
                        'post_title' => $updated_post['post_title'],
                        'post_name' => $post['post_name']
                    ]);
                },
                [
                    $post
                ]
            ],
        ];
    }

    public function test_update_restore_deleted_record_with_success()
    {
        $post = $this->factory()->post->create_and_get(['post_status' => 'trash']);
        $importer_post = $this->factory()->post->create_and_get();

        $mapper = $this->createPartialMock(PostMapper::class, ['sortFields']);
        $mapper->method('sortFields')
            ->willReturn([]);

        $data = $this->createMock(ParsedData::class);
        $data->method('getData')
            ->willReturn([]);

        $importer = $this->createMock(ImporterModel::class);
        $importer->method('getId')
            ->willReturn($importer_post->ID);

        $template = $this->createMock(Template::class);

        $this->setProtectedProperty($mapper, 'ID', $post->ID);
        $this->setProtectedProperty($mapper, 'importer', $importer);
        $this->setProtectedProperty($mapper, 'template', $template);

        update_post_meta($post->ID, '_iwp_trash_status', 'publish');
        update_post_meta($post->ID, '_iwp_trash_importer', $importer_post->ID);

        $mapper->update($data);

        $this->assertEquals('publish', get_post_status($post->ID));
        $this->assertEmpty(get_post_meta($post->ID, '_iwp_trash_status', true));
        $this->assertEmpty(get_post_meta($post->ID, '_iwp_trash_importer', true));
    }

    public function test_update_restore_deleted_record_with_different_importer()
    {
        $post = $this->factory()->post->create_and_get(['post_status' => 'trash']);
        $importer_post = $this->factory()->post->create_and_get();
        $importer2_post = $this->factory()->post->create_and_get();

        $mapper = $this->createPartialMock(PostMapper::class, ['sortFields']);
        $mapper->method('sortFields')
            ->willReturn([]);

        $data = $this->createMock(ParsedData::class);
        $data->method('getData')
            ->willReturn([]);

        $importer = $this->createMock(ImporterModel::class);
        $importer->method('getId')
            ->willReturn($importer_post->ID);


        $template = $this->createMock(Template::class);

        $this->setProtectedProperty($mapper, 'ID', $post->ID);
        $this->setProtectedProperty($mapper, 'importer', $importer);
        $this->setProtectedProperty($mapper, 'template', $template);

        update_post_meta($post->ID, '_iwp_trash_status', 'publish');
        update_post_meta($post->ID, '_iwp_trash_importer', $importer2_post->ID);

        $mapper->update($data);

        $this->assertEquals('trash', get_post_status($post->ID));
        $this->assertEquals('publish', get_post_meta($post->ID, '_iwp_trash_status', true));
        $this->assertEquals($importer2_post->ID, get_post_meta($post->ID, '_iwp_trash_importer', true));
    }

    public function testGetObjectsForRemoval()
    {
        $post1 = $this->factory()->post->create_and_get();
        $post2 = $this->factory()->post->create_and_get();
        $post3 = $this->factory()->post->create_and_get();
        $post4 = $this->factory()->post->create_and_get();
        $post5 = $this->factory()->post->create_and_get();

        $status_id = 'ABC123';
        $importer_id = 999;

        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);
        $mapper = $this->createPartialMock(PostMapper::class, []);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $this->setProtectedProperty($mapper, 'ID', $post2->ID);
        $mapper->add_version_tag();
        $this->setProtectedProperty($mapper, 'ID', $post3->ID);
        $mapper->add_version_tag();

        $status_id = 'ABC456';
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);
        $mapper = $this->createPartialMock(PostMapper::class, []);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $this->setProtectedProperty($mapper, 'ID', $post1->ID);
        $mapper->add_version_tag();
        $this->setProtectedProperty($mapper, 'ID', $post4->ID);
        $mapper->add_version_tag();

        $ids = $mapper->get_objects_for_removal();
        $this->assertContains($post2->ID, $ids);
        $this->assertContains($post3->ID, $ids);
        $this->assertNotContains($post1->ID, $ids);
        $this->assertNotContains($post4->ID, $ids);
        $this->assertNotContains($post5->ID, $ids);
    }

    public function testDelete()
    {
        $post1 = $this->factory()->post->create_and_get();
        $post2 = $this->factory()->post->create_and_get();

        $importer_model = $this->createMock(ImporterModel::class);
        $mapper = $this->createPartialMock(PostMapper::class, []);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $mapper->delete($post1->ID);

        $this->assertNull(get_post($post1->ID));
        $this->assertEquals($post2, get_post($post2->ID));
    }

    /**
     * @dataProvider provideSortFieldsData
     */
    public function testSortFields($expected_post, $expected_meta, $fields)
    {
        $mapper = $this->createPartialMock(PostMapper::class, []);
        $post = [];
        $meta = [];

        $mapper->sortFields($fields, $post, $meta);
        $this->assertArraySubset($expected_post, $post);
        $this->assertArraySubset($expected_meta, $meta);
    }

    public function provideSortFieldsData()
    {
        $fields = [
            'ID' => 'ABC',
            '_ID' => '',
            'TEST' => '',
            'menu_order' => 'ABC',
            'comment_status' => 'ABC',
            'ping_status' => 'ABC',
            'pinged' => 'ABC',
            'post_author' => 'ABC',
            'post_category' => 'ABC',
            'post_content' => 'ABC',
            'post_date' => 'ABC',
            'post_date_gmt' => 'ABC',
            'post_excerpt' => 'ABC',
            'post_name' => 'ABC',
            'post_parent' => 'ABC',
            'post_password' => 'ABC',
            'post_status' => 'ABC',
            'post_title' => 'ABC',
            'post_type' => 'ABC',
            'tags_input' => 'ABC',
            'to_ping' => 'ABC',
            'tax_input' => 'ABC',
        ];
        return [
            'Post and Custom Fields' => [
                [
                    'ID' => 'ABC',
                    'menu_order' => 'ABC',
                    'comment_status' => 'ABC',
                    'ping_status' => 'ABC',
                    'pinged' => 'ABC',
                    'post_author' => 'ABC',
                    'post_category' => 'ABC',
                    'post_content' => 'ABC',
                    'post_date' => 'ABC',
                    'post_date_gmt' => 'ABC',
                    'post_excerpt' => 'ABC',
                    'post_name' => 'ABC',
                    'post_parent' => 'ABC',
                    'post_password' => 'ABC',
                    'post_status' => 'ABC',
                    'post_title' => 'ABC',
                    'post_type' => 'ABC',
                    'tags_input' => 'ABC',
                    'to_ping' => 'ABC',
                    'tax_input' => 'ABC',
                ],
                [
                    '_ID' => '',
                    'TEST' => '',
                ],
                $fields
            ]
        ];
    }

    public function testUpdateCustomField()
    {
        $existing_post = $this->factory()->post->create_and_get();
        $mapper = $this->createPartialMock(PostMapper::class, []);

        $this->assertEmpty(get_post_meta($existing_post->ID, '_post_meta', true));

        $mapper->update_custom_field($existing_post->ID, '_post_meta', 'yes');
        $this->assertEquals('yes', get_post_meta($existing_post->ID, '_post_meta', true));

        $mapper->update_custom_field($existing_post->ID, '_post_meta', 'no');
        $this->assertEquals('no', get_post_meta($existing_post->ID, '_post_meta', true));

        $mapper->update_custom_field($existing_post->ID, '_post_meta', '');
        $this->assertEquals('', get_post_meta($existing_post->ID, '_post_meta', true));
    }

    public function testAddVersionTag()
    {
        $mock_post = $this->factory()->post->create_and_get();
        $status_id = 'ABC123';
        $importer_id = 999;

        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);

        $mapper = $this->createPartialMock(PostMapper::class, []);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);
        $this->setProtectedProperty($mapper, 'ID', $mock_post->ID);

        $this->assertEmpty(get_post_meta($mock_post->ID, '_iwp_session_' . $importer_id, true));
        $mapper->add_version_tag();
        $this->assertEquals($status_id, get_post_meta($mock_post->ID, '_iwp_session_' . $importer_id, true));
    }
}
