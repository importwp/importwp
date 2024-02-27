<?php

namespace ImportWP\Common\Importer\Config;

use ImportWP\Common\Importer\ConfigInterface;
use ImportWP\Common\Importer\Exception\FileException;

class Config implements ConfigInterface
{

    private $config;
    private $file;
    private $index_buffer = [];
    private $index_cache_size = 1;

    public function __construct($file)
    {
        $this->file = $file;
        if (file_exists($this->file)) {
            $this->read();
        } else {
            $this->config = array();
            $this->write();
        }
    }

    /**
     * Read from file
     */
    private function read()
    {
        $this->config = json_decode(file_get_contents($this->file), true);
    }

    /**
     * Write to file
     */
    private function write()
    {
        file_put_contents($this->file, json_encode($this->config));
    }

    /**
     * Get setting
     *
     * @param $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : false;
    }

    /**
     * Set setting
     *
     * @param $key
     * @param $data
     *
     * @return mixed
     */
    public function set($key, $data)
    {
        $this->config[$key] = $data;
        $this->write();
    }

    public function getData()
    {
        $data = $this->get('data');

        return $data;
    }

    /**
     * TODO: Refactor function, basic implementation to test stream file indexing
     * @param $key
     * @param $record
     * @return array
     * @throws FileException
     */
    public function getIndex($key, $record)
    {
        $recordIndex = $record * 2;

        $cache = $this->getCachedIndex($key, $record);
        if ($cache) {
            return $cache;
        }

        $file = $this->getIndexFile($key);
        if (!file_exists($file)) {
            throw new FileException(sprintf(__("Config Index File Not Found: %s", 'jc-importer'), $file));
        }

        $fh = fopen($file, 'r');
        $counter = 0;


        $max_chunk = 8192;
        $prev_chunk = false;

        $found = $recordIndex === 0 ? true : false;
        $cache_max = $this->getIndexCacheSize();
        $cache = [];

        while (!feof($fh)) {

            // get buffer / append end of previous buffer
            $buffer = fread($fh, $max_chunk);
            if ($prev_chunk !== false) {
                $buffer = $prev_chunk . $buffer;
            }

            // reset $prev_chunk
            $prev_chunk = false;

            $buffer_len = strlen($buffer);

            $separator_line_count = substr_count($buffer, "\n");

            if ($counter + $separator_line_count >= $recordIndex) {

                // loop through each separator
                $separator_offset = 0;
                $separator_counter = $counter;
                while ($separator_offset < strlen($buffer)) {

                    $separator_pos = strpos($buffer, "\n", $separator_offset);
                    if ($separator_pos !== false) {

                        if ($found) {
                            $cache_data = substr($buffer, $separator_offset, $separator_pos - $separator_offset);
                            $cache[] = $cache_data;
                            if (count($cache) >= $cache_max * 2) {
                                break;
                            }
                        }

                        $separator_counter++;
                        $separator_offset = $separator_pos + 1;
                        if ($separator_counter === $recordIndex) {
                            $found = true;
                        }
                    } elseif (strlen($buffer) > $separator_offset) {
                        $prev_chunk = substr($buffer, $separator_offset);
                        break;
                    }
                }
            }

            if (count($cache) >= $cache_max * 2) {
                break;
            }

            $counter += $separator_line_count;
        }

        if (count($cache) < 2) {
            throw new FileException(sprintf(__("Unable to located record: %s.", 'jc-importer'), ($record + 1)));
        }

        // store cache
        $this->config['cached_indices'] = [
            'key' => $key,
            'start' => $record,
            'cache' => $cache
        ];
        $this->write();

        return [
            $cache[0],
            $cache[1]
        ];
    }

    public function setIndex($key, $record, $start, $end)
    {

        $this->index_buffer[] = $start;
        $this->index_buffer[] = $end - $start;

        if (count($this->index_buffer) >= 200000) {
            $this->storeIndexes($key, true);
        }
    }

    public function readIndexes($key)
    {
        return $this->hasIndexed($key);
    }

    public function storeIndexes($key, $buffer = false)
    {

        if (count($this->index_buffer) > 0) {

            if (!isset($this->config['count'])) {
                $this->config['count'] = array();
            }

            if (!isset($this->config['count'][$key])) {
                $this->config['count'][$key] = 0;
            }

            $this->config['count'][$key] += count($this->index_buffer) / 2;

            $fh = fopen($this->getIndexFile($key), 'a');
            foreach ($this->index_buffer as $value) {
                fputs($fh, $value . "\n");
            }
            fclose($fh);
            $this->index_buffer = [];
        }

        // hit end of indexer, write config to file
        if (false === $buffer) {
            if (!isset($this->config['indexed'])) {
                $this->config['indexed'] = array();
            }

            $this->config['indexed'][$key] = true;
            $this->write();
        }

        return true;
    }

    private function hasIndexed($key)
    {
        return isset($this->config['indexed']) && isset($this->config['indexed'][$key]) && true === $this->config['indexed'][$key];
    }

    public function getRecordCount($key)
    {
        return $this->config['count'][$key];
    }

    public function getIndexFile($key)
    {
        return $this->file . '.' . $key;
    }

    private function getCachedIndex($key, $record)
    {
        if (
            isset($this->config['cached_indices']['key'])
            && $this->config['cached_indices']['key'] === $key
            && isset($this->config['cached_indices']['start'])
            && isset($this->config['cached_indices']['cache'])
            && $this->config['cached_indices']['start'] <= $record
            && ($this->config['cached_indices']['start'] + (count($this->config['cached_indices']['cache']) / 2)) - 1 >= $record
        ) {

            $index = ($record - $this->config['cached_indices']['start']) * 2;

            return [
                $this->config['cached_indices']['cache'][$index],
                $this->config['cached_indices']['cache'][$index + 1]
            ];
        }

        return false;
    }

    public function setIndexCacheSize($length)
    {
        $this->index_cache_size = $length;
    }

    public function getIndexCacheSize()
    {
        return $this->index_cache_size;
    }
}
