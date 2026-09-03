<?php

namespace ImportWP\Common\Importer\File;

use ImportWP\Common\Importer\FileInterface;

class JSONFile extends AbstractIndexedFile implements FileInterface
{
    /**
     * Path segments to the array that holds records.
     * Empty means the root value is an array of records.
     *
     * @var string[]
     */
    private $base_path_segments = [];

    /**
     * Cache key fragment derived from the record path.
     *
     * @var string
     */
    private $record_path_cache_key = 'root';

    /**
     * Discovered record paths (path => sample count).
     *
     * @var array
     */
    private $path_list = [];

    private $chunk = '';
    private $chunk_size = 8192;
    private $chunk_offset = 0;

    private $record_counter = 0;
    private $record_start_index = 0;
    private $record_offset = 0;

    // Stream parser state — must persist across chunk reads
    private $parse_segment_index = 0;
    private $parse_awaiting_value = false;
    private $parse_depth = 0;
    private $parse_search_depth = 0;
    private $parse_path_matched = false;
    private $parse_tracking = false;
    private $parse_record_depth = 0;
    private $parse_skip_depth = 0;

    /**
     * Set base path for records.
     *
     * Examples: "data", "results/items", "/" or "" for a root array.
     *
     * @param string $path
     */
    public function setRecordPath($path = '/')
    {
        if ($path === null) {
            $path = '/';
        }

        $path = trim((string) $path);
        if (strpos($path, '/') === 0) {
            $path = substr($path, 1);
        }
        $path = rtrim($path, '/');

        $segments = $path === '' ? [] : explode('/', $path);
        $segments = array_values(array_filter($segments, function ($value) {
            return $value !== '';
        }));

        $new_key = empty($segments) ? 'root' : implode('_', $segments);
        if ($new_key !== $this->record_path_cache_key) {
            $this->resetIndexState();
        }

        $this->base_path_segments = $segments;
        $this->record_path_cache_key = $new_key;
    }

    public function getFileIndexKey()
    {
        return sprintf('file_index-%s', $this->record_path_cache_key);
    }

    /**
     * Discover candidate record paths (arrays of objects).
     *
     * @return array path => count
     */
    public function get_path_list()
    {
        $this->path_list = $this->config ? $this->config->get('json_path_list') : null;
        if (!is_array($this->path_list)) {
            $this->path_list = [];
        }

        if (!empty($this->path_list)) {
            return $this->path_list;
        }

        $decoded = $this->decodeFileSample();
        if (!is_array($decoded)) {
            return [];
        }

        $this->path_list = [];
        $this->collectPaths($decoded, '', $this->path_list);
        if ($this->config) {
            $this->config->set('json_path_list', $this->path_list);
        }

        return $this->path_list;
    }

    /**
     * Return records at the current record path by decoding JSON.
     * Used for preview so we are not dependent on a stream index.
     *
     * @param int $limit
     * @return array
     */
    public function getDecodedRecords($limit = 1)
    {
        $decoded = $this->decodeFileSample();
        if (!is_array($decoded)) {
            return [];
        }

        $records = $this->resolveRecordsAtPath($decoded);
        if (!is_array($records) || !$this->isList($records)) {
            return [];
        }

        if ($limit > 0) {
            return array_slice($records, 0, $limit);
        }

        return $records;
    }

    /**
     * Generate record file positions.
     */
    public function generateIndex()
    {
        $this->record_counter = 0;
        $this->record_start_index = 0;
        $this->chunk = '';
        $this->chunk_offset = 0;

        $this->parse_segment_index = 0;
        $this->parse_awaiting_value = false;
        $this->parse_depth = 0;
        $this->parse_search_depth = 0;
        $this->parse_path_matched = empty($this->base_path_segments);
        $this->parse_tracking = false;
        $this->parse_record_depth = 0;
        $this->parse_skip_depth = 0;

        rewind($this->getFileHandle());
        while (!feof($this->getFileHandle())) {
            if ($this->is_processing && $this->chunk_offset > $this->process_max_size) {
                break;
            }

            $this->chunk .= $this->getChunk();
            $this->processChunk();
        }
    }

    /**
     * Read chunk from file.
     *
     * @return bool|string
     */
    public function getChunk()
    {
        return fread($this->getFileHandle(), $this->chunk_size);
    }

