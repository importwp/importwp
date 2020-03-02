<?php

namespace ImportWP\Common\Util;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Container;

class Logger
{
    public static function clear($id)
    {

        /**
         * @var ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');
        if (false === $importer_manager->is_debug()) {
            return;
        }

        $log_file = self::getLogFile($id);
        file_put_contents($log_file, '');
    }
    public static function write($message, $id = null)
    {
        /**
         * @var ImporterManager $importer_manager
         */
        $importer_manager = Container::getInstance()->get('importer_manager');
        if (false === $importer_manager->is_debug()) {
            return;
        }

        $log_file = self::getLogFile($id);

        file_put_contents($log_file, date('Y-m-d H:i:s - ') . $message . "\n", FILE_APPEND);
    }

    public static function getLogFile($id = null)
    {
        /**
         * @var Filesystem $filesystem
         */
        $filesystem = Container::getInstance()->get('filesystem');

        if (is_null($id)) {
            return $filesystem->get_temp_directory() . DIRECTORY_SEPARATOR . 'debug.log';
        }
        return $filesystem->get_temp_directory() . DIRECTORY_SEPARATOR . 'debug-' . $id . '.log';
    }
}
