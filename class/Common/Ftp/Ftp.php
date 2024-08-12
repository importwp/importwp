<?php

namespace ImportWP\Common\Ftp;

use ImportWP\Common\Filesystem\Filesystem;

class Ftp
{
    /**
     * @var resource $_conn
     */
    private $_conn;
    /**
     * @var string $conn_hash
     */
    private $conn_hash;
    /**
     * @var Filesystem $filesystem
     */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function download_file($url, $host, $user, $pass, $override_filename = null, $port = 21)
    {
        if ($this->_conn && $this->conn_hash !== md5($host . $user . $pass)) {
            $this->disconnect();
        }

        if (!function_exists('\ftp_connect')) {
            return new \WP_Error('IWP_FTP_3', __("To download via FTP please enable the php ftp extension.", 'jc-importer'));
        }

        if (!$this->_conn) {
            if (!$this->login($host, $user, $pass, $port)) {
                return new \WP_Error('IWP_FTP_0', __("Unable to login to ftp server", 'jc-importer'));
            }
        }

        if (!$this->_conn) {
            return new \WP_Error('IWP_FTP_0', __("Unable to connect to ftp server", 'jc-importer'));
        }


        // Note: Not all ftp servers support SIZE checks.
        $disable_filesize = apply_filters('iwp/ftp/disable_size_check', false);
        if(false === $disable_filesize){
            
            $size = ftp_size($this->_conn, $url);
            if ($size === -1) {
                return new \WP_Error('IWP_FTP_1', __('File doesn\'t exist on ftp server.', 'jc-importer'));
            }

            if ($size === 0) {
                return false;
            }
        }

        if (!empty($override_filename)) {

            $wp_dest = $override_filename;
        } else {

            $wp_upload_dir = wp_upload_dir();

            $dest    = wp_unique_filename($wp_upload_dir['path'], basename($url));
            $wp_dest = $wp_upload_dir['path'] . '/' . $dest;
        }

        $passive_mode = apply_filters('iwp/ftp/passive_mode', true);
        ftp_pasv($this->_conn, $passive_mode);

        $result = ftp_get($this->_conn, $wp_dest, $url, FTP_BINARY);
        if (false === $result) {
            return new \WP_Error('IWP_FTP_2', sprintf(__('Unable to download: %s file via ftp.', 'jc-importer'), $url));
        }

        return array(
            'dest' => $wp_dest,
            'type' => $this->filesystem->get_filetype($wp_dest),
            'mime' => $this->filesystem->get_file_mime($wp_dest)
        );
    }

    public function connect($host, $port = 21)
    {
        $this->_conn = ftp_connect($host, $port);
    }

    public function disconnect()
    {
        if ($this->_conn) {
            ftp_close($this->_conn);
            $this->_conn = false;
        }
    }

    public function login($host, $user, $pass, $port = 21)
    {
        if (!$this->_conn) {
            $this->connect($host, $port);
        }

        if ($this->_conn) {
            if (true === ftp_login($this->_conn, $user, $pass)) {
                $this->conn_hash = md5($host . $user . $pass);
                return true;
            }
        }

        return false;
    }

    public function get_connection()
    {
        return $this->_conn;
    }
}
