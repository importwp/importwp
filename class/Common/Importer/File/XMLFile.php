<?php

namespace ImportWP\Common\Importer\File;

use ImportWP\Common\Importer\FileInterface;

class XMLFile extends AbstractIndexedFile implements FileInterface
{

    private $base_path;
    private $base_path_segments = array();
    private $record_offset = 0;
    private $chunk_size = 8192;
    private $depth = 0;
    private $open_nodes = array();
    private $node_list = array();
    private $chunk;
    private $chunk_offset = 0;

    private $record_counter = 0;
    private $record_start_index = 0;
    private $record_opened = false;
    private $last_record_incomplete = false;
    private $record_patch_cache_key = '';

    /**
     * Set base path for records
     *
     * @param string $base_path
     */
    public function setRecordPath($base_path = '/')
    {
        // strip slash from start
        if (strpos($base_path, '/') === 0) {
            $base_path = substr($base_path, 1);
        }

        // split basepath in segments
        $this->base_path_segments = explode('/', $base_path);
        array_filter($this->base_path_segments, function ($value) {
            return $value !== "";
        });

        if (!empty($this->base_path_segments)) {
            $this->record_patch_cache_key = implode('_', $this->base_path_segments);
            $base_path                    = array_pop($this->base_path_segments);
        }

        $this->base_path = $base_path;
    }

    /**
     * Get record
     *
     * @param int $index
     *
     * @return string
     */
    public function getRecord($index = 0)
    {
        $record = parent::getRecord($index);

        if ($index === $this->getRecordCount() - 1 && true === $this->last_record_incomplete) {
            $record = $this->fixRecord($record);
        }

        return $this->wrapWithRecordTag($record);
    }

    /**
     * Fix a broken xml segment
     *
     * @param $record
     *
     * @return bool|string
     */
    private function fixRecord($record)
    {
        $open       = "/<[^\/][^>]+[^\/]>/";
        $close      = "/<\/[^>]+[^\/]>/";
        $depth      = 0;
        $started    = false;
        $open_nodes = array();

        $chunk = $record;

        while (preg_match(
            "/<[^?|!\-\-][^>]*>/",
            $chunk,
            $matches,
            PREG_OFFSET_CAPTURE
        ) !== 0) {
            if (isset($matches[0]) && isset($matches[0][0])) {
                list($captured, $offset) = $matches[0];

                if (preg_match($open, $captured) === 1) {
                    $started = true;
                    $depth++;
                    $node_name    = $this->getNodeName($captured);
                    $open_nodes[] = $node_name;
                } else if (preg_match($close, $captured) === 1) {
                    $depth--;
                    array_pop($open_nodes);
                }

                $string_offset = $offset + strlen($captured);
                $chunk         = substr($chunk, $string_offset);
            }

            if ($started && 0 === $depth) {
                // We should be at the end of the xml
                return substr($record, 0, strlen($chunk));
            }
        }

        if (!empty($open_nodes)) {
            // Add closing tags for open nodes
            foreach ($open_nodes as $node) {
                $record .= "</{$node}>";
            }
        }

        return $record;
    }

    /**
     * Get node name from xml tag
     *
     * @param $node
     *
     * @return bool|string
     */
    private function getNodeName($node)
    {

        preg_match("/<\/?([a-zA-Z\-_\.:]+)[ ]*[^>]*>/", $node, $matches);
        if (isset($matches[0]) && isset($matches[1])) {
            $name = $matches[1];
        } else {
            $name = substr($node, 1, strlen($node) - 2);
        }

        return $name;
    }

