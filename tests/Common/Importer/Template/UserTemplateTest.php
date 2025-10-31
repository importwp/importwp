<?php

namespace ImportWPTests\Common\Importer\Template;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\UserTemplate;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPTests\Utils\MockDataTrait;

/**
 * @group Template
 * @group Core
 */
class UserTemplateTest extends \WP_UnitTestCase
{
    use ArraySubsetAsserts;
    use MockDataTrait;

    public function testRegister()
    {
        $template = new UserTemplate(new EventHandler());
        $fields = $template->register();

        $expected = [
            [
                'id' => 'user',
                'type' => 'group',
                'fields' => [
                    [
                        'id' => 'ID',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'user_login',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'user_email',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'first_name',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'last_name',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'role',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'user_url',
                        'type' => 'field'
                    ],
                    [
                        'id' => 'user_pass',
                        'type' => 'field'
                    ],
                ]
            ]
        ];

        $this->assertArraySubset($expected, $fields);
    }

    public function testRegisterSettings()
    {
        $template = new UserTemplate(new EventHandler());
        $fields = $template->register_settings();
        $expected = [
            [
                'id' => 'notify_users',
                'type' => 'checkbox'
            ],
            [
                'id' => 'generate_pass',
                'type' => 'checkbox'
            ]
        ];
        $this->assertArraySubset($expected, $fields);
    }

