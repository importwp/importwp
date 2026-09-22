<?php

namespace ImportWP\Common\Importer\Parser;

use ImportWP\Common\Importer\FileInterface;

abstract class AbstractParser
{
    /**
     * @var \ImportWP\Common\Importer\FileInterface $file
     */
    protected $file;

    protected $record_index;
    protected $record;

    /**
     * Parser constructor.
     *
     * @param \ImportWP\Common\Importer\FileInterface $file
     */
    public function __construct(FileInterface $file)
    {
        $this->file = $file;
    }

    public function getRecord($record_index = 0)
    {

        if ($this->record_index !== $record_index) {
            $this->record_index = $record_index;
            $this->record       = $this->file->getRecord($this->getRecordIndex());
            $this->onRecordLoaded();
        }

        return $this;
    }

    public function getRecordIndex()
    {
        return $this->record_index;
    }

    abstract protected function onRecordLoaded();

    public function queryGroup($group)
    {
        $output = [];

        if (isset($group['fields'])) {

            // find files matching ._mapped._index

            $maps = [];
            foreach ($group['fields'] as $field_key => $field_value) {

                $matches = [];
                if (
                    // post.post_name._mapped._index
                    // custom_fields.0.value._mapped._index
                    preg_match('/^(.*?)\._mapped\._index$/', $field_key, $matches) === 1 &&
                    // ignore custom_fields.0._mapped._index old style FieldMapper
                    preg_match('/^custom_fields\.(?:[0-9]+)\._mapped\._index$/', $field_key) === 0 &&
                    intval($field_value) > 0
                ) {
                    $maps[$matches[1]] = $field_value;
                } else {
                    $output[$field_key] = $this->query_string($field_value);
                }
            }
        }

        if (!empty($maps)) {
            foreach ($maps as $field_key => $field_rows) {

                $data = [];
                $delimiter = false;

                if (isset($output[$field_key . '._mapped._delimiter']) && !empty($output[$field_key . '._mapped._delimiter'])) {

                    // get delimiter from FieldMap delimiter field, this has priority
                    $delimiter = $output[$field_key . '._mapped._delimiter'];
                } else {

                    // get delimiter from parent section settings. e.g. taxonomies and attachments
                    $lastPos = strrpos($field_key, '.');
                    if ($lastPos !== false) {
                        $tmp = substr($field_key, 0, $lastPos);
                        if (isset($output[$tmp . '.settings._delimiter'])) {
                            $delimiter = !empty($output[$tmp . '.settings._delimiter']) ? $output[$tmp . '.settings._delimiter'] : ',';
                        }
                    }
                }

                foreach ($output as $item_key => $item_value) {
                    $matches = [];
                    if (preg_match('/^' . $field_key . '\._mapped\.([0-9]+)\.(.*?)$/', $item_key, $matches) === 1) {

                        // row is to high
                        if (intval($matches[1]) >= intval($field_rows)) {

                            unset($output[$matches[0]]);
                            continue;
                        }

                        if (!isset($data[$matches[1]])) {
                            $data[$matches[1]] = [];
                        }

                        $data[$matches[1]][$matches[2]] = $item_value;

                        unset($output[$matches[0]]);
                    }
                }

                if (!empty($delimiter)) {

                    $field_parts = explode($delimiter, $output[$field_key]);
                    foreach ($field_parts as $k => $field_part) {
                        $field_parts[$k] = $this->map_field_data($field_part, array_values($data));
                    }
                    $output[$field_key] = implode($delimiter, $field_parts);
                } else {
                    $output[$field_key] = $this->map_field_data($output[$field_key], array_values($data));
                }
            }
        }

        return $output;
    }

    /**
     * Parse Query String for {} run query on them
     *
     * @param string $query
     *
     * @return string
     */
    public function query_string($query)
    {
        // Parse [iwp:method(...)] before substituting {column} values, otherwise
        // parentheses in the data (e.g. Excel =Hyperlink("url")) can close a
        // malformed mapping that is missing ")".
        return $this->interpolate_braces($this->handle_custom_methods($query));
    }

    public function query_matches($matches)
    {
        if (isset($matches[0]) && isset($matches[1])) {
            return $this->query($matches[1]);
        }

        return '';
    }

    abstract public function query($query);

    public function file()
    {
        return $this->file;
    }

    public function handle_custom_methods($input)
    {
        // Prefixed [iwp:method(...)] to avoid colliding with shortcodes / Gutenberg content.
        if (!is_string($input) || $input === '' || strpos($input, '[iwp:') === false) {
            return $input;
        }

        $offset = 0;
        $output = '';
        $length = strlen($input);

        while (($start = strpos($input, '[iwp:', $offset)) !== false) {
            $output .= substr($input, $offset, $start - $offset);

            $parsed = $this->parse_custom_method_at($input, $start, $length);
            if ($parsed === null) {
                $output .= '[iwp:';
                $offset = $start + 5;
                continue;
            }

            $output .= $parsed[1];
            $offset = $parsed[0];
        }

        return $output . substr($input, $offset);
    }

