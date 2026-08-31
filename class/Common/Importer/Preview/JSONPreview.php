<?php

namespace ImportWP\Common\Importer\Preview;

use ImportWP\Common\Importer\File\JSONFile;
use ImportWP\Common\Importer\PreviewInterface;

class JSONPreview implements PreviewInterface
{
    /**
     * @var JSONFile
     */
    private $file;

    /**
     * @var string
     */
    private $record_path;

    /**
     * @param JSONFile $file
     * @param string $record_path
     */
    public function __construct(JSONFile $file, $record_path = '/')
    {
        $this->file = $file;
        $this->record_path = $record_path;
        $this->file->processing(true);
    }

    public function output()
    {
        $data = $this->data();
        return wp_json_encode($data);
    }

    /**
     * Build a preview tree for the first record under the given path.
     *
     * Shape mirrors XMLPreview nodes so the React tree can reuse similar rendering:
     * [ { node, xpath, attr, value } ]
     *
     * @return array
     */
    public function data()
    {
        $this->file->setRecordPath($this->record_path);

        // Prefer decoded JSON for preview — avoids sticky/empty stream indexes.
        $records = $this->file->getDecodedRecords(1);
        if (!empty($records) && is_array($records[0])) {
            return [[
                'node' => 'record',
                'xpath' => '',
                'attr' => [],
                'value' => $this->buildNodes($records[0], ''),
            ]];
        }

        // Fallback to stream-indexed record
        if ($this->file->getRecordCount() > 0) {
            $raw = $this->file->getRecord(0);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return [[
                    'node' => 'record',
                    'xpath' => '',
                    'attr' => [],
                    'value' => $this->buildNodes($decoded, ''),
                ]];
            }
        }

        return [[
            'node' => 'record',
            'xpath' => '',
            'attr' => [],
            'value' => [],
        ]];
    }

    /**
     * @param mixed $data
     * @param string $path
     * @return array
     */
    private function buildNodes($data, $path)
    {
        if (!is_array($data)) {
            return [[
                'node' => 'text',
                'xpath' => $path === '' ? '/' : $path,
                'value' => $this->stringify($data),
                'type' => 'text',
                'attr' => [],
            ]];
        }

        $nodes = [];

        if ($this->isList($data)) {
            foreach ($data as $index => $item) {
                $child_path = $path . '/' . $index;
                $nodes[] = [
                    'node' => (string) $index,
                    'xpath' => $child_path,
                    'attr' => [],
                    'value' => is_array($item)
                        ? $this->buildNodes($item, $child_path)
                        : $this->stringify($item),
                ];
            }
            return $nodes;
        }

        foreach ($data as $key => $value) {
            $child_path = $path . '/' . $key;
            $nodes[] = [
                'node' => (string) $key,
                'xpath' => $child_path,
                'attr' => [],
                'value' => is_array($value)
                    ? $this->buildNodes($value, $child_path)
                    : $this->stringify($value),
            ];
        }

        return $nodes;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function stringify($value)
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
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
