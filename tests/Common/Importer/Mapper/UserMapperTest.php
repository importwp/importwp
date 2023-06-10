<?php

namespace ImportWPTests\Common\Importer\Mapper;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Mapper\UserMapper;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\UserTemplate;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPTests\Utils\ProtectedPropertyTrait;

/**
 * @group Mapper
 * @group Core
 */
class UserMapperTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;
    use ProtectedPropertyTrait;

    /**
     * @dataProvider provideExistsData
     */
    public function testExists($expected, \Closure $setData)
    {
        $mock_user = [
            'user_pass' => 'password',
            'user_login' => 'user_one',
            'user_nicename' => 'user-one',
            'user_url' => 'http://www.google.com',
            'user_email' => 'user@one.com',
            'display_name' => 'User One',
            'nickname' => 'User Nickname',
            'first_name' => 'User',
            'last_name' => 'One',
            'description' => 'Test User',
            'rich_editing' => 'yes',
            'user_registered' => '2020-01-16 08:03:00',
            'role' => 'administrator',
        ];

        $user = $this->factory()->user->create_and_get($mock_user);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $parsed_data = new ParsedData($mapper);
        $setData($parsed_data, $user);

        $result = $mapper->exists($parsed_data);
        if ($expected === false) {
            $this->assertFalse($result);
        } else {
            $this->assertGreaterThan(0, $result);
        }
    }

    public function provideExistsData()
    {
        return [
            'User Login' => [
                true,
                function (ParsedData $data, \WP_User $user) {
                    $data->update([
                        'user_login' => $user->user_login
                    ]);
                }
            ],
            'User Email' => [
                true,
                function (ParsedData $data, \WP_User $user) {
                    $data->update([
                        'user_email' => $user->user_email
                    ]);
                }
            ],
            'User Email and Login, email has higher priority of both fields present' => [
                true,
                function (ParsedData $data, \WP_User $user) {
                    $data->update([
                        'user_email' => $user->user_email,
                        'user_login' => 'ASD'
                    ]);
                }
            ],
            'User Login and Email, email has higher priority of both fields present' => [
                false,
                function (ParsedData $data, \WP_User $user) {
                    $data->update([
                        'user_email' => 'ASD',
                        'user_login' => $user->user_login
                    ]);
                }
            ],
        ];
    }

    public function testExistsEmptyData()
    {

        $user = $this->factory()->user->create_and_get();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $parsed_data = new ParsedData($mapper);
        $parsed_data->update([]);

        $this->expectException(MapperException::class);
        $result = $mapper->exists($parsed_data);
    }

    public function testInsert()
    {
        $mock_user = [
            'user_pass' => 'password',
            'user_login' => 'user_one',
            'user_nicename' => 'user-one',
            'user_url' => 'http://www.google.com',
            'user_email' => 'user@one.com',
            'display_name' => 'User One',
            'nickname' => 'User Nickname',
            'first_name' => 'User',
            'last_name' => 'One',
            'description' => 'Test User',
            'rich_editing' => 'yes',
            'user_registered' => '2020-01-16 08:03:00',
            'role' => 'administrator',
        ];

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $template = new UserTemplate($this->createPartialMock(EventHandler::class, []));
        $this->setProtectedProperty($template, 'importer', $importer_model);
        $this->setProtectedProperty($mapper, 'template', $template);

        $parsed_data = new ParsedData($mapper);
        $parsed_data->update($mock_user);

        $id = $mapper->insert($parsed_data);

        $record = get_user_by('ID', $id);

        $this->assertArraySubset([
            'user_nicename' => $mock_user['user_nicename'],
            'user_url' => $mock_user['user_url'],
            'user_email' => $mock_user['user_email'],
            'display_name' => $mock_user['display_name'],
        ], $record->to_array());

        $this->assertEquals($mock_user['nickname'], get_user_meta($id, 'nickname', true));
        $this->assertEquals($mock_user['first_name'], get_user_meta($id, 'first_name', true));
        $this->assertEquals($mock_user['last_name'], get_user_meta($id, 'last_name', true));
        $this->assertEquals($mock_user['description'], get_user_meta($id, 'description', true));
        $this->assertEquals($mock_user['rich_editing'], get_user_meta($id, 'rich_editing', true));
        $this->assertEquals([$mock_user['role']], $record->roles);
    }

    public function testUpdateEmpty()
    {
        $mock_user = [
            'user_pass' => 'password',
            'user_login' => 'user_one',
            'user_nicename' => 'user-one',
            'user_url' => 'http://www.google.com',
            'user_email' => 'user@one.com',
            'display_name' => 'User One',
            'nickname' => 'User Nickname',
            'first_name' => 'User',
            'last_name' => 'One',
            'description' => 'Test User',
            'rich_editing' => 'yes',
            'user_registered' => '2020-01-16 08:03:00',
            'role' => 'administrator',
        ];

        $mock_updated_user = [];

        $existing_users = [];
        $existing_users[] = $this->factory()->user->create_and_get($mock_user);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $template = new UserTemplate($this->createPartialMock(EventHandler::class, []));
        $this->setProtectedProperty($template, 'importer', $importer_model);
        $this->setProtectedProperty($mapper, 'template', $template);

        $parsed_data = new ParsedData($mapper);
        $parsed_data->update($mock_updated_user);

        $id = $existing_users[0]->ID;
        $this->setProtectedProperty($mapper, 'ID', $id);
        $mapper->update($parsed_data);

        $record = get_user_by('ID', $id);

        $this->assertArraySubset([
            'user_nicename' => $mock_user['user_nicename'],
            'user_url' => $mock_user['user_url'],
            'user_email' => $mock_user['user_email'],
            'display_name' => $mock_user['display_name'],
        ], $record->to_array());

        $this->assertEquals($mock_user['nickname'], get_user_meta($id, 'nickname', true));
        $this->assertEquals($mock_user['first_name'], get_user_meta($id, 'first_name', true));
        $this->assertEquals($mock_user['last_name'], get_user_meta($id, 'last_name', true));
        $this->assertEquals($mock_user['description'], get_user_meta($id, 'description', true));
        $this->assertEquals($mock_user['rich_editing'], get_user_meta($id, 'rich_editing', true));
        $this->assertEquals([$mock_user['role']], $record->roles);
    }

    public function testUpdateCore()
    {
        $mock_user = [
            'user_pass' => 'password',
            'user_login' => 'user_one',
            'user_nicename' => 'user-one',
            'user_url' => 'http://www.google.com',
            'user_email' => 'user@one.com',
            'display_name' => 'User One',
            'nickname' => 'User Nickname',
            'first_name' => 'User',
            'last_name' => 'One',
            'description' => 'Test User',
            'rich_editing' => 'yes',
            'user_registered' => '2020-01-16 08:03:00',
            'role' => 'administrator',
        ];

        $mock_updated_user = [
            // 'user_pass' => 'passwordupdated', // cant test due to hashing
            // 'user_login' => 'user_oneupdated', // cant update login 
            // 'user_registered' => '2020-01-15 08:03:00', // cant update registered date
            'user_nicename' => 'user-oneupdated',
            'user_url' => 'http://www.google.com/updated',
            'user_email' => 'updated@one.com',
            'display_name' => 'User One updated',
            'nickname' => 'User Nickname updated',
            'first_name' => 'Userupdated',
            'last_name' => 'Oneupdated',
            'description' => 'Test User updated',
            'rich_editing' => 'no',
            'role' => 'subscriber',
        ];

        $existing_users = [];
        $existing_users[] = $this->factory()->user->create_and_get($mock_user);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $template = new UserTemplate($this->createPartialMock(EventHandler::class, []));
        $this->setProtectedProperty($template, 'importer', $importer_model);
        $this->setProtectedProperty($mapper, 'template', $template);

        $parsed_data = new ParsedData($mapper);
        $parsed_data->update($mock_updated_user);

        $id = $existing_users[0]->ID;
        $this->setProtectedProperty($mapper, 'ID', $id);
        $mapper->update($parsed_data);

        $record = get_user_by('ID', $id);

        $this->assertArraySubset([
            'user_nicename' => $mock_updated_user['user_nicename'],
            'user_url' => $mock_updated_user['user_url'],
            'user_email' => $mock_updated_user['user_email'],
            'display_name' => $mock_updated_user['display_name'],
        ], $record->to_array());

        $this->assertEquals($mock_updated_user['nickname'], get_user_meta($id, 'nickname', true));
        $this->assertEquals($mock_updated_user['first_name'], get_user_meta($id, 'first_name', true));
        $this->assertEquals($mock_updated_user['last_name'], get_user_meta($id, 'last_name', true));
        $this->assertEquals($mock_updated_user['description'], get_user_meta($id, 'description', true));
        $this->assertEquals($mock_updated_user['rich_editing'], get_user_meta($id, 'rich_editing', true));
        $this->assertEquals([$mock_updated_user['role']], $record->roles);
    }

    public function testGetObjectsForRemoval()
    {
        $user1 = $this->factory()->user->create_and_get();
        $user2 = $this->factory()->user->create_and_get();
        $user3 = $this->factory()->user->create_and_get();
        $user4 = $this->factory()->user->create_and_get();
        $user5 = $this->factory()->user->create_and_get();

        $status_id = 'ABC123';
        $importer_id = 999;

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);


        $this->setProtectedProperty($mapper, 'ID', $user2->ID);
        $mapper->add_version_tag();
        $this->setProtectedProperty($mapper, 'ID', $user3->ID);
        $mapper->add_version_tag();

        $status_id = 'ABC456';
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $this->setProtectedProperty($mapper, 'ID', $user1->ID);
        $mapper->add_version_tag();
        $this->setProtectedProperty($mapper, 'ID', $user4->ID);
        $mapper->add_version_tag();

        $ids = $mapper->get_objects_for_removal();
        $this->assertContains($user2->ID, $ids);
        $this->assertContains($user3->ID, $ids);
        $this->assertNotContains($user1->ID, $ids);
        $this->assertNotContains($user4->ID, $ids);
        $this->assertNotContains($user5->ID, $ids);
    }

    public function testDelete()
    {
        $user1 = $this->factory()->user->create_and_get();
        $user2 = $this->factory()->user->create_and_get();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $mapper->delete($user1->ID);

        $this->assertFalse(get_user_by('ID', $user1->ID));
        $this->assertEquals($user2, get_user_by('ID', $user2->ID));
    }

    public function testGetCustomField()
    {
        // TODO: write get_custom_field tests
        $existing_user = $this->factory()->user->create_and_get();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $this->assertEmpty($mapper->get_custom_field($existing_user->ID, 'test_meta', true));

        update_user_meta($existing_user->ID, 'test_meta', 'yes');

        $this->assertEquals('yes', $mapper->get_custom_field($existing_user->ID, 'test_meta', true));
    }

    public function testUpdateCustomField()
    {
        $existing_user = $this->factory()->user->create_and_get();
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);

        $this->assertEmpty(get_user_meta($existing_user->ID, '_user_meta', true));

        $mapper->update_custom_field($existing_user->ID, '_user_meta', 'yes');
        $this->assertEquals('yes', get_user_meta($existing_user->ID, '_user_meta', true));

        $mapper->update_custom_field($existing_user->ID, '_user_meta', 'no');
        $this->assertEquals('no', get_user_meta($existing_user->ID, '_user_meta', true));

        $mapper->update_custom_field($existing_user->ID, '_user_meta', '');
        $this->assertEquals('', get_user_meta($existing_user->ID, '_user_meta', true));
    }

    public function testAddVersionTag()
    {
        $mock_user = $this->factory()->user->create_and_get();
        $status_id = 'ABC123';
        $importer_id = 999;

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | UserMapper
         */
        $mapper = $this->createPartialMock(UserMapper::class, []);

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getId')->willReturn($importer_id);
        $importer_model->method('getStatusId')->willReturn($status_id);
        $importer_model->method('getSetting')->willReturn(true);
        $this->setProtectedProperty($mapper, 'importer', $importer_model);
        $this->setProtectedProperty($mapper, 'ID', $mock_user->ID);

        $this->assertEmpty(get_user_meta($mock_user->ID, '_iwp_session_' . $importer_id, true));
        $mapper->add_version_tag();
        $this->assertEquals($status_id, get_user_meta($mock_user->ID, '_iwp_session_' . $importer_id, true));
    }
}
