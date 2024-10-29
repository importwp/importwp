<?php

namespace ImportWP\Common\Http;

use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Util\Logger;

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
        $args = apply_filters('iwp/http/remote_get_args', [
            'timeout' => 30,
            'sslverify' => false,
            'headers' => $headers,
            'reject_unsafe_urls' => true
        ]);

        $response = wp_safe_remote_get($source, $args);
        $result = true;
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            Logger::write(__CLASS__ . '::download_file -response-code=' . $response_code . ' -url=' . esc_url($source));
            if ($response_code !== 200) {
                return new \WP_Error('IWP_HTTP_1', sprintf(__('Unable to download: %s, Response Code: %s', 'jc-importer'), esc_url($source), $response_code));
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
        $args = apply_filters('iwp/http/remote_get_args', [
            'timeout' => 30,
            'sslverify' => false,
            'headers' => $headers,
            'reject_unsafe_urls' => true
        ]);

        $args = array_merge($args, [
            'stream' => true,
            'filename' => $destination,
        ]);

        $response = wp_remote_get($source, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        Logger::write(__CLASS__ . '::download_file_stream -response-code=' . $response_code . ' -url=' . esc_url($source) . ' -size=' . filesize($destination));
        if ($response_code !== 200) {
            return new \WP_Error('IWP_HTTP_1', sprintf(__('Unable to download: %s, Response Code: %s', 'jc-importer'), esc_url($source), $response_code));
        }

        $filename = $this->get_response_filename($response);
        if ($filename) {
            return $filename;
        }

        return true;
    }

    public function get_response_filename($response)
    {
        $content_disposition = wp_remote_retrieve_header($response, 'content-disposition');
        $matches = [];
        if (preg_match('~filename=(?|"([^"]*)"|\'([^\']*)\'|([^;]*))~', $content_disposition, $matches) !== false && isset($matches[1])) {
            return $matches[1];
        }

        // If there is no extension on the file, use the response content-type to add th file extension
        if (isset($response['filename'])) {

            $filename = basename($response['filename']);
            $current_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (empty($current_ext)) {

                $content_type = wp_remote_retrieve_header($response, 'content-type');

                if ($content_type) {

                    $mime_types = wp_get_mime_types();
                    $possible_ext = array_search($content_type, $mime_types);
                    $allowed_extensions = explode('|', $possible_ext);

                    if ($possible_ext !== false && empty($current_ext) && !empty($allowed_extensions) && !in_array(strtolower($filename), $allowed_extensions)) {
                        return $filename . '.' . $allowed_extensions[0];
                    }
                }
            }
        }

        return false;
    }

    public function set_stream_headers()
    {
        send_nosniff_header();
        nocache_headers();
    }
}