    public function testPreProcess()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($field) {
            return false;
        }));

        $mapper = $this->create_user_mapper();
        $template = new UserTemplate(new EventHandler());
        $template->register_hooks($importer_model);
        $parsed_data = new ParsedData($mapper);
        $parsed_data->add([
            'user.user_pass' => $this->mock_data['user']['user_pass'],
            'user.user_login' => $this->mock_data['user']['user_login'],
            'user.user_nicename' => $this->mock_data['user']['user_nicename'],
            'user.user_url' => $this->mock_data['user']['user_url'],
            'user.user_email' => $this->mock_data['user']['user_email'],
            'user.display_name' => $this->mock_data['user']['display_name'],
            'user.nickname' => $this->mock_data['user']['nickname'],
            'user.first_name' => $this->mock_data['user']['first_name'],
            'user.last_name' => $this->mock_data['user']['last_name'],
            'user.description' => $this->mock_data['user']['description'],
            'user.rich_editing' => $this->mock_data['user']['rich_editing'],
            'user.user_registered' => $this->mock_data['user']['user_registered'],
            'user.role' => $this->mock_data['user']['role'],
            'user.jabber' => $this->mock_data['user']['jabber'],
            'user.aim' => $this->mock_data['user']['aim'],
            'user.yim' => $this->mock_data['user']['yim'],
        ]);

        $data = $template->pre_process($parsed_data);
        $this->assertEquals([
            // 'user_pass' => $this->mock_data['user']['user_pass'],
            'user_login' => $this->mock_data['user']['user_login'],
            // 'user_nicename' => $this->mock_data['user']['user_nicename'],
            // 'user_url' => $this->mock_data['user']['user_url'],
            'user_email' => $this->mock_data['user']['user_email'],
            // 'display_name' => $this->mock_data['user']['display_name'],
            'nickname' => $this->mock_data['user']['nickname'],
            'first_name' => $this->mock_data['user']['first_name'],
            'last_name' => $this->mock_data['user']['last_name'],
            // 'description' => $this->mock_data['user']['description'],
            'rich_editing' => $this->mock_data['user']['rich_editing'],
            'user_registered' => $this->mock_data['user']['user_registered'],
            // 'role' => $this->mock_data['user']['role'],
            'jabber' => $this->mock_data['user']['jabber'],
            'aim' => $this->mock_data['user']['aim'],
            'yim' => $this->mock_data['user']['yim'],
        ], $data->getData());
    }

    /**
     * @dataProvider providePreProcessIsFieldEnabledData
     */
    public function testPreProcessIsFieldEnabled($enabled, $field)
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('isEnabledField')->will($this->returnCallback(function ($name) use ($enabled, $field) {
            if ($name === 'user.' . $field) {
                return $enabled;
            }
            return false;
        }));

        $mapper = $this->create_user_mapper();
        $template = new UserTemplate(new EventHandler());
        $template->register_hooks($importer_model);
        $parsed_data = new ParsedData($mapper);
        $parsed_data->add([
            'user.user_pass' => $this->mock_data['user']['user_pass'],
            'user.user_login' => $this->mock_data['user']['user_login'],
            'user.user_nicename' => $this->mock_data['user']['user_nicename'],
            'user.user_url' => $this->mock_data['user']['user_url'],
            'user.user_email' => $this->mock_data['user']['user_email'],
            'user.display_name' => $this->mock_data['user']['display_name'],
            'user.nickname' => $this->mock_data['user']['nickname'],
            'user.first_name' => $this->mock_data['user']['first_name'],
            'user.last_name' => $this->mock_data['user']['last_name'],
            'user.description' => $this->mock_data['user']['description'],
            'user.rich_editing' => $this->mock_data['user']['rich_editing'],
            'user.user_registered' => $this->mock_data['user']['user_registered'],
            'user.role' => $this->mock_data['user']['role'],
            'user.jabber' => $this->mock_data['user']['jabber'],
            'user.aim' => $this->mock_data['user']['aim'],
            'user.yim' => $this->mock_data['user']['yim'],
        ]);

        $expected = [
            'user_pass' => $this->mock_data['user']['user_pass'],
            'user_login' => $this->mock_data['user']['user_login'],
            // 'user_nicename' => $this->mock_data['user']['user_nicename'],
            'user_url' => $this->mock_data['user']['user_url'],
            'user_email' => $this->mock_data['user']['user_email'],
            // 'display_name' => $this->mock_data['user']['display_name'],
            'nickname' => $this->mock_data['user']['nickname'],
            'first_name' => $this->mock_data['user']['first_name'],
            'last_name' => $this->mock_data['user']['last_name'],
            // 'description' => $this->mock_data['user']['description'],
            'rich_editing' => $this->mock_data['user']['rich_editing'],
            'user_registered' => $this->mock_data['user']['user_registered'],
            // 'role' => $this->mock_data['user']['role'],
            'jabber' => $this->mock_data['user']['jabber'],
            'aim' => $this->mock_data['user']['aim'],
            'yim' => $this->mock_data['user']['yim'],
        ];

        foreach (['user_url', 'user_pass'] as $field_name) {
            if ($field === $field_name && $enabled === true) {
                continue;
            }

            unset($expected[$field_name]);
        }

        $data = $template->pre_process($parsed_data);
        $this->assertEquals($expected, $data->getData());
    }

    public function providePreProcessIsFieldEnabledData()
    {
        return [
            'user_url is enabled' => [
                true,
                'user_url',
            ],
            'user_url is disabled' => [
                false,
                'user_url',
            ],
            'user_pass is enabled' => [
                true,
                'user_pass',
            ],
            'user_pass is disabled' => [
                false,
                'user_pass',
            ],
        ];
    }

    public function testPreProcessPasswordGeneration()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($name) {
            if ('generate_pass' === $name) {
                return true;
            }
            return false;
        }));

        $mapper = $this->create_user_mapper();
        $template = new UserTemplate(new EventHandler());
        $template->register_hooks($importer_model);
        $parsed_data = new ParsedData($mapper);
        $parsed_data->add([
            'user.user_login' => $this->mock_data['user']['user_login'],
            'user.user_nicename' => $this->mock_data['user']['user_nicename'],
            'user.user_email' => $this->mock_data['user']['user_email'],
            'user.display_name' => $this->mock_data['user']['display_name'],
            'user.nickname' => $this->mock_data['user']['nickname'],
            'user.first_name' => $this->mock_data['user']['first_name'],
            'user.last_name' => $this->mock_data['user']['last_name'],
            'user.description' => $this->mock_data['user']['description'],
            'user.rich_editing' => $this->mock_data['user']['rich_editing'],
            'user.user_registered' => $this->mock_data['user']['user_registered'],
            'user.role' => $this->mock_data['user']['role'],
            'user.jabber' => $this->mock_data['user']['jabber'],
            'user.aim' => $this->mock_data['user']['aim'],
            'user.yim' => $this->mock_data['user']['yim'],
        ]);

        $data = $template->pre_process($parsed_data);
        $this->assertNotEmpty($data->getValue('user_pass'));
    }

    public function testPostProcessNotificationEmailEnabled()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($name) {
            if ('notify_users' === $name) {
                return true;
            }
            return false;
        }));

        $mapper = $this->create_user_mapper();
        $template = new UserTemplate(new EventHandler());
        $template->register_hooks($importer_model);
        $parsed_data = new ParsedData($mapper);
        $parsed_data->add([
            'user.user_login' => $this->mock_data['user']['user_login'],
            'user.user_nicename' => $this->mock_data['user']['user_nicename'],
            'user.user_email' => $this->mock_data['user']['user_email'],
            'user.display_name' => $this->mock_data['user']['display_name'],
            'user.nickname' => $this->mock_data['user']['nickname'],
            'user.first_name' => $this->mock_data['user']['first_name'],
            'user.last_name' => $this->mock_data['user']['last_name'],
            'user.description' => $this->mock_data['user']['description'],
            'user.rich_editing' => $this->mock_data['user']['rich_editing'],
            'user.user_registered' => $this->mock_data['user']['user_registered'],
            'user.role' => $this->mock_data['user']['role'],
            'user.jabber' => $this->mock_data['user']['jabber'],
            'user.aim' => $this->mock_data['user']['aim'],
            'user.yim' => $this->mock_data['user']['yim'],
        ]);

        $user_id = $this->factory()->user->create();

        $data = $template->post_process($user_id, $this->createMock(ParsedData::class));
        // $this->assertNotEmpty($data->getValue('user_pass'));

        $email = tests_retrieve_phpmailer_instance()->get_sent();
        $this->assertNotFalse($email);
        reset_phpmailer_instance();
    }


    public function testPostProcessNotificationEmailDisabled()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | ImporterModel
         */
        $importer_model = $this->createMock(ImporterModel::class);
        $importer_model->method('getSetting')->will($this->returnCallback(function ($name) {
            if ('notify_users' === $name) {
                return false;
            }
            return false;
        }));

        $mapper = $this->create_user_mapper();
        $template = new UserTemplate(new EventHandler());
        $template->register_hooks($importer_model);
        $parsed_data = new ParsedData($mapper);
        $parsed_data->add([
            'user.user_login' => $this->mock_data['user']['user_login'],
            'user.user_nicename' => $this->mock_data['user']['user_nicename'],
            'user.user_email' => $this->mock_data['user']['user_email'],
            'user.display_name' => $this->mock_data['user']['display_name'],
            'user.nickname' => $this->mock_data['user']['nickname'],
            'user.first_name' => $this->mock_data['user']['first_name'],
            'user.last_name' => $this->mock_data['user']['last_name'],
            'user.description' => $this->mock_data['user']['description'],
            'user.rich_editing' => $this->mock_data['user']['rich_editing'],
            'user.user_registered' => $this->mock_data['user']['user_registered'],
            'user.role' => $this->mock_data['user']['role'],
            'user.jabber' => $this->mock_data['user']['jabber'],
            'user.aim' => $this->mock_data['user']['aim'],
            'user.yim' => $this->mock_data['user']['yim'],
        ]);

        $user_id = $this->factory()->user->create();

        $data = $template->post_process($user_id, $this->createMock(ParsedData::class));

        $email = tests_retrieve_phpmailer_instance()->get_sent();
        $this->assertFalse($email);
        reset_phpmailer_instance();
    }
}
