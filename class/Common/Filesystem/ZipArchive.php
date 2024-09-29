<?php

namespace ImportWP\Common\Filesystem;

class ZipArchive
{
    public function __construct()
    {
        if (self::has_requirements_met()) {
            add_filter('iwp/importer/file_uploaded/file_path', [$this, 'read_file_matching_ext'], 10, 2);
        }
    }

    public static function has_requirements_met()
    {
        return class_exists('\ZipArchive');
    }

    /**
     * @param string $input_filepath
     * @param \ImportWP\Common\Model\ImporterModel $importer_model
     * @return void
     */
    function read_file_matching_ext($input_filepath, $importer_model)
    {
        $ext = $this->get_ext($input_filepath);

        switch ($ext) {
            case 'zip':

                $zip = new \ZipArchive();
                if (true !== $zip->open($input_filepath)) {
                    return $input_filepath;
                }

                $file_found = false;
                for ($i = 0; $i < $zip->numFiles; $i++) {

                    $filename = $zip->getNameIndex($i);

                    // TODO: we should be able to get file based on desired extension
                    if (preg_match('/\.(xml|csv|blm)$/', $filename) === 0 && $zip->numFiles > 1) {
                        continue;
                    }

                    $file_found = $filename;
                    break;
                }

                if (!$file_found) {
                    return $input_filepath;
                }

                $file_found_ext = $this->get_ext($file_found);
                $output_filepath = $input_filepath . '.' . $file_found_ext;

                $file_contents = $zip->getFromName($file_found);
                if (!$file_contents) {
                    return $input_filepath;
                }

                $zip->close();

                $output_filepath = $this->unique_filename($output_filepath);
                file_put_contents($output_filepath, $file_contents);

                update_post_meta($importer_model->getId(), '_iwp_zip', $input_filepath);

                return $output_filepath;
            case 'gz':

                $output_filepath = $this->get_output_filepath($input_filepath);
                $output_filepath = $this->unique_filename($output_filepath);
                $sfp = gzopen($input_filepath, "rb");
                $fp = fopen($output_filepath, "w");

                while (!gzeof($sfp)) {
                    $string = gzread($sfp, 4096);
                    fwrite($fp, $string, strlen($string));
                }
                gzclose($sfp);
                fclose($fp);

                if ($input_filepath !== $output_filepath) {
                    @unlink($input_filepath);
                }

                return $output_filepath;
        }

        return $input_filepath;
    }



    function get_ext($filepath)
    {
        $file_parts = explode('.', basename($filepath));
        return $file_parts[count($file_parts) - 1];
    }

    function get_output_filepath($filepath)
    {
        $file_parts = explode('.', $filepath);
        array_pop($file_parts);

        $last_part = $file_parts[count($file_parts) - 1];
        $matches = [];
        if (preg_match('/([^_]+)/', $last_part, $matches) !== false) {
            $file_parts[count($file_parts) - 1] = $matches[1];
        }

        return implode('.', $file_parts);
    }

    function unique_filename($filepath)
    {
        $filename = basename($filepath);
        $dir = substr($filepath, 0, -strlen($filename));
        $filename = wp_unique_filename($dir, basename($filename));

        return $dir . '/' . $filename;
    }
}
