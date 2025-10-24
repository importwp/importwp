<?php

namespace ImportWPTests\Common\Importer;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Importer\State\ImporterState;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Container;
use ImportWP\EventHandler;

class ImporterManagerTest extends \WP_UnitTestCase
{
    public function whitelistTestDir($paths)
    {
        $paths[] = realpath(IWP_TEST_ROOT);
        return $paths;
    }

    public function testGetImporter()
    {
        /**
         * @var ImporterManager $manager
         */
        $manager = Container::getInstance()->get('importer_manager');

        $new_importer = new ImporterModel([
            'name' => 'Test Importer'
        ]);
        $importer_id = $new_importer->save();

        $this->assertInstanceOf(ImporterModel::class, $manager->get_importer($importer_id));
        $this->assertInstanceOf(ImporterModel::class, $manager->get_importer($new_importer));

        wp_delete_post($importer_id, true);
    }

    public function testCustomTemplateRegisterHook()
    {
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | Filesystem
         */
        $mock_filesystem = $this->createMock(Filesystem::class);
        /**
         * @var \PHPUnit\Framework\MockObject\MockObject | TemplateManager
         */
        $template_manager = $this->createMock(TemplateManager::class);
        $event_handler = new EventHandler();

        $importer_manager = new ImporterManager($mock_filesystem, $template_manager, $event_handler);

        $templates = $importer_manager->get_templates();
        $this->assertTrue(!isset($templates['test_class']));

        $event_handler->listen('templates.register', function ($templates) {
            $templates['test_class'] = 'test';
            return $templates;
        });

        $templates = $importer_manager->get_templates();
        $this->assertTrue(isset($templates['test_class']));
    }

    public function testPreviewCSVFile()
    {
        /**
         * @var ImporterManager $manager
         */
        $manager = Container::getInstance()->get('importer_manager');

        // 1. Create Importer
        $new_importer = new ImporterModel([
            'name' => 'Test Importer'
        ]);
        $importer_id = $new_importer->save();

        add_filter('iwp/importer/local_file/allowed_directories', 'whitelistTestDir');
        $attachment_id = $manager->local_file($new_importer, IWP_TEST_ROOT . "/data/csv/test.csv");
        remove_filter('iwp/importer/local_file/allowed_directories', 'whitelistTestDir');

        $this->assertGreaterThan(0, $attachment_id);
        $this->assertEquals('csv', $new_importer->getParser());
        $this->assertEquals('"', $new_importer->getFileSetting('enclosure'));
        $this->assertEquals(',', $new_importer->getFileSetting('delimiter'));

        $manager->clear_config_files($new_importer->getId());

        $preview_map = [
            'field_1' => '{0}',
            'field_2' => '{1}',
            'field_3' => '{2}',
        ];

        // row 0
        $this->assertEquals([
            'field_1' => 'One',
            'field_2' => 'Two',
            'field_3' => 'Three',
        ], $manager->preview_csv_file($new_importer, $preview_map));

        // row 1
        $this->assertEquals([
            'field_1' => '1',
            'field_2' => '2',
            'field_3' => '3',
        ], $manager->preview_csv_file($new_importer, $preview_map, 1));

        // cleanup
        wp_delete_post($importer_id, true);
        wp_delete_attachment($attachment_id, true);
    }

    public function testPreviewXMLFile()
    {
        /**
         * @var ImporterManager $manager
         */
        $manager = Container::getInstance()->get('importer_manager');

        // 1. Create Importer
        $new_importer = new ImporterModel([
            'name' => 'Test Importer'
        ]);
        $importer_id = $new_importer->save();

        add_filter('iwp/importer/local_file/allowed_directories', 'whitelistTestDir');
        $attachment_id = $manager->local_file($new_importer, IWP_TEST_ROOT . "/data/xml/simple.xml");
        remove_filter('iwp/importer/local_file/allowed_directories', 'whitelistTestDir');

        $this->assertGreaterThan(0, $attachment_id);
        $this->assertEquals('xml', $new_importer->getParser());
        $this->assertNull($new_importer->getFileSetting('base_path'));

        $manager->clear_config_files($new_importer->getId());

        $new_importer->setFileSetting('base_path', 'posts/post');
        $new_importer->save();

        $preview_map = [
            'field_1' => '{/name}',
            'field_2' => '{/content}',
        ];

        // row 0
        $this->assertEquals([
            'field_1' => 'Post One',
            'field_2' => 'Post One content',
        ], $manager->preview_xml_file($new_importer->getId(), $preview_map));

        // // row 1
        $this->assertEquals([
            'field_1' => 'Post Two',
            'field_2' => 'Post Two content',
        ], $manager->preview_xml_file($new_importer->getId(), $preview_map, 1));

        // // row 1
        $this->assertEquals([
            'field_1' => 'Post Five',
            'field_2' => 'Post Five content',
        ], $manager->preview_xml_file($new_importer->getId(), $preview_map, 4));

        // cleanup
        wp_delete_post($importer_id, true);
        wp_delete_attachment($attachment_id, true);
    }