    /**
     * Generate file position index.
     *
     * Read through entire xml file generating record start and end positions
     *
     * @return mixed
     */
    protected function generateIndex()
    {
        $this->record_counter     = 0;
        $this->record_start_index = 0;

        rewind($this->getFileHandle());
        while (!feof($this->getFileHandle())) {

            $this->chunk .= $this->getChunk();
            $this->readChunkXmlNodes();

            // only read the first 1mb of file
            if ($this->is_processing && ($this->record_counter > 0 || ftell($this->getFileHandle()) > $this->process_max_size)) {
                break;
            }
        }

        // if file is incomplete do we try and fix the record, or skip it?
        if (!$this->is_processing && true === $this->record_opened) {
            $this->record_opened          = false;
            $this->last_record_incomplete = true;
            $this->setIndex(
                $this->record_counter,
                $this->record_start_index,
                $this->chunk_offset
            );
        }
    }

    /**
     * Read chunk from file
     *
     * @return bool|string
     */
    private function getChunk()
    {
        return fread($this->getFileHandle(), $this->chunk_size);
    }

    /**
     * Read all xml nodes in chunk
     */
    private function readChunkXmlNodes()
    {
        $tags = [
            ["<?", "?>", true, 0],
            ["<!--", "-->", true, 0],
            ["<![CDATA[", "]]>", true, 0],
            ["<!", ">", true, 0],
            ["</", ">", false, -1],
            ["<", "/>", false, 0],
            ["<", ">", false, 1],
        ];

        while (preg_match("/<[^>]+>/", $this->chunk, $matches, PREG_OFFSET_CAPTURE) !== 0) {
            list($captured, $offset) = $matches[0];

            $this->record_offset = $offset;

            foreach ($tags as $tag) {

                list($open_tag, $close_tag, $search_for_close, $depth_delta) = $tag;

                if (strpos($captured, $open_tag) === 0) {

                    if (strlen($captured) - strlen($close_tag) === strpos($captured, $close_tag)) {
                        // Tag Found
                        if ($search_for_close === false) {
                            $this->record_node($captured, $depth_delta);
                        }
                        break;
                    } elseif ($search_for_close === true) {
                        $close_tag_position = strpos($this->chunk, $close_tag, $offset);
                        if ($close_tag_position !== false) {
                            // Close tag is found in current chunk
                            $captured = substr($this->chunk, $offset, $close_tag_position + strlen($close_tag) - $offset);
                            if ($search_for_close === false) {
                                $this->record_node($captured, $depth_delta);
                            }
                        } else {
                            // We need to load next chunk to find
                            return;
                        }
                        break;
                    }
                }
            }

            $string_offset      = $offset + strlen($captured);
            $this->chunk_offset += $string_offset;
            $this->chunk        = substr($this->chunk, $string_offset);
        }
        return;

        // TODO: Match xmlns: <[^>]+xmlns:(.*?)="(.*?)"[^>]+>
        /*$open      = "/<[^\/][^>]*[^\/]>/s";
        $close     = "/<\/[^>]*[^\/]>/";
        $openclose = "/<[^\/][^>]+\/>/s";

        while (preg_match("/<[^?|!\-\-][^>]*>/s", $this->chunk, $matches,
                PREG_OFFSET_CAPTURE) !== 0) {
            if (isset($matches[0]) && isset($matches[0][0])) {
                list($captured, $offset) = $matches[0];

                $this->record_offset = $offset;

                if (preg_match($close, $captured) === 1) {
                    $this->depth--;

                    $node = array_pop($this->open_nodes);

                    if ($this->isRecordEnd($node)) {
                        $this->record_opened = false;
                        $temp                = $this->chunk_offset + $this->record_offset + strlen($captured);
                        $this->setIndex($this->record_counter,
                            $this->record_start_index, $temp);
                        $this->record_counter++;
                    }
                }elseif (preg_match($open, $captured) === 1) {
                    $this->depth++;

                    $node_name = $this->getNodeName($captured);

                    if ($this->isRecordStart($node_name)) {
                        $this->record_opened      = true;
                        $temp                     = $this->chunk_offset + $this->record_offset;
                        $this->record_start_index = $temp;
                    }

                    $this->open_nodes[] = $node_name;
                    $this->record_open_nodes();

                }
                $string_offset      = $offset + strlen($captured);
                $this->chunk_offset += $string_offset;
                $this->chunk        = substr($this->chunk, $string_offset);
            }
        }*/
    }

