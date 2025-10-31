<?php

namespace ImportWPTests\Common\Importer\File;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Container;

class CSVFileTest extends \WP_UnitTestCase
{
    public function testBasicCSV()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config_CSVFileTest_testBasicCSV.json');
        tempnam(sys_get_temp_dir(), 'config_CSVFileTest_testBasicCSV.json.file_index');

        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/test.csv", new Config($config_file));
        $this->assertEquals(2, $file->getRecordCount());
        $this->assertEquals("One,Two,Three", trim($file->getNextRecord()));
        $this->assertEquals("1,2,3", trim($file->getNextRecord()));
    }

    public function normalize_line_endings($input_filepath, $output_filepath = null)
    {
        if (is_null($output_filepath)) {
            $output_filepath = $input_filepath;
        }

        $contents = file_get_contents($input_filepath);
        $contents = str_replace([
            "\r\n",
            "\n\r"
        ], "\n", $contents);

        $contents = str_replace([
            "\r"
        ], "\n", $contents);

        file_put_contents($output_filepath, $contents);
    }

    function iwp_snippet_normalize_new_lines($input_filepath, $importer_model)
    {
        $this->normalize_line_endings($input_filepath);
        $id = $importer_model->getId();

        /**
         * @var ImportWP\Common\Importer\ImporterManager $manager
         */
        $manager = Container::getInstance()->get('importer_manager');
        $manager->clear_config_files($id, true, true);
    }
}
