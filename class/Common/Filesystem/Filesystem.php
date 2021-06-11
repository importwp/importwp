<?php

namespace ImportWP\Common\Filesystem;

use ImportWP\Common\Http\Http;
use ImportWP\Common\Util\Logger;
use ImportWP\Common\Util\Singleton;
use ImportWP\Container;
use ImportWP\EventHandler;

class Filesystem
{
    use Singleton;

    /**
     * @var ImportWP\Container\Container
     */
    private $container;

    /**
     * @var EventHandler $event_handler
     */
    private $event_handler;

    public function __construct(EventHandler $event_handler)
    {
        $this->event_handler = $event_handler;
        $this->container = Container::getInstance();
    }

    public function copy($source, $destination, $allowed_mimes = null)
    {

        if (!file_exists($source)) {
            Logger::write('File doesn`t exist on local filesystem: ' . $source);
            return new \WP_Error('IWP_FS_7', 'File doesn`t exist on local filesystem.');
        }

        $filetype = $this->get_filetype($source);

        if (!is_null($allowed_mimes)) {

            if (!$filetype) {
                Logger::write('Unable to determine filetype: ' . $source);
                return new \WP_Error('IWP_FS_6', 'Unable to determine filetype.');
            }

            if (!in_array($filetype, $allowed_mimes, true)) {
                Logger::write('Invalid filetype: ' . $filetype . ', allowed(' . implode(', ', $allowed_mimes) . ')');
                return new \WP_Error('IWP_FS_4', 'Invalid filetype.');
            }
        }

        if (!copy($source, $destination)) {
            return new \WP_Error('IWP_FS_4', 'Unable to copy file: ' . $source . '.');
        }

        $type = $this->get_file_mime($source);
        Logger::write('Copied file: ' . $destination . ' -type=' . $filetype . ' -mime=' . $type);
        return true;
    }

    public function upload_file($attachment, $allowed_mimes = null)
    {
        // use built in wordpress file upload
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . "wp-admin" . '/includes/file.php';
        }

        $uploaded_file = wp_handle_upload($attachment, ['test_form' => false, 'test_type' => false]);

        if (isset($uploaded_file['error'])) {
            Logger::write($uploaded_file['error']);
            return new \WP_Error('IWP_FS_UF', $uploaded_file['error']);
        }

        $file = $uploaded_file['file'];
        $type = $uploaded_file['type'];

        $filetype = $this->check_mime_header($uploaded_file['type']);

        // if header doesnt match check for file extension.
        if (!$filetype) {
            $filetype = $this->get_filetype_from_ext($attachment['name']);
        }

        // determine file type from mimetype.
        if (!is_null($allowed_mimes) && !in_array($filetype, $allowed_mimes, true)) {
            Logger::write('Invalid filetype: ' . $filetype . ', allowed(' . implode(', ', $allowed_mimes) . ')');
            return new \WP_Error('IWP_FS_4', 'Invalid filetype.');
        }

        Logger::write('Uploaded file: ' . $file . ' -type=' . $filetype . ' -mime=' . $type);

        return array(
            'dest' => $file,
            'type' => $filetype,
            'mime' => $type
        );
    }

    public function download_file($remote_url, $filetype = null, $allowed_mimes = null, $override_filename = null, $prefix = '')
    {
        $remote_url_temp = strtok($remote_url, '?');

        if (!is_null($allowed_mimes)) {
            if (is_null($filetype)) {
                $filetype = $this->get_filetype_from_ext($remote_url_temp);
            }
            if (!in_array($filetype, $allowed_mimes, true)) {
                Logger::write('Invalid filetype: ' . $filetype . ', allowed(' . implode(', ', $allowed_mimes) . ')');
                return new \WP_Error('IWP_FS_4', 'Invalid filetype.');
            }
        }

        $wp_upload_dir = wp_upload_dir();
        $filename = !empty($override_filename) ? $override_filename : $prefix . basename($remote_url_temp);
        $dest    = wp_unique_filename($wp_upload_dir['path'], $filename);
        $wp_dest = $wp_upload_dir['path'] . '/' . $dest;
        touch($wp_dest);

        /**
         * @var Http $http
         */
        $http = Container::getInstance()->get('http');

        $headers = [];
        if ($filetype === 'xml') {
            $headers['Content-Type'] = 'text/xml';
            $headers['Accept'] = 'text/xml';
        } elseif ($filetype === 'csv') {
            $headers['Content-Type'] = 'text/csv';
            $headers['Accept'] = 'text/csv';
        }

        $result = $http->download_file_stream($remote_url, $wp_dest, $headers);
        if (is_wp_error($result)) {
            @unlink($wp_dest);
            Logger::write($result->get_error_message());
            return $result;
        }

        if (is_string($result)) {
            $filename = !empty($override_filename) ? $override_filename : $prefix . basename($result);
            $dest    = wp_unique_filename($wp_upload_dir['path'], $filename);
            $wp_tmp_dest = $wp_upload_dir['path'] . '/' . $dest;

            if (copy($wp_dest, $wp_tmp_dest)) {
                Logger::write('Rename file: ' . $wp_dest . ' -output=' . $wp_tmp_dest);
                unlink($wp_dest);
                $wp_dest = $wp_tmp_dest;
            }
        }

        $exists = $this->file_exists($wp_dest);
        if (is_wp_error($exists)) {
            return $exists;
        }

        $type = $this->get_file_mime($wp_dest);
        Logger::write('Downloaded file: ' . $wp_dest . ' -type=' . $filetype . ' -mime=' . $type);

        return array(
            'dest' => $wp_dest,
            'type' => $filetype,
            'mime' => $type
        );
    }

    public function file_exists($src)
    {
        try {
            if (!file_exists($src)) {
                throw new \Exception("File not found: " . $src);
            }

            $size = filesize($src);
            if ($size == 0) {
                unlink($src);
                throw new \Exception("File not found or empty: " . $src);
            }
        } catch (\Exception $e) {
            return new \WP_Error('IWP_FS_8', $e->getMessage());
        }

        return true;
    }

    public function copy_file($remote_url, $allowed_mimes = null, $override_filename = null, $prefix = '')
    {
        $remote_url = strtok($remote_url, '?');

        $wp_upload_dir = wp_upload_dir();

        $filename = !empty($override_filename) ? $override_filename : $prefix . basename($remote_url);
        $dest    = wp_unique_filename($wp_upload_dir['path'], $filename);
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
    public function get_temp_directory($url = false)
    {
        $base = $url ? WP_CONTENT_URL : WP_CONTENT_DIR;
        $ds = $url ? '/' : DIRECTORY_SEPARATOR;
        $path = $base . $ds . 'uploads';
        if (!is_dir($path)) {
            mkdir($path);
        }

        $path .= $ds . 'importwp';
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

        return $this->event_handler->run('importer.allowed_mime_types', [false, $mime]);
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
        $check = wp_check_filetype_and_ext($file, basename($file));
        return $check['type'];
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
