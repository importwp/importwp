<?php

namespace ImportWP\Common\Exporter\File;

class File
{
    /**
     * @var bool|resource
     */
    protected $fh;

    /**
     * @var EWP_Exporter $exporter
     */
    protected $exporter;

    /**
     * EWP_File constructor.
     *
     * @param EWP_Exporter $exporter
     *
     * @throws Exception
     */
    public function __construct($exporter)
    {

        $this->exporter = $exporter;

        $file_path = $this->get_file_path();
        $this->fh = fopen($file_path, 'w');
    }

    public function get_file_name()
    {
        throw new \Exception("get_file_name");
    }

    public function get_file_path()
    {
        $path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'exportwp' . DIRECTORY_SEPARATOR;
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
