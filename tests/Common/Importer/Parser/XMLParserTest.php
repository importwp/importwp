<?php

namespace ImportWPTests\Common\Importer\Parser;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\XMLFile;
use ImportWP\Common\Importer\Parser\XMLParser;

/**
 * @group Parser
 * @group Core
 */
class XMLParserTest extends \WP_UnitTestCase
{


    public function testQueryGroupNoBasePath()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $config      = new Config($config_file);

        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/wp-export.xml', $config);
        $file->setRecordPath('rss/channel/item');

        $parser = new XMLParser($file);

        $result = $parser->getRecord(0)->queryGroup([
            'fields' => [
                'post_name'    => '{/wp:post_name}',
                'post_title'   => '{/title}',
                'post_content' => '{/content:encoded}'
            ]
        ]);

        $this->assertEquals("post-one", $result['post_name']);
        $this->assertEquals("Post One", $result['post_title']);
    }

    /**
     * Run xpath query with a base, returning a list of data sets
     */
    public function testQueryGroupWithBasePath()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $config      = new Config($config_file);

        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/wp-export.xml', $config);
        $file->setRecordPath('rss/channel/item');

        $parser = new XMLParser($file);

        $result = $parser->getRecord(0)->queryGroup([
            'base'   => '/wp:postmeta',
            'fields' => [
                'iwpcf_key'   => '{/wp:meta_key}',
                'iwpcf_value' => '{/wp:meta_value}',
            ]
        ]);

        $this->assertEquals(6, count($result));
        foreach ($result as $data_set) {
            $this->assertArrayHasKey('iwpcf_key', $data_set);
            $this->assertArrayHasKey('iwpcf_value', $data_set);
            $this->assertNotEmpty($data_set['iwpcf_key']);
            $this->assertNotEmpty($data_set['iwpcf_value']);
        }
    }

    public function testNestedGroupQueries()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $config      = new Config($config_file);

        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/wp-export.xml', $config);
        $file->setRecordPath('rss/channel/item');

        $parser = new XMLParser($file);

        $result = [];
        $parser->getRecord(0);

        $postmeta_records = $parser->getSubRecords('/wp:postmeta');
        foreach ($postmeta_records as $meta) {
            $key = $parser->getString('/wp:meta_key', $meta);
            $value = $parser->getString('/wp:meta_value', $meta);
            $result[$key] = $value;
        }

        $this->assertEquals([
            '_yoast_wpseo_focuskw' => 'post-one-123',
            '_yoast_wpseo_title' => 'This is the post one\'s excerpt',
            '_yoast_wpseo_metadesc' => 'publish',
            '_jci_version_205' => '9',
            '_pingme' => '1',
            '_encloseme' => '1',
        ], $result);
    }

    public function testCustomMethod()
    {

        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $config      = new Config($config_file);

        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/wp-export.xml', $config);
        $file->setRecordPath('rss/channel/item');

        $parser = new XMLParser($file);

        $result = $parser->getRecord(0)->queryGroup([
            'fields' => [
                'one' => '[strtoupper("{/title}")]',
            ]
        ]);

        $this->assertEquals(strtoupper('Post One'), $result['one']);
    }

    public function testA10FetchingNamespaces()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $config      = new Config($config_file);

        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/a10.xml', $config);
        $file->setRecordPath('rss/channel/item');

        $parser = new XMLParser($file);

        $record = $parser->getRecord(0);

        $result = $record->queryGroup([
            'fields' => [
                'post_content' => '{/a10:content/Vacancy/Brand}',
            ]
        ]);

        $this->assertEquals('Default Brand', $result['post_content']);
    }

    public function testA10ListBasePath()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $config      = new Config($config_file);

        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/a10.xml', $config);
        $file->setRecordPath('rss/channel/item');
        $nodes = $file->get_node_list();

        $this->assertEquals('/rss/channel/item/a10:updated', $nodes[12]);
    }

    public function testCDATAInvalidFeed()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $config      = new Config($config_file);

        $file = new XMLFile(IWP_TEST_ROOT . '/data/xml/ticket-1139-cdata.xml', $config);
        $file->setRecordPath('products/product');

        $parser = new XMLParser($file);

        $record = $parser->getRecord(0);

        $result = $record->queryGroup([
            'fields' => [
                'post_content' => '{/ProductCode}',
            ]
        ]);

        $this->assertEquals('927.2115', $result['post_content']);
    }
}
