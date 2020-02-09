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

    public function download_file($url, $host, $user, $pass)
    {
        if ($this->_conn && $this->conn_hash !== md5($host . $user . $pass)) {
            $this->disconnect();
        }

        if (!$this->_conn) {
            $this->login($host, $user, $pass);
        }

        $size = ftp_size($this->_conn, $url);
        if ($size === -1) {
            return new \WP_Error('IWP_FTP_1', 'Could not get ftp file size.');
        }


        if ($size === 0) {
            return false;
        }

        $wp_upload_dir = wp_upload_dir();

        $dest    = wp_unique_filename($wp_upload_dir['path'], basename($url));
        $wp_dest = $wp_upload_dir['path'] . '/' . $dest;

        $result = ftp_get($this->_conn, $wp_dest, $url, FTP_BINARY);
        if (false === $result) {
            return new \WP_Error('IWP_FTP_2', 'Unable to download: ' . $url . '  file via ftp.');
        }

        return array(
            'dest' => $wp_dest,
            'type' => $this->filesystem->get_filetype($wp_dest),
            'mime' => $this->filesystem->get_file_mime($wp_dest)
        );
    }

    public function connect($host)
    {
        $this->_conn = ftp_connect($host);
    }

    public function disconnect()
    {
        if ($this->_conn) {
            ftp_close($this->_conn);
            $this->_conn = false;
        }
    }

    public function login($host, $user, $pass)
    {
        if (!$this->_conn) {
            $this->connect($host);
        }

        if ($this->_conn) {
            if (true === ftp_login($this->_conn, $user, $pass)) {
                $this->conn_hash = md5($host . $user . $pass);
            }
        }
    }
}
