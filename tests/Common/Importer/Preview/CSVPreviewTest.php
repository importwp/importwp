<?php

namespace ImportWPTests\Common\Importer\Preview;

use ImportWP\Common\Importer\Config\Config;
use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Common\Importer\Preview\CSVPreview;

class CSVPreviewTest extends \WP_UnitTestCase
{
    public function testCSVPreview()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/test.csv", new Config($config_file));

        $preview = new CSVPreview($file);
        $this->assertEquals("<table><tr><th>One</th><th>Two</th><th>Three</th></tr><tr><td>1</td><td>2</td><td>3</td></tr></table>", $preview->output());
    }

    public function testCSVDataPreview()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file        = new CSVFile(IWP_TEST_ROOT . "/data/csv/test.csv", new Config($config_file));

        // show headings = true
        $preview = new CSVPreview($file);
        $this->assertEquals([
            'headings' => ['One', 'Two', 'Three'],
            'row' => [1, 2, 3],
            'record' => 0,
            'total' => 1,
        ], $preview->data());

        // show headings = false
        $preview = new CSVPreview($file);
        $this->assertEquals([
            'headings' => [0, 1, 2],
            'row' => ['One', 'Two', 'Three'],
            'record' => 0,
            'total' => 2,
        ], $preview->data(0, false));

        // show headings = false, 2nd row
        $preview = new CSVPreview($file);
        $this->assertEquals([
            'headings' => [0, 1, 2],
            'row' => [1, 2, 3],
            'record' => 1,
            'total' => 2,
        ], $preview->data(1, false));
    }

    public function test_multi_record_csv_preview_with_headings()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file = new CSVFile(IWP_TEST_ROOT . '/data/csv/data-posts.csv', new Config($config_file));
        $preview = new CSVPreview($file);

        $first = $preview->data(0, true);
        $this->assertEquals(3, $first['total']);
        $this->assertEquals(0, $first['record']);
        $this->assertEquals('Post One', $first['row'][0]);
        $this->assertEquals('title', $first['headings'][0]);

        $second = $preview->data(1, true);
        $this->assertEquals(1, $second['record']);
        $this->assertEquals('Post Two', $second['row'][0]);

        $third = $preview->data(2, true);
        $this->assertEquals(2, $third['record']);
        $this->assertEquals('Post Three', $third['row'][0]);
    }

    public function test_csv_preview_clamps_out_of_range_record()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file = new CSVFile(IWP_TEST_ROOT . '/data/csv/data-posts.csv', new Config($config_file));
        $preview = new CSVPreview($file);

        $result = $preview->data(99, true);
        $this->assertEquals(2, $result['record']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals('Post Three', $result['row'][0]);
    }

    public function test_csv_preview_total_includes_all_data_rows_for_sample_files()
    {
        $config_file = tempnam(sys_get_temp_dir(), 'config');
        $file = new CSVFile(IWP_TEST_ROOT . '/data/csv/data-posts.csv', new Config($config_file));
        // Processing mode used to stop after 2 rows; sample files must still expose every record.
        $file->processing(true);

        $preview = new CSVPreview($file);
        $result = $preview->data(0, true);

        $this->assertEquals(3, $result['total']);
        $this->assertEquals(4, $file->getRecordCount());
    }
}
