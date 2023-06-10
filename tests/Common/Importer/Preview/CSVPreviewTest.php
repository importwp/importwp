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
            'row' => [1, 2, 3]
        ], $preview->data());

        // show headings = false
        $preview = new CSVPreview($file);
        $this->assertEquals([
            'headings' => [0, 1, 2],
            'row' => ['One', 'Two', 'Three']
        ], $preview->data(0, false));

        // show headings = false, 2nd row
        $preview = new CSVPreview($file);
        $this->assertEquals([
            'headings' => [0, 1, 2],
            'row' => [1, 2, 3]
        ], $preview->data(1, false));
    }
}
