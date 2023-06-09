<?php

namespace ImportWP\Common\Importer\File;

use ImportWP\Common\Importer\ConfigInterface;

abstract class AbstractIndexedFile extends AbstractFile
{

    private $loaded = false;
    protected $config;

    /**
     * Are we temp processing a file
     *
     * @var boolean
     */
    protected $is_processing = false;
    /**
     * When temp processing a file we do not need all of it.
     *
     * @var integer Max byte of file to read
     */
    protected $process_max_size = 1000000;

    /**
     * AbstractIndexedFile constructor.
     *
     * TODO: Config might not be right here, as we are just storing record index's
     *
     * @param string $file_path
     * @param ConfigInterface $config
     */
    public function __construct($file_path, $config = null)
    {
        parent::__construct($file_path);

        $this->config = $config;
    }

    /**
     * Get record
     *
     * @param int $index
     *
     * @return string
     *
     * @throws \ImportWP\Common\Importer\Exception\FileException
     */
    public function getRecord($index = 0)
    {

        $position = $this->getIndex($index);
        fseek($this->getFileHandle(), $position[0]);
        $this->setCurrentRecord($index);

        // loop till length has been met or end of file

        $contents      = '';
        $max_chunk     = 8192;
        $bytes_to_read = $position[1];
        while (!feof($this->getFileHandle()) && $bytes_to_read > 0) {

            $temp_length = $bytes_to_read;
            if ($bytes_to_read > $max_chunk) {
                $temp_length = $max_chunk;
            }

            $contents      .= fread($this->getFileHandle(), $temp_length);
            $bytes_to_read -= $temp_length;
        }

        // Convert to utf-8 if not already
        if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
            $encoding = mb_detect_encoding($contents, array('UTF-8'), true);
            if (false === $encoding) {
                $from_encoding = $this->config->get('file_encoding');
                if (!empty($from_encoding)) {
                    $contents = mb_convert_encoding($contents, 'UTF-8', $from_encoding);
                } else {
                    $contents = mb_convert_encoding($contents, 'UTF-8');
                }
            }
        }

        return $contents;
    }

    /**
     * Get record file index.
     *
     * @param int $record
     *
     * @return array
     */
    public function getIndex($record)
    {
        if (!$this->loadIndex()) {
            $this->generateIndex();
            $this->storeIndexes();
        }

        return $this->config->getIndex($this->getFileIndexKey(), $record);
    }

    /**
     * Add record and file position to index
     *
     * @param int $record
     * @param int $start
     * @param int $end
     *
     * @internal param $index
     */
    public function setIndex($record, $start, $end)
    {
        if (!$this->loadIndex()) {
            $this->generateIndex();
            $this->storeIndexes();
        }

        $this->config->setIndex($this->getFileIndexKey(), $record, $start, $end);


        //        $this->index[$record] = array($start, $end);
    }

    /**
     * Load index from cache
     *
     * @return bool
     */
    public function loadIndex()
    {
        if ($this->loaded) {
            return true;
        }

        // Load index from cache
        $this->loaded = true;
        if ($this->readIndexes()) {
            return true;
        }

        return false;
    }

    /**
     * Generate file position index.
     *
     * @return mixed
     */
    abstract protected function generateIndex();

    /**
     * Is there record next
     *
     * @return bool
     */
    public function hasNextRecord()
    {
        if ($this->getRecordCount() - 1 > $this->current_record) {
            return true;
        }

        return false;
    }

    /**
     * Get record count
     *
     * @return int
     */
    public function getRecordCount()
    {
        if (!$this->loadIndex()) {
            $this->generateIndex();
            $this->storeIndexes();
        }

        return $this->config->getRecordCount($this->getFileIndexKey());
    }

    /**
     * Is there record previous
     *
     * @return bool
     */
    public function hasPrevRecord()
    {
        if ($this->current_record > 0) {
            return true;
        }

        return false;
    }

    public function storeIndexes()
    {
        if (null === $this->config) {
            return false;
        }

        return $this->config->storeIndexes($this->getFileIndexKey());
    }

    public function readIndexes()
    {
        if (null === $this->config) {
            return false;
        }

        return $this->config->readIndexes($this->getFileIndexKey());

        //	    return $this->index = $this->config->get( $this->getFileIndexKey() );
    }

    public function getFileIndexKey()
    {
        return 'file_index';
    }

    public function processing($processing = false)
    {
        $this->is_processing = true;
    }

    /**
     * Read file size from handle and reset pointer back to current position
     *
     * @param resource $handle
     * @return void
     */
    public function get_file_size($handle)
    {
        $current  = ftell($handle);
        fseek($handle, 0, SEEK_END);
        $size = ftell($handle);
        fseek($handle, $current);

        return $size;
    }
}
