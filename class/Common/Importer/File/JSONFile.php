<?php

namespace ImportWP\Common\Importer\File;

use ImportWP\Common\Importer\FileInterface;

class JSONFile extends AbstractIndexedFile implements FileInterface
{
    private $base_path;

    private $chunk;
    private $chunk_size = 8192;
    private $chunk_offset = 0;

    private $record_counter = 0;
    private $record_start_index = 0;

    public function setRecordPath($path)
    {
        $this->base_path = "\"{$path}\"";
    }

    /**
     * Generate record file positions
     *
     * Loop through each record and save each position
     */
    public function generateIndex()
    {
        $this->record_counter     = 0;
        $this->record_start_index = 0;

        rewind($this->getFileHandle());
        while (!feof($this->getFileHandle())) {

            $this->chunk .= $this->getChunk();
            $this->processChunk();
        }
    }

    /**
     * Read chunk from file
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
            '"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"', // skip escaped \"
            "'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'", // skip escaped \'
            '([^,"\'{}\[\]:\s]+)', // values not in quotes or double quotes
            '({)',
            '(})',
            '(\[)',
            '(\])',
            '(:)',
            '(,)',
        ];

        $open_tags = ['{', '['];
        $close_tags = ['}', ']'];

        $regex = '/' . implode('|', $regex_parts) . '/s';
        $track_depth = false;
        $track_depth_init = false;
        $depth = 0;
        $depth_to_capture = 1;

        while (preg_match($regex, $this->chunk, $matches, PREG_OFFSET_CAPTURE) !== 0) {
            list($captured, $offset) = $matches[0];

            $this->record_offset = $offset;
            $depth_changed = false;

            if (true === $track_depth) {

                if (in_array($captured, $open_tags)) {
                    $depth++;
                    $depth_changed = true;
                } elseif (in_array($captured, $close_tags)) {
                    $depth--;
                    $depth_changed = true;
                }

                if ($depth_changed) {
                    if ($depth === 0) {
                        $this->record_start_index = $this->chunk_offset + $this->record_offset;
                    } elseif ($depth < -1) {
                        $track_depth = false;
                    } elseif ($depth === -1) {
                        $this->setIndex(
                            $this->record_counter,
                            $this->record_start_index,
                            $this->chunk_offset + $this->record_offset + strlen($captured)
                        );
                        $this->record_counter++;
                    }
                }
            } elseif ($track_depth_init && $depth_to_capture >= 0) {
                if (in_array($captured, $open_tags)) {
                    $depth_to_capture--;
                    if ($depth_to_capture < 0) {
                        $track_depth = true;
                        $this->record_start_index = $this->chunk_offset + $this->record_offset;
                    }
                }
            }


            if ($captured === $this->base_path) {
                $track_depth_init = true;
            }

            $string_offset      = $offset + strlen($captured);
            $this->chunk_offset += $string_offset;
            $this->chunk        = substr($this->chunk, $string_offset);
        }
    }
}