    /**
     * Substitute {column} / {xpath} selectors.
     *
     * @param string $query
     * @return string
     */
    private function interpolate_braces($query)
    {
        if (!is_string($query) || $query === '' || strpos($query, '{') === false) {
            return $query;
        }

        return preg_replace_callback('/{(.*?)}/', array($this, 'query_matches'), $query);
    }

    /**
     * @param string $input
     * @param int    $start
     * @param int    $length
     * @return array{0:int,1:string}|null End offset and replacement, or null if not a complete call.
     */
    private function parse_custom_method_at($input, $start, $length)
    {
        $i = $start + 5;
        $name_len = strspn($input, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_', $i);
        if ($name_len < 1) {
            return null;
        }

        $method = substr($input, $i, $name_len);
        $i += $name_len;
        if ($i >= $length || $input[$i] !== '(') {
            return null;
        }
        $i++;

        $extracted = $this->extract_balanced_method_args($input, $i, $length);
        if ($extracted === null) {
            return null;
        }

        list($raw_args, $after_paren) = $extracted;
        if ($after_paren >= $length || $input[$after_paren] !== ']') {
            return null;
        }

        $end = $after_paren + 1;
        $original = substr($input, $start, $end - $start);
        $args = $this->split_custom_method_args($raw_args);
        foreach ($args as &$arg) {
            $arg = $this->interpolate_braces($arg);
        }
        unset($arg);

        if (is_callable($method)) {
            return array($end, call_user_func_array($method, $args));
        }

        return array($end, $original);
    }

    /**
     * Read until the method's closing ")", ignoring parentheses inside quotes.
     *
     * @param string $input
     * @param int    $start
     * @param int    $length
     * @return array{0:string,1:int}|null Raw args and offset after ")", or null if unterminated.
     */
    private function extract_balanced_method_args($input, $start, $length)
    {
        $depth = 1;
        $quote = null;
        for ($i = $start; $i < $length; $i++) {
            $ch = $input[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return array(substr($input, $start, $i - $start), $i + 1);
                }
            }
        }

        return null;
    }

    /**
     * @param string $raw_args
     * @return array
     */
    private function split_custom_method_args($raw_args)
    {
        $args = [];
        if ($raw_args === '') {
            return $args;
        }

        // Dont split comma's if they are inside a double quote
        if (preg_match_all('/(?:".*?"|[^",\s]+)(?=\s*,|\s*$)/s', $raw_args, $result) > 0) {
            $args = $result[0];
            foreach ($args as &$arg) {
                // Strip quotes from start and end of string
                $arg = preg_replace('/^(\'(.*)\'|"(.*)")$/s', '$2$3', $arg);
            }
            unset($arg);
        }

        return $args;
    }

    public function map_field_data($input, $map)
    {
        foreach ($map as $map_data) {

            $condition = isset($map_data['_condition']) && !empty($map_data['_condition']) ? $map_data['_condition'] : 'equal';
            $key = isset($map_data['key']) ? $map_data['key'] : '';
            $output = isset($map_data['value']) ? $map_data['value'] : '';

            switch ($condition) {

                case 'gt':
                    $left = intval($input);
                    $right = intval($key);
                    if ($left > $right) {
                        return $output;
                    }
                    break;
                case 'gte':
                    $left = intval($input);
                    $right = intval($key);
                    if ($left >= $right) {
                        return $output;
                    }
                    break;
                case 'lt':
                    $left = intval($input);
                    $right = intval($key);
                    if ($left < $right) {
                        return $output;
                    }
                    break;
                case 'lte':
                    $left = intval($input);
                    $right = intval($key);
                    if ($left <= $right) {
                        return $output;
                    }
                    break;
                case 'contains':
                    if (stripos($input, trim($key)) !== false) {
                        return $output;
                    }
                    break;
                case 'in':
                    if (in_array($input, explode(',', $key))) {
                        return $output;
                    }
                    break;
                case 'not-equal':
                    if (trim($key) !== trim($input)) {
                        return $output;
                    }
                    break;
                case 'not-contains':
                    if (stripos($input, trim($key)) === false) {
                        return $output;
                    }
                    break;
                case 'not-in':
                    if (!in_array($input, explode(',', $key))) {
                        return $output;
                    }
                    break;
                default:
                    if (trim($key) === trim($input)) {
                        return $output;
                    }
                    break;
            }
        }
        return $input;
    }
}
