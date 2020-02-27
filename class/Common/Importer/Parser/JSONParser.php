<?php

namespace ImportWP\Common\Importer\Parser;

use ImportWP\Common\Importer\ParserInterface;

class JSONParser extends AbstractParser implements ParserInterface
{
    /**
     * JSON Record
     *
     * @var array
     */
    private $json_record;

    public function query($query)
    {
        return isset($this->json_record[$query]) ? $this->json_record[$query] : '';
    }

    protected function onRecordLoaded()
    {
        $this->json_record = json_decode($this->record, true);
    }

    public function record()
    {
        return $this->json_record;
    }
}
