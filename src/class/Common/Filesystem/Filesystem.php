<?php

namespace ImportWP\Common\Filesystem;

use ImportWP\Common\Http\Http;
use ImportWP\Common\Util\Singleton;
use ImportWP\Container;

class Filesystem
{
    use Singleton;

    /**
     * @var ImportWP\Container\Container
     */
    private $container;

    public function __construct()
    {
        $this->container = Container::getInstance();
    }

    public function copy($source, $destination, $allowed_mimes = null)
    {

        if (!file_exists($source)) {
            return new \WP_Error('IWP_FS_7', 'File doesn`t exist on local filesystem.');
        }

        if (!is_null($allowed_mimes)) {

            $filetype = $this->get_filetype($source);
            if (!$filetype) {
                return new \WP_Error('IWP_FS_6', 'Unable to determine filetype.');
            }

            if (!in_array($filetype, $allowed_mimes, true)) {
                return new \WP_Error('IWP_FS_4', 'Invalid filetype.');
            }

            return copy($source, $destination);
        }

        return copy($source, $destination);
    }

    public function upload_file($attachment, $allowed_mimes = null)
    {
        // check for upload status.
        switch ($attachment['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return new \WP_Error('IWP_FS_1', 'No file sent.');
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return new \WP_Error('IWP_FS_2', 'Exceeded filesize limit.');
                break;
            default:
                return new \WP_Error('IWP_FS_3', 'Unknown errors.');
                break;
        }

        if (isset($attachment['error']) && UPLOAD_ERR_OK === $attachment['error']) {

            // uploaded without errors.
            $a_name     = $attachment['name'];
            $a_tmp_name = $attachment['tmp_name'];

            $filetype = $this->check_mime_header($attachment['type']);

            // if header doesnt match check for file extension.
            if (!$filetype) {
                $filetype = $this->get_filetype_from_ext($attachment['name']);
            }

            // determine file type from mimetype.
            if (!is_null($allowed_mimes) && !in_array($filetype, $allowed_mimes, true)) {
                return new \WP_Error('IWP_FS_4', 'Invalid filetype.');
            }

            $wp_upload_dir = wp_upload_dir();

            $dest    = wp_unique_filename($wp_upload_dir['path'], $a_name);
            $wp_dest = $wp_upload_dir['path'] . '/' . $dest;

            // check to see if file was created.
            if (move_uploaded_file($a_tmp_name, $wp_dest)) {

                // return result array.
                return array(
                    'dest' => $wp_dest,
                    'type' => $filetype,
                    'mime' => $attachment['type']
                );
            }

            return new \WP_Error('IWP_FS_5', 'Unable to upload file.');
        }
    }

    public function download_file($remote_url, $filetype = null, $allowed_mimes = null)
    {
        $remote_url_temp = strtok($remote_url, '?');

        if (!is_null($allowed_mimes)) {
            if (is_null($filetype)) {
                $filetype = $this->get_filetype_from_ext($remote_url_temp);
            }
            if (!in_array($filetype, $allowed_mimes, true)) {
                return new \WP_Error('IWP_FS_4', 'Invalid filetype.');
            }
        }

        $wp_upload_dir = wp_upload_dir();

        $dest    = wp_unique_filename($wp_upload_dir['path'], basename($remote_url_temp));
        $wp_dest = $wp_upload_dir['path'] . '/' . $dest;

        /**
         * @var Http $http
         */
        $http = Container::getInstance()->get('http');

        $result = $http->download_file_stream($remote_url, $wp_dest);
        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'dest' => $wp_dest,
            'type' => $filetype,
            'mime' => $this->get_file_mime($wp_dest)
        );
    }

    public function copy_file($remote_url, $allowed_mimes = null)
    {
        $remote_url = strtok($remote_url, '?');

        $wp_upload_dir = wp_upload_dir();

        $dest    = wp_unique_filename($wp_upload_dir['path'], basename($remote_url));
        $wp_dest = $wp_upload_dir['path'] . '/' . $dest;

        $result = $this->copy($remote_url, $wp_dest, $allowed_mimes);
        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'dest' => $wp_dest,
            'type' => $this->get_filetype($wp_dest),
            'mime' => $this->get_file_mime($wp_dest)
        );
    }



    /**
     * Get and/or create the plugins tmp directory
     *
     * @return string
     */
    public function get_temp_directory()
    {

        $path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($path)) {
            mkdir($path);
        }

        $path .= DIRECTORY_SEPARATOR . 'importwp';
        if (!is_dir($path)) {
            mkdir($path);
        }

        return $path;
    }

    public function check_mime_header($mime)
    {
        switch ($mime) {
            case 'text/comma-separated-values':
            case 'text/csv':
            case 'application/csv':
            case 'application/excel':
            case 'application/vnd.ms-excel':
            case 'application/vnd.msexcel':
            case 'text/anytext':
            case 'text/plain':
                return 'csv';
            case 'text/xml':
            case 'application/xml':
            case 'application/x-xml':
                return 'xml';
        }

        return false;
    }

    public function get_filetype($file)
    {
        $mime_type = $this->get_file_mime($file);
        if ($mime_type) {
            return $this->check_mime_header($mime_type);
        }

        return $this->get_filetype_from_ext($file);
    }

    public function get_file_mime($file)
    {
        return mime_content_type($file);
    }

    public function get_filetype_from_ext($file)
    {

        if (stripos($file, '.csv')) {
            $filetype = 'csv';
        } elseif (stripos($file, '.xml')) {
            $filetype = 'xml';
        }

        return $filetype;
    }
}
