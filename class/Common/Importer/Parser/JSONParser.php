<?php

namespace ImportWP\Common\Importer\Parser;

use ImportWP\Common\Importer\ParserInterface;

class JSONParser extends AbstractParser implements ParserInterface
{
    /**
     * Decoded JSON record.
     *
     * @var array|null
     */
    private $json_record;

    /**
     * Active sub-record when expanding a group base.
     *
     * @var array|null
     */
    private $query_base_record;

    /**
     * Query a value from the current JSON record.
     *
     * Paths are slash-delimited relative to the record root, e.g. "/id", "/author/name".
     * Legacy flat keys without a leading slash are also supported.
     *
     * @param string $query
     * @param bool $as_string
     * @return mixed
     */
    public function query($query, $as_string = true)
    {
        $value = $this->resolvePath($query, $this->getActiveRecord());

        if ($value === null || $value === false) {
            return $as_string ? '' : false;
        }

        if (!$as_string) {
            return $value;
        }

        if (is_array($value)) {
            if ($this->isList($value)) {
                $parts = [];
                foreach ($value as $item) {
                    if (is_scalar($item) || $item === null) {
                        $parts[] = (string) $item;
                    } else {
                        $parts[] = wp_json_encode($item);
                    }
                }
                return implode(',', $parts);
            }

            return wp_json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Query a group of JSON data, optionally expanding a nested array via base.
     *
     * @param array $group
     * @return array
     */
    public function queryGroup($group)
    {
        if (isset($group['base']) && $group['base'] !== '') {
            $output = [];
            $sub_records = $this->query($group['base'], false);

            if (!is_array($sub_records)) {
                return [];
            }

            if (!$this->isList($sub_records)) {
                $sub_records = [$sub_records];
            }

            foreach ($sub_records as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $this->query_base_record = $record;
                $output[] = parent::queryGroup($group);
            }
            $this->query_base_record = null;

            return $output;
        }

        return parent::queryGroup($group);
    }

    protected function onRecordLoaded()
    {
        $this->json_record = json_decode($this->record, true);
        $this->query_base_record = null;

        if (!is_array($this->json_record)) {
            $this->json_record = [];
        }
    }

    public function record()
    {
        return $this->json_record;
    }

    /**
     * @return array|null
     */
    private function getActiveRecord()
    {
        if (is_array($this->query_base_record)) {
            return $this->query_base_record;
        }

        return $this->json_record;
    }

    /**
     * Resolve a slash path against a record array.
     *
     * @param string $query
     * @param array|null $record
     * @return mixed
     */
    private function resolvePath($query, $record)
    {
        if (!is_array($record)) {
            return null;
        }

        $query = (string) $query;
        if ($query === '' || $query === '/') {
            return $record;
        }

        if (strpos($query, '/') === 0) {
            $query = substr($query, 1);
        }

        // Legacy flat key: no slashes
        if (strpos($query, '/') === false) {
            return array_key_exists($query, $record) ? $record[$query] : null;
        }

        $segments = explode('/', $query);
        $current = $record;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array $data
     * @return bool
     */
    private function isList(array $data)
    {
        if ($data === []) {
            return true;
        }

        return array_keys($data) === range(0, count($data) - 1);
    }
}
