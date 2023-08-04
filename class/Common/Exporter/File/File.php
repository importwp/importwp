<?php

namespace ImportWP\Common\Exporter\File;

use ImportWP\Common\Model\ExporterModel;

class File
{
    /**
     * @var bool|resource
     */
    protected $fh;

    /**
     * @var ExporterModel $exporter
     */
    protected $exporter;

    /**
     * EWP_File constructor.
     *
     * @param ExporterModel $exporter
     *
     * @throws Exception
     */
    public function __construct($exporter)
    {
        $this->exporter = $exporter;

        $file_path = $this->get_file_path();
        $this->fh = fopen($file_path, 'a+');
    }

    public function wipe()
    {
        fclose($this->fh);
        $file_path = $this->get_file_path();
        $this->fh = fopen($file_path, 'w');
    }

    public function getFieldLabel($field)
    {
        $label = '';
        if (isset($field['label']) && !empty($field['label'])) {
            $label = $field['label'];
        } elseif (isset($field['selection']) && !empty($field['selection'])) {
            $label =  $field['selection'];
        }
        return $label;
    }

    public function getValue($item, $data)
    {
        $value = isset($data[$item['id']]) ? $data[$item['id']] : '';
        if (is_array($value)) {
            return implode(',', $value);
        }

        return $value;
    }

    public function get_file_name()
    {
        throw new \Exception("get_file_name");
    }

    public function get_file_path()
    {
        $path = wp_normalize_path(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'exportwp' . DIRECTORY_SEPARATOR);
        if (!file_exists($path)) {
            mkdir($path);
        }

        return $path . $this->get_file_name();
    }

    public function get_file_url()
    {
        return content_url('/uploads/exportwp/' . $this->get_file_name());
    }
}
