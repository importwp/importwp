<?php

namespace ImportWPTests\Common\Importer\File;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\JSONFile;

/**
 * @group File
 * @group Core
 */
class JSONFileTest extends \WP_UnitTestCase
{
    public function test_basic_record_count_and_path_list()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/basic.json', $config);

        $paths = $file->get_path_list();
        $this->assertArrayHasKey('data', $paths);
        $this->assertEquals(5, $paths['data']);

        $file->setRecordPath('data');
        $this->assertEquals(5, $file->getRecordCount());

        $record = json_decode($file->getRecord(0), true);
        $this->assertEquals(1, $record['id']);
        $this->assertEquals('Post One', $record['name']);
    }

    public function test_root_array()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/root-array.json', $config);

        $paths = $file->get_path_list();
        $this->assertArrayHasKey('/', $paths);
        $this->assertEquals(2, $paths['/']);

        $file->setRecordPath('/');
        $this->assertEquals(2, $file->getRecordCount());

        $record = json_decode($file->getRecord(1), true);
        $this->assertEquals('Root Two', $record['name']);
    }

    public function test_nested_path()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/nested.json', $config);

        $paths = $file->get_path_list();
        $this->assertArrayHasKey('results/items', $paths);
        $this->assertEquals(2, $paths['results/items']);

        $file->setRecordPath('results/items');
        $this->assertEquals(2, $file->getRecordCount());

        $record = json_decode($file->getRecord(0), true);
        $this->assertEquals(10, $record['id']);
        $this->assertEquals('Alice', $record['author']['name']);
    }

    public function test_index_cache_key_varies_by_path()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/basic.json', $config);
        $file->setRecordPath('data');
        $this->assertEquals('file_index-data', $file->getFileIndexKey());

        $file->setRecordPath('/');
        $this->assertEquals('file_index-root', $file->getFileIndexKey());
    }
}