    private function record_node($node, $depth_delta)
    {

        $this->depth += $depth_delta;
        $node_name = $this->getNodeName($node);

        if ($depth_delta > 0) {

            if ($this->isRecordStart($node_name)) {
                $this->record_opened = true;
                $this->record_start_index = $this->chunk_offset + $this->record_offset;
            }

            $this->open_nodes[] = $node_name;
            $this->record_open_nodes();
        } elseif ($depth_delta < 0) {

            array_pop($this->open_nodes);
            if ($this->isRecordEnd($node_name)) {
                $this->record_opened = false;

                $this->setIndex(
                    $this->record_counter,
                    $this->record_start_index,
                    $this->chunk_offset + $this->record_offset + strlen($node)
                );
                $this->record_counter++;
            }
        }

        //        echo sprintf("%s %s - %d\n", str_repeat("\t", $this->depth), $node_name, $depth_delta);
    }

    /**
     * Check for record start node
     *
     * @param $node_name
     *
     * @return bool
     */
    private function isRecordStart($node_name)
    {

        if ($this->base_path_segments === $this->open_nodes && $node_name === $this->base_path) {
            return true;
        }

        return false;
    }

    /**
     * Check for record end node
     *
     * @param $node_name
     *
     * @return bool
     */
    private function isRecordEnd($node_name)
    {
        if ($this->record_opened === true && $node_name === $this->base_path) {

            // check to see if we are a nested duplicate node name /Events/Event/Instance and we are closing Event
            if (in_array($node_name, $this->open_nodes)) {
                $array_counts = array_count_values($this->open_nodes);
                if (isset($array_counts[$node_name]) && $array_counts[$node_name] > 0) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    public function wrapWithRecordTag($record)
    {
        $namespace_attrs = '';
        $unique_matches = array();
        $temp = $record;

        //        $new_regex       = '/<([^:!\/]+):[^ ]+[^>]*>/';
        //        $new_regex = '/<([^!\/>]+):[^>]+>/'; // breaking due to attr="http://", would match the :
        $new_regex = '/<([^!\/">]+):[^>]+>/';

        while (preg_match($new_regex, $temp, $matches, PREG_OFFSET_CAPTURE) !== 0) {
            if (isset($matches[0]) && isset($matches[0][0]) && isset($matches[1]) && isset($matches[1][0])) {
                list($match, $offset) = $matches[0];
                $namespace = trim($matches[1][0]);

                // TODO: update regex to not need this
                $namespace_parts = explode(' ', $namespace);
                $namespace = $namespace_parts[count($namespace_parts) - 1];

                if (!in_array($namespace, $unique_matches)) {
                    $unique_matches[] = $namespace;
                    $namespace_attrs .= ' xmlns:' . $namespace . '="false"';
                }

                $temp = substr($temp, $offset + strlen($match));
            }
        }

        $open_tag = '<record' . $namespace_attrs . '>';
        $close_tag = '</record>';
        return $open_tag . $record . $close_tag;
    }

    private function record_open_nodes()
    {
        $key = '/' . implode('/', $this->open_nodes);
        if (!in_array($key, $this->node_list, true)) {
            array_push($this->node_list, $key);
        }
    }

    public function get_node_list()
    {

        $this->node_list = $this->config->get('node_list');
        if (!is_array($this->node_list)) {
            $this->node_list = array();
        }

        $loaded = $this->loadIndex();
        if (empty($this->node_list) || !$loaded) {
            $this->generateIndex();
            $this->storeIndexes();

            $this->config->set('node_list', $this->node_list);
        }

        return $this->node_list;
    }

    protected final function getFileIndexKey()
    {
        $key = sprintf('file_index-%s', $this->record_patch_cache_key);

        return $key;
    }
}
