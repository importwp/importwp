<?php

namespace ImportWP\Common\Importer\Parser;

use ImportWP\Common\Importer\File\CSVFile;
use ImportWP\Common\Importer\ParserInterface;

/**
 * @property CSVFile $file
 */
class CSVParser extends AbstractParser implements ParserInterface
{
    /**
     * CSV Record
     *
     * @var array
     */
    private $csv_record;

    public function query($query)
    {
        return isset($this->csv_record[$query]) ? $this->csv_record[$query] : '';
    }

    protected function onRecordLoaded()
    {
        $this->csv_record = str_getcsv($this->record, $this->file->getDelimiter(), $this->file->getEnclosure());
    }

    public function record()
    {
        return $this->csv_record;
    }
}
