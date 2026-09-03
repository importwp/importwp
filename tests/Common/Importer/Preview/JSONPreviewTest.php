<?php

namespace ImportWPTests\Common\Importer\Preview;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\JSONFile;
use ImportWP\Common\Importer\Preview\JSONPreview;

/**
 * @group Preview
 * @group Core
 */
class JSONPreviewTest extends \WP_UnitTestCase
{
    public function test_preview_data_tree()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/basic.json', $config);
        $preview = new JSONPreview($file, 'data');

        $data = $preview->data();
        $this->assertEquals('record', $data[0]['node']);
        $this->assertEmpty($data[0]['xpath']);
        $this->assertNotEmpty($data[0]['value']);

        $nodes = [];
        foreach ($data[0]['value'] as $child) {
            $nodes[$child['node']] = $child;
        }

        $this->assertArrayHasKey('id', $nodes);
        $this->assertEquals('/id', $nodes['id']['xpath']);
        $this->assertEquals('1', $nodes['id']['value']);
        $this->assertEquals('Post One', $nodes['name']['value']);
    }

    public function test_nested_preview_paths()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/nested.json', $config);
        $preview = new JSONPreview($file, 'results/items');

        $data = $preview->data();
        $nodes = [];
        foreach ($data[0]['value'] as $child) {
            $nodes[$child['node']] = $child;
        }

        $this->assertArrayHasKey('author', $nodes);
        $this->assertEquals('/author', $nodes['author']['xpath']);
        $this->assertIsArray($nodes['author']['value']);
        $this->assertEquals('Alice', $nodes['author']['value'][0]['value']);
        $this->assertEquals('/author/name', $nodes['author']['value'][0]['xpath']);
    }
}
