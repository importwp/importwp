<?php

namespace ImportWP\Common\Http;

use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Util\Logger;
use ImportWP\Container;

class Http
{
    /**
     * @var Properties
     */
    protected $properties;

    public function __construct(Properties $properties)
    {
        $this->properties = $properties;
    }

    public function end_rest_success($data)
    {
        return [
            'status' => 'S',
            'data' => $data
        ];
    }

    public function end_rest_error($data)
    {
        return [
            'status' => 'E',
            'data' => $data
        ];
    }

    public function download_file($source, $destination, $headers = [])
    {
        $response = wp_remote_get($source, array('timeout' => 30, 'sslverify' => false, 'headers' => $headers));
        $result = true;
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            Logger::write(__CLASS__ . '::download_file -response-code=' . $response_code . ' -url=' . esc_url($source));
            if ($response_code !== 200) {
                return new \WP_Error('IWP_HTTP_1', 'Unable to download: ' . esc_url($source) . ', Response Code: ' . $response_code);
            }

            $filename = $this->get_response_filename($response);
            if ($filename) {
                $result = $filename;
            }
        }

        if (is_wp_error($response)) {
            return $response;
        }



        if (!file_put_contents($destination, $response['body'])) {
            return false;
        }

        return $result;
    }

    public function download_file_stream($source, $destination, $headers = [])
    {
        $response = wp_remote_get($source, ['stream' => true, 'filename' => $destination, 'timeout' => 30, 'sslverify' => false, 'headers' => $headers]);
        $result = true;

        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);

            Logger::write(__CLASS__ . '::download_file_stream -response-code=' . $response_code . ' -url=' . esc_url($source) . ' -size=' . filesize($destination));
            if ($response_code !== 200) {
                return new \WP_Error('IWP_HTTP_1', 'Unable to download: ' . esc_url($source) . ', Response Code: ' . $response_code);
            }

            $filename = $this->get_response_filename($response);
            if ($filename) {
                $result = $filename;
            }
        }

        if (is_wp_error($response)) {
            return $this->download_file($source, $destination);
        }
        return $result;
    }

    public function get_response_filename($response)
    {
        $content_disposition = wp_remote_retrieve_header($response, 'content-disposition');
        $matches = [];
        if (preg_match('~filename=(?|"([^"]*)"|\'([^\']*)\'|([^;]*))~', $content_disposition, $matches) !== false && isset($matches[1])) {
            return $matches[1];
        }

        return false;
    }

    public function set_stream_headers()
    {
        $importer_manager = Container::getInstance()->get('importer_manager');
        if (false === $importer_manager->is_debug()) {
            header('Content-Type: text/event-stream');
        }

        header("Content-Encoding: none");
        header('Cache-Control: no-cache');

        // Allow for other requests to run at the same time
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
