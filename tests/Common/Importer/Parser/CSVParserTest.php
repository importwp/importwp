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

    public function testSerializedData()
    {

        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/test.csv", new Config($config_file));

        $parser = new CSVParser($file);

        $serialized_str = 'a:6:{i:1729718256;a:2:{s:32:"recovery_mode_clean_expired_keys";a:1:{s:32:"40cd750bba9870f18aada2478b24840a";a:3:{s:8:"schedule";s:5:"daily";s:4:"args";a:0:{}s:8:"interval";i:86400;}}s:34:"wp_privacy_delete_old_export_files";a:1:{s:32:"40cd750bba9870f18aada2478b24840a";a:3:{s:8:"schedule";s:6:"hourly";s:4:"args";a:0:{}s:8:"interval";i:3600;}}}i:1729721855;a:1:{s:16:"wp_version_check";a:1:{s:32:"40cd750bba9870f18aada2478b24840a";a:3:{s:8:"schedule";s:10:"twicedaily";s:4:"args";a:0:{}s:8:"interval";i:43200;}}}i:1729723655;a:1:{s:17:"wp_update_plugins";a:1:{s:32:"40cd750bba9870f18aada2478b24840a";a:3:{s:8:"schedule";s:10:"twicedaily";s:4:"args";a:0:{}s:8:"interval";i:43200;}}}i:1729725455;a:1:{s:16:"wp_update_themes";a:1:{s:32:"40cd750bba9870f18aada2478b24840a";a:3:{s:8:"schedule";s:10:"twicedaily";s:4:"args";a:0:{}s:8:"interval";i:43200;}}}i:1729804656;a:1:{s:30:"wp_site_health_scheduled_check";a:1:{s:32:"40cd750bba9870f18aada2478b24840a";a:3:{s:8:"schedule";s:6:"weekly";s:4:"args";a:0:{}s:8:"interval";i:604800;}}}s:7:"version";i:2;}';

        $result = $parser->getRecord(0)->queryGroup([
            'fields' => [
                'one' => $serialized_str,
            ]
        ]);
        $this->assertEquals($serialized_str, $result['one']);
    }
}