    public function processChunk()
    {
        $regex_parts = [
            '"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"',
            '([^,"\'{}\[\]:\s]+)',
            '({)',
            '(})',
            '(\[)',
            '(\])',
            '(:)',
            '(,)',
        ];

        $regex = '/' . implode('|', $regex_parts) . '/s';

        while (preg_match($regex, $this->chunk, $matches, PREG_OFFSET_CAPTURE) !== 0) {
            list($captured, $offset) = $matches[0];
            $this->record_offset = $offset;

            $is_string = strlen($captured) >= 2 && $captured[0] === '"';
            $is_open_object = $captured === '{';
            $is_close_object = $captured === '}';
            $is_open_array = $captured === '[';
            $is_close_array = $captured === ']';
            $is_colon = $captured === ':';
            $is_structure = $is_open_object || $is_close_object || $is_open_array || $is_close_array;

            // Skipping an unmatched value subtree
            if ($this->parse_skip_depth > 0) {
                if ($is_open_object || $is_open_array) {
                    $this->parse_skip_depth++;
                    $this->parse_depth++;
                } elseif ($is_close_object || $is_close_array) {
                    $this->parse_skip_depth--;
                    $this->parse_depth--;
                }
                $this->consumeToken($offset, $captured);
                continue;
            }

            if ($this->parse_tracking) {
                if ($is_open_object || $is_open_array) {
                    $this->parse_record_depth++;
                    $this->parse_depth++;
                } elseif ($is_close_object || $is_close_array) {
                    $this->parse_record_depth--;
                    $this->parse_depth--;
                    if ($this->parse_record_depth === 0 && $is_close_object) {
                        $this->setIndex(
                            $this->record_counter,
                            $this->record_start_index,
                            $this->chunk_offset + $this->record_offset + strlen($captured)
                        );
                        $this->record_counter++;
                        $this->parse_tracking = false;
                    } elseif ($this->parse_record_depth < 0) {
                        $this->parse_tracking = false;
                        $this->parse_path_matched = false;
                        $this->parse_awaiting_value = false;
                        $this->parse_segment_index = 0;
                        $this->parse_search_depth = 0;
                    }
                }
                $this->consumeToken($offset, $captured);
                continue;
            }

            if ($this->parse_path_matched) {
                if ($is_open_object) {
                    $this->parse_tracking = true;
                    $this->parse_record_depth = 1;
                    $this->parse_depth++;
                    $this->record_start_index = $this->chunk_offset + $this->record_offset;
                } elseif ($is_close_array || $is_close_object) {
                    $this->parse_path_matched = false;
                    $this->parse_awaiting_value = false;
                    $this->parse_segment_index = 0;
                    $this->parse_search_depth = 0;
                    $this->parse_depth--;
                } elseif ($is_open_array) {
                    $this->parse_depth++;
                }
                $this->consumeToken($offset, $captured);
                continue;
            }

            if ($this->parse_awaiting_value) {
                if ($is_colon) {
                    $this->consumeToken($offset, $captured);
                    continue;
                }

                $is_last_segment = $this->parse_segment_index >= count($this->base_path_segments);

                if ($is_last_segment && $is_open_array) {
                    $this->parse_path_matched = true;
                    $this->parse_awaiting_value = false;
                    $this->parse_depth++;
                } elseif (!$is_last_segment && $is_open_object) {
                    $this->parse_awaiting_value = false;
                    $this->parse_depth++;
                    $this->parse_search_depth = $this->parse_depth;
                } elseif ($is_open_object || $is_open_array) {
                    $this->parse_awaiting_value = false;
                    $this->parse_segment_index = 0;
                    $this->parse_search_depth = 0;
                    $this->parse_skip_depth = 1;
                    $this->parse_depth++;
                } else {
                    $this->parse_awaiting_value = false;
                    $this->parse_segment_index = 0;
                    $this->parse_search_depth = 0;
                }

                $this->consumeToken($offset, $captured);
                continue;
            }

            if (empty($this->base_path_segments)) {
                if ($is_open_array && $this->parse_depth === 0) {
                    $this->parse_path_matched = true;
                    $this->parse_depth++;
                } elseif ($is_structure) {
                    if ($is_open_object || $is_open_array) {
                        $this->parse_depth++;
                    } else {
                        $this->parse_depth--;
                    }
                }
                $this->consumeToken($offset, $captured);
                continue;
            }

            // Enter root object so we can match top-level path keys
            if ($is_open_object && $this->parse_depth === 0 && $this->parse_segment_index === 0 && !$this->parse_awaiting_value) {
                $this->parse_depth++;
                $this->parse_search_depth = 1;
                $this->consumeToken($offset, $captured);
                continue;
            }

            // Looking for the next path key at search_depth
            if ($is_string && $this->parse_depth === $this->parse_search_depth) {
                $key = $this->unquote($captured);
                if (isset($this->base_path_segments[$this->parse_segment_index]) && $key === $this->base_path_segments[$this->parse_segment_index]) {
                    $this->parse_segment_index++;
                    $this->parse_awaiting_value = true;
                }
            } elseif ($is_structure) {
                if ($is_open_object || $is_open_array) {
                    $this->parse_depth++;
                } else {
                    $this->parse_depth--;
                    if ($this->parse_depth < $this->parse_search_depth) {
                        $this->parse_segment_index = min($this->parse_segment_index, $this->segmentsReachedAtDepth($this->parse_depth));
                        $this->parse_search_depth = max(0, $this->parse_depth);
                    }
                }
            }

            $this->consumeToken($offset, $captured);
        }
    }

