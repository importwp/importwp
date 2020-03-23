<?php

namespace ImportWP\Common\Util;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Container;

class Logger
{
    private static $id = null;

    public static function setId($id = null)
    {
        self::$id = $id;
    }

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
        if (is_null($id) && !is_null(self::$id) && intval(self::$id) > 0) {
            $id = self::$id;
        }

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

    public static function getLogFile($id = null, $url = false)
    {
        /**
         * @var Filesystem $filesystem
         */
        $filesystem = Container::getInstance()->get('filesystem');

        if (is_null($id)) {
            return $filesystem->get_temp_directory($url) . DIRECTORY_SEPARATOR . 'debug.log';
        }

        return $filesystem->get_temp_directory($url) . DIRECTORY_SEPARATOR . 'debug-' . $id . '.log';
    }
}
