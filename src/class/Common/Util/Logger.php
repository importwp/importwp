<?php

namespace ImportWP\Common\Util;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Container;

class Logger
{
    public static function write($message)
    {
        if (!defined('IWP_DEBUG') || IWP_DEBUG !== true) {
            return;
        }

        /**
         * @var Filesystem $filesystem
         */
        $filesystem = Container::getInstance()->get('filesystem');

        $log_file = $filesystem->get_temp_directory() . DIRECTORY_SEPARATOR . 'debug.log';
        file_put_contents($log_file, date('Y-m-d H:i:s - ') . $message . "\n", FILE_APPEND);
    }
}
