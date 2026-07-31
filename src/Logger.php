<?php
/**
 * clayon/src/Logger.php
 * 
 * Simple centralized logging utility for the Clayon platform.
 */

class Logger {
    private static $logDir = __DIR__ . '/../logs';

    public static function log($message, $type = 'INFO', $filename = 'worker.log') {
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$type] $message" . PHP_EOL;
        
        file_put_contents(self::$logDir . '/' . $filename, $formattedMessage, FILE_APPEND);
    }

    public static function error($message, $filename = 'worker.log') {
        self::log($message, 'ERROR', $filename);
    }

    public static function info($message, $filename = 'worker.log') {
        self::log($message, 'INFO', $filename);
    }
}
