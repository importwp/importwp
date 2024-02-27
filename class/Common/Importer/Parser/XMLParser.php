<?php

namespace ImportWP\Common\Importer\Parser;

use ImportWP\Common\Importer\ParserInterface;

class XMLParser extends AbstractParser implements ParserInterface
{
    /**
     * XML Record
     *
     * @var \SimpleXMLElement
     */
    private $record_xml;

    /**
     * Base path to alter query
     *
     * @var string
     */
    private $query_base;

    /**
     * Query Group of XML Data
     *
     * @param $group
     *
     * @return array
     */
    public function queryGroup($group)
    {
        if (isset($group['base'])) {

            $output      = [];
            $sub_records = $this->query($group['base'], false);
            $this->setQueryBase($group['base']);

            if (!empty($sub_records)) {
                foreach ($sub_records as $record) {
                    $this->record_xml = $record;
                    $output[]         = parent::queryGroup($group);
                }
            }

            $this->resetQueryBase();
        } else {
            $output = parent::queryGroup($group);
        }

        return $output;
    }

    /**
     * Run XML Query
     *
     * @param $query
     *
     * @param bool $as_string
     *
     * @return bool|\SimpleXMLElement[]
     */
    public function query($query, $as_string = true)
    {
        $xpath_prefix = "/record/*";
        if ($this->getQueryBase() !== "") {
            $xpath_prefix = '.';
        }

        if (false === $this->record_xml) {
            return false;
        }

        $results = $this->record_xml->xpath($xpath_prefix . $query);

        // make sure the result has records
        if (is_array($results) && count($results) > 0) {
            if ($as_string) {
                $temp = [];
                foreach ($results as $result) {
                    $temp[] = (string) $result;
                }

                return implode(',', $temp);
            }

            return $results;
        }

        return false;
    }

    /**
     * Get Query Base
     *
     * @return string
     */
    private function getQueryBase()
    {
        if ($this->query_base) {
            return $this->query_base;
        }

        return '';
    }

    /**
     * Set Query Base
     *
     * @param string $base
     */
    private function setQueryBase($base)
    {
        $this->query_base = $base;
    }

    /**
     * Reset Query Base
     */
    private function resetQueryBase()
    {
        $this->query_base = null;
        $this->onRecordLoaded();
    }

    /**
     * Load record string into XML format
     */
    protected function onRecordLoaded()
    {
        $this->record_xml = simplexml_load_string($this->record);
    }

    public function record()
    {
        return $this->record_xml;
    }

    /**
     * Return list of SimpleXMLElements
     *
     * @param string $sub_path
     * @param \SimpleXMLElement $record_xml
     * @return \SimpleXMLElement[]
     */
    public function getSubRecords($sub_path, $record_xml = null)
    {
        $xpath_prefix = '.';

        if ($record_xml === null) {
            $record_xml = $this->record_xml;
            $xpath_prefix = "/record/*";
        }

        return $record_xml->xpath($xpath_prefix . $sub_path);
    }

    public function getString($query, $record_xml = null)
    {
        $result = $this->getSubRecords($query, $record_xml);
        $output = [];
        foreach ($result as $a) {
            $output[] = (string) $a;
        }
        return implode(',', $output);
    }
}