    public function testImport()
    {

        /**
         * @var ImporterManager $manager
         */
        $manager = Container::getInstance()->get('importer_manager');

        // 1. Create Importer
        $new_importer = new ImporterModel([
            'name' => 'Test Importer'
        ]);
        $new_importer->setTemplate('post');
        $new_importer->setMap('post.post_title', '{/name}');
        $new_importer->setMap('post.post_content', '{/content}');
        $new_importer->save();

        add_filter('iwp/importer/local_file/allowed_directories', 'whitelistTestDir');
        $manager->local_file($new_importer, IWP_TEST_ROOT . "/data/xml/simple.xml");
        remove_filter('iwp/importer/local_file/allowed_directories', 'whitelistTestDir');

        $new_importer->setFileSetting('base_path', 'posts/post');
        $new_importer->save();

        // test update
        // wp_insert_post(['post_title' => 'Post One', 'post_content' => 'help', 'post_status' => 'publish']);
        $user = uniqid('wptest');
        $status = $manager->import($new_importer, $user);
        $this->assertEquals('complete', $status['status']);
    }

    public function testImportWithPermissions()
    {
        $migrations = new \ImportWP\Common\Migration\Migrations();
        $migrations->install();

        /**
         * @var ImporterManager $manager
         */
        $manager = Container::getInstance()->get('importer_manager');

        // 1. Create Importer
        $new_importer = new ImporterModel([
            'name' => 'Test Importer',
            'permissions' => [
                'create' => [
                    'enabled' => false,
                ],
                'update' => [
                    'enabled' => false,
                ],
                'remove' => [
                    'enabled' => false
                ]
            ]
        ]);
        $new_importer->setTemplate('post');
        $new_importer->setSetting('post_type', 'post');
        $new_importer->setMap('post.post_title', '{/name}');
        $new_importer->setMap('post.post_content', '{/content}');
        $new_importer->save();

        // Switch to original unique identifier
        $new_importer->setSetting('unique_identifier_type', '');
        $new_importer->save();

        add_filter('iwp/importer/local_file/allowed_directories', 'whitelistTestDir');
        $manager->local_file($new_importer, IWP_TEST_ROOT . "/data/xml/simple.xml");
        remove_filter('iwp/importer/local_file/allowed_directories', 'whitelistTestDir');

        $new_importer->setFileSetting('base_path', 'posts/post');
        $importer_id = $new_importer->save();

        // test update
        $user = uniqid('wptest');

        // NOTE: For some reason we need a sleep for this test to pass.
        sleep(1);

        $session = md5($new_importer->getId() . time());
        update_post_meta($new_importer->getId(), '_iwp_session', $session);
        $status = $manager->import($new_importer, $user, $session);
        $this->assertEquals([
            'errors' => 5,
            'inserts' => 0,
            'updates' => 0,
            'deletes' => 0,
            'skips' => 0
        ], $status['stats']);
        $this->assertEquals('complete', $status['status']);

        ImporterState::clear_options($importer_id);
        $new_importer = new ImporterModel($importer_id);


        // Test with insert
        $new_importer->setPermission('create', ['enabled' => true]);
        $new_importer->setPermission('remove', ['enabled' => true]);
        $new_importer->save();

        // NOTE: For some reason we need a sleep for this test to pass.
        sleep(1);

        // test update
        $session = md5($new_importer->getId() . time());
        update_post_meta($new_importer->getId(), '_iwp_session', $session);
        $status = $manager->import($new_importer, $user, $session);

        $this->assertEquals([
            'errors' => 0,
            'inserts' => 5,
            'updates' => 0,
            'deletes' => 0,
            'skips' => 0
        ], $status['stats']);
        $this->assertEquals('complete', $status['status']);

        ImporterState::clear_options($importer_id);

        // Test with update
        $new_importer->setPermission('update', ['enabled' => true]);
        $new_importer->save();

        // NOTE: For some reason we need a sleep for this test to pass.
        sleep(1);

        // test update
        $session = md5($new_importer->getId() . time());
        update_post_meta($new_importer->getId(), '_iwp_session', $session);
        $status = $manager->import($new_importer, $user, $session);
        $this->assertEquals([
            'errors' => 0,
            'inserts' => 0,
            'updates' => 5,
            'deletes' => 0,
            'skips' => 0
        ], $status['stats']);
        $this->assertEquals('complete', $status['status']);

        ImporterState::clear_options($importer_id);

        // Test with remove
        $new_importer->setPermission('create', ['enabled' => false]);
        $new_importer->setPermission('update', ['enabled' => false]);
        $new_importer->setPermission('remove', ['enabled' => true]);
        $new_importer->save();


        // NOTE: For some reason we need a sleep for this test to pass.
        sleep(1);

        // test update
        $session = md5($new_importer->getId() . time());
        update_post_meta($new_importer->getId(), '_iwp_session', $session);

        $new_importer = new ImporterModel($importer_id);
        $status = $manager->import($new_importer, $user, $session);
        $this->assertEquals([
            'errors' => 5,
            'inserts' => 0,
            'updates' => 0,
            'deletes' => 0,
            'skips' => 0
        ], $status['stats']);

        $this->assertEquals('running', $status['status']);

        $status = $manager->import($new_importer, $user, $session);
        $this->assertEquals([
            'errors' => 5,
            'inserts' => 0,
            'updates' => 0,
            'deletes' => 5,
            'skips' => 0
        ], $status['stats']);
        $this->assertEquals('complete', $status['status']);
    }
}
