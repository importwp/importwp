<?php

namespace ImportWP\Common\Importer\File;

use ImportWP\Common\Importer\Exception\FileException;

abstract class AbstractFile
{

    /**
     * @var string $file_path
     */
    private $file_path;
    /**
     * @var resource $file_handle
     */
    private $file_handle;

    protected $current_record = false;

    private $current_file_position = -1;

    /**
     * File constructor.
     *
     * @param $file_path
     * @throws FileException
     */
    public function __construct($file_path)
    {
        @ini_set('auto_detect_line_endings', TRUE);

        $this->file_path = $file_path;
        if (file_exists($this->file_path)) {
            $this->file_handle = fopen($this->file_path, 'r');
        } else {
            throw new FileException("File Not Found: " . $file_path);
        }
    }

    public function __destruct()
    {
        if ($this->file_handle) {
            fclose($this->file_handle);
        }

        @ini_set('auto_detect_line_endings', FALSE);
    }

    /**
     * Get next record
     *
     * @return string
     */
    public function getNextRecord()
    {
        $record = false === $this->current_record ? 0 : $this->current_record + 1;

        return $this->getRecord($record);
    }

    abstract public function getRecord($record);

    /**
     * Get previous Record
     *
     * @return array
     */
    public function getPreviousRecord()
    {
        return $this->getRecord($this->current_record - 1);
    }

    protected function saveFilePosition()
    {
        $this->current_file_position = ftell($this->file_handle);
    }

    protected function loadFilePosition($record = 0)
    {

        if ($this->current_file_position >= 0) {
            fseek($this->file_handle, $this->current_file_position);
        }
    }

    /**
     * @return resource
     * @throws FileException
     */
    protected function getFileHandle()
    {
        if (!in_array(get_resource_type($this->file_handle), array('stream', 'file'), true)) {
            throw new FileException("File not found: " . $this->file_path);
        }
        return $this->file_handle;
    }

    protected function setCurrentRecord($index)
    {
        $this->current_record = $index;
    }
}