    /**
     * @param int $depth
     * @return int
     */
    private function segmentsReachedAtDepth($depth)
    {
        return max(0, min($depth, count($this->base_path_segments)));
    }

    /**
     * @param int $offset
     * @param string $captured
     */
    private function consumeToken($offset, $captured)
    {
        $string_offset = $offset + strlen($captured);
        $this->chunk_offset += $string_offset;
        $this->chunk = substr($this->chunk, $string_offset);
    }

    /**
     * @param string $token
     * @return string
     */
    private function unquote($token)
    {
        if (strlen($token) >= 2 && $token[0] === '"' && substr($token, -1) === '"') {
            return stripcslashes(substr($token, 1, -1));
        }

        return $token;
    }

    /**
     * @return array|null
     */
    private function decodeFileSample()
    {
        $handle = $this->getFileHandle();
        $current = ftell($handle);
        rewind($handle);

        $sample = '';
        $max = max($this->process_max_size, 1000000);
        while (!feof($handle) && strlen($sample) < $max) {
            $sample .= fread($handle, $this->chunk_size);
        }

        $decoded = json_decode($sample, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = $this->decodePartialSample($sample);
        }

        fseek($handle, $current);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Walk to the record array for the current base path.
     *
     * @param array $decoded
     * @return array|null
     */
    private function resolveRecordsAtPath(array $decoded)
    {
        if (empty($this->base_path_segments)) {
            return $decoded;
        }

        $current = $decoded;
        foreach ($this->base_path_segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Attempt to decode a truncated JSON sample by closing open braces/brackets.
     *
     * @param string $sample
     * @return array|null
     */
    private function decodePartialSample($sample)
    {
        $in_string = false;
        $escape = false;
        $stack = [];

        $len = strlen($sample);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sample[$i];
            if ($in_string) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $in_string = false;
                }
                continue;
            }

            if ($ch === '"') {
                $in_string = true;
            } elseif ($ch === '{' || $ch === '[') {
                $stack[] = $ch;
            } elseif ($ch === '}' || $ch === ']') {
                array_pop($stack);
            }
        }

        if ($in_string) {
            $sample .= '"';
        }

        $sample = rtrim($sample);
        $sample = preg_replace('/[,:]\s*$/', '', $sample);

        while (!empty($stack)) {
            $open = array_pop($stack);
            $sample .= $open === '{' ? '}' : ']';
        }

        $decoded = json_decode($sample, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * @param mixed $data
     * @param string $prefix
     * @param array $paths
     */
    private function collectPaths($data, $prefix, &$paths)
    {
        if (!is_array($data)) {
            return;
        }

        if ($this->isList($data)) {
            if ($this->isListOfObjects($data)) {
                $key = $prefix === '' ? '/' : $prefix;
                $paths[$key] = count($data);
            }
            return;
        }

        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $path = $prefix === '' ? (string) $key : $prefix . '/' . $key;
            $this->collectPaths($value, $path, $paths);
        }
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

    /**
     * @param array $data
     * @return bool
     */
    private function isListOfObjects(array $data)
    {
        if (empty($data) || !$this->isList($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!is_array($item) || $this->isList($item)) {
                return false;
            }
        }

        return true;
    }
}
