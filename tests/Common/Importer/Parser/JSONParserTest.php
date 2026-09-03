<?php

namespace ImportWPTests\Common\Importer\Parser;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\JSONFile;
use ImportWP\Common\Importer\Parser\JSONParser;

/**
 * @group Parser
 * @group Core
 */
class JSONParserTest extends \WP_UnitTestCase
{
    public function test_flat_and_nested_queries()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/nested.json', $config);
        $file->setRecordPath('results/items');

        $parser = new JSONParser($file);
        $parser->getRecord(0);

        $this->assertEquals('10', $parser->query('/id'));
        $this->assertEquals('Nested One', $parser->query('/name'));
        $this->assertEquals('Alice', $parser->query('/author/name'));

        // Legacy flat key
        $this->assertEquals('Nested One', $parser->query('name'));
    }

    public function test_query_group_without_base()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/basic.json', $config);
        $file->setRecordPath('data');

        $parser = new JSONParser($file);
        $result = $parser->getRecord(0)->queryGroup([
            'fields' => [
                'post_title' => '{/name}',
                'post_content' => '{/content}',
            ],
        ]);

        $this->assertEquals('Post One', $result['post_title']);
        $this->assertEquals("Post One's Content", $result['post_content']);
    }

    public function test_query_group_with_base()
    {
        $config = new Config(tempnam(sys_get_temp_dir(), 'json-config'));
        $file = new JSONFile(IWP_TEST_ROOT . '/data/json/nested.json', $config);
        $file->setRecordPath('results/items');

        $parser = new JSONParser($file);
        $result = $parser->getRecord(0)->queryGroup([
            'base' => '/meta',
            'fields' => [
                'key' => '{/key}',
                'value' => '{/value}',
            ],
        ]);

        $this->assertCount(2, $result);
        $this->assertEquals('color', $result[0]['key']);
        $this->assertEquals('blue', $result[0]['value']);
        $this->assertEquals('size', $result[1]['key']);
        $this->assertEquals('large', $result[1]['value']);
    }
}
