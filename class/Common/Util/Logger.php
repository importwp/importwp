<?php

namespace ImportWP\Common\Util;

use DateTime;
use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Container;

class Logger
{
    private static $id = null;
    private static $requestType = null;
    private static $time = -1;
    private static $disabled = false;

    public static function setId($id = null)
    {
        self::$id = $id;
    }

    public static function disable()
    {
        self::$disabled = true;
    }

    public static function setRequestType($requestType = null)
    {
        self::$requestType = $requestType;
    }

    public static function clearRequestType()
    {
        self::$requestType = null;
    }

    public static function timer()
    {
        $tmp = self::$time;
        self::$time = microtime(true);

        return $tmp > -1 ? self::$time - $tmp : 0;
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
    public static function write($message, $id = null, $type = 'DEBUG')
    {
        if (self::$disabled) {
            return;
        }

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

        $now = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
        $log = $now->format('Y-m-d H:i:s.u') . ' ' . $type;
        if (!is_null(self::$requestType)) {
            $log .= ' ' . self::$requestType;
        }

        $log .= ' - ' . $message;

        $log .= ' -memory=' . self::formatBytes(memory_get_usage(), 2);

        file_put_contents($log_file,  $log . "\n", FILE_APPEND);
    }

    public static function debug($message, $id = null)
    {
        self::write($message, $id, 'DEBUG');
    }

    public static function info($message, $id = null)
    {
        self::write($message, $id, 'INFO');
    }

    public static function error($message, $id = null)
    {
        self::write($message, $id, 'ERROR');
    }

    public static function warn($message, $id = null)
    {
        self::write($message, $id, 'WARN');
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

    static function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow)); 

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
