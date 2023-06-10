<?php

namespace ImportWPTests\Common\Importer\Parser;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Common\Importer\Parser\CSVParser;

/**
 * @group Parser
 * @group Core
 */
class CSVParserTest extends \WP_UnitTestCase
{
    public function testBasicSetup()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/test.csv", new Config($config_file));

        $parser = new CSVParser($file);
        $record = $parser->getRecord(0);
        $this->assertEquals('One', $record->query(0));
        $this->assertEquals('Two', $record->query(1));
        $this->assertEquals('Three', $record->query(2));

        $record = $parser->getRecord(1);
        $this->assertEquals('1', $record->query(0));
        $this->assertEquals('2', $record->query(1));
        $this->assertEquals('3', $record->query(2));
    }

    public function testRecordNotFoundException()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/test.csv", new Config($config_file));
        $parser      = new CSVParser($file);

        $this->expectException(\Exception::class);
        $record = $parser->getRecord(2);
    }

    public function testSectionMarkDelimiterCSV()
    {
        // §
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/tab_delimiter_test.csv", new Config($config_file));
        $file->setDelimiter("\t");
        $this->assertEquals(6, $file->getRecordCount());

        $parser = new CSVParser($file);
        $record = $parser->getRecord(0);
        $this->assertEquals('Col 1', $record->query(0));
        $this->assertEquals('Col 2', $record->query(1));
    }

    public function testCustomMethod()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/test.csv", new Config($config_file));

        $parser = new CSVParser($file);
        $result = $parser->getRecord(0)->queryGroup([
            'fields' => [
                'one' => '[strtoupper("{0}")]',
                'two' => '[strtoupper("{0}")] [strtoupper("{0}")]',
            ]
        ]);
        $this->assertEquals(strtoupper('One'), $result['one']);
        $this->assertEquals(strtoupper('One One'), $result['two']);
    }

    public function testMultipleCustomMethod()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/test.csv", new Config($config_file));

        $parser = new CSVParser($file);
        $this->assertEquals(strtoupper('One'), $parser->handle_custom_methods("[strtoupper(\"One\")]"));
        $this->assertEquals(strtoupper('(One)'), $parser->handle_custom_methods("[strtoupper(\"(One)\")]"));
        $this->assertEquals(strtoupper('(One) (Two)'), $parser->handle_custom_methods("[strtoupper(\"(One)\")] [strtoupper(\"(Two)\")]"));
    }
}
