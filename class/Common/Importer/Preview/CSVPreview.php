<?php

namespace ImportWP\Common\Importer\Preview;

use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Common\Importer\PreviewInterface;

class CSVPreview implements PreviewInterface
{

    /**
     * @var CSVFile $file
     */
    private $file;

    /**
     * CSVPreview constructor.
     *
     * @param \ImportWP\Common\Importer\File\CSVFile $file
     * @param array $args
     */
    public function __construct(CSVFile $file, $args = array())
    {
        $this->file = $file;
        // Do not enable processing mode — preview navigation needs the full record index.
    }

    /**
     * Generate a return a table based on the CSV data being previewed.
     *
     * @return string
     */
    public function output()
    {
        $records_to_fetch = 5;
        $total_records    = $this->file->getRecordCount();
        if ($total_records < $records_to_fetch) {
            $records_to_fetch = $total_records;
        }

        $output = '<table>';

        for ($i = 0; $i < $records_to_fetch; $i++) {

            $csv     = $this->file->getRecord($i);
            $wrapper = $i == 0 ? 'th' : 'td';

            $output .= '<tr>';
            $output .= sprintf('<%s>', $wrapper);
            $output .= implode(sprintf('</%s><%s>', $wrapper, $wrapper), str_getcsv($csv));
            $output .= sprintf('</%s>', $wrapper);
            $output .= '</tr>';
        }

        $output .= '</table>';

        return $output;
    }

    public function data($record_index = 0, $show_headings = true)
    {
        $result = [];
        $headings = str_getcsv($this->file->getRecord(0), $this->file->getDelimiter(), $this->file->getEnclosure(), $this->file->getEscape());
        $count = $this->file->getRecordCount();
        $total = true === $show_headings ? max(0, $count - 1) : $count;
        $record_index = max(0, intval($record_index));
        if ($total > 0) {
            $record_index = min($record_index, $total - 1);
        } else {
            $record_index = 0;
        }

        if (true === $show_headings) {
            $result['headings'] = $headings;
            $file_index = $record_index + 1;
        } else {
            $result['headings'] = [];
            for ($i = 0; $i < count($headings); $i++) {
                $result['headings'][] = $i;
            }
            $file_index = $record_index;
        }

        $result['row'] = str_getcsv($this->file->getRecord($file_index), $this->file->getDelimiter(), $this->file->getEnclosure(), $this->file->getEscape());
        $result['record'] = $record_index;
        $result['total'] = $total;
        return $result;
    }
}
