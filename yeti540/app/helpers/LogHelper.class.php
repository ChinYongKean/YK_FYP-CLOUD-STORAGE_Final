<?php

namespace App\Helpers;

/**
 * Log class.
 *
 * Class file for managing SYSTEM logging functionality to file
 *
 * @author      MFScripts.com - info@mfscripts.com
 * @version     1.0
 */
class LogHelper
{
    public static $newSession = true;
    public static $context = 'system';
    public static $oldContext = null;
    public static $paramsDefaults = [];
    public static $logSessionTracker = null;

    static function initErrorHandler() {
        if (!defined('SITE_CONFIG_LOGGING_LOG_ENABLED')) {
            define('SITE_CONFIG_LOGGING_LOG_ENABLED', 'yes');
        }

        error_reporting(E_ALL);
        ini_set('display_errors', 'Off');
        ini_set('log_errors', 'On');
        $error_handler = set_error_handler(array('App\Helpers\LogHelper', 'globalErrorHandler'));
        register_shutdown_function(array('App\Helpers\LogHelper', 'fatalErrorHandler'));
        
        self::$logSessionTracker = substr(md5(microtime()), 0, 8);
    }

    static function globalErrorHandler($errno, $errstr, $errfile, $errline) {
        // defaults encase the db config didn't load
        if (!defined('SITE_CONFIG_LOGGING_LOG_ENABLED')) {
            define('SITE_CONFIG_LOGGING_LOG_ENABLED', 'yes');
        }

        if (!defined('SITE_CONFIG_LOGGING_LOG_TYPE')) {
            define('SITE_CONFIG_LOGGING_LOG_TYPE', 'Serious Errors Only');
        }

        // make sure logging is enabled
        if (SITE_CONFIG_LOGGING_LOG_ENABLED != 'yes') {
            return;
        }

        if (!(error_reporting() & $errno)) {
            // this error code is not included in error_reporting
            return;
        }

        // set error logs to 'system'
        $oldContext = self::getContext();
        self::setContext('system');

        // prepare error data
        $errorArr = [];
        $errorArr['Error Msg'] = $errstr;
        $errorArr['File'] = $errfile;
        $errorArr['Line Number'] = $errline;
        $errorArr['Error Type'] = self::friendlyErrorType($errno);
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_USER_ERROR:
                if (in_array(SITE_CONFIG_LOGGING_LOG_TYPE, array('Serious Errors Only', 'Serious Errors and Warnings', 'All Error Types'))) {
                    self::error(print_r($errorArr, true));
                }

                if (SITE_CONFIG_LOGGING_LOG_OUTPUT == 'yes') {
                    self::outputFormattedError($errorArr);
                }
                exit(1);
                break;

            case E_WARNING:
            case E_USER_WARNING:
                if (in_array(SITE_CONFIG_LOGGING_LOG_TYPE, array('Serious Errors and Warnings', 'All Error Types'))) {
                    self::warning(print_r($errorArr, true));
                }
                break;

            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                if (SITE_CONFIG_LOGGING_LOG_TYPE == 'All Error Types') {
                    self::info(print_r($errorArr, true));
                }
                break;

            default:
                // unknown error type: [$errno] $errstr
                break;
        }

        // revert to original logging file
        self::setContext($oldContext);

        /* don't execute PHP internal error handler */
        return true;
    }

    static function fatalErrorHandler() {
        $errfile = "Unknown file";
        $errstr = "Shutdown";
        $errno = E_CORE_ERROR;
        $errline = 0;

        $error = error_get_last();

        // if we've found an error then log it
        if ($error !== NULL) {
            $errno = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr = $error["message"];

            self::globalErrorHandler($errno, $errstr, $errfile, $errline);
        }
    }

    static function outputFormattedError($errorArr) {
        echo '<div style="padding:20px; background-color: #FFBABA; border: 1px solid; color: #D8000C;">';
        echo '<span style="font-family: Arial, Helvetica, sans-serif;">';
        echo '<strong>SYSTEM ERROR:</strong><br/><br/>';
        echo '<table cellspacing="0" cellpadding="2">';
        foreach ($errorArr AS $label => $value) {
            echo '<tr>';
            echo '<td style="width: 150px; font-weight: bold;">' . htmlentities($label) . ':</td>';
            echo '<td>' . htmlentities($value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</span>';
        echo '</div>';
    }

    static function friendlyErrorType($type) {
        switch ($type) {
            case E_ERROR: // 1 // 
                return 'E_ERROR';
            case E_WARNING: // 2 // 
                return 'E_WARNING';
            case E_PARSE: // 4 // 
                return 'E_PARSE';
            case E_NOTICE: // 8 // 
                return 'E_NOTICE';
            case E_CORE_ERROR: // 16 // 
                return 'E_CORE_ERROR';
            case E_CORE_WARNING: // 32 // 
                return 'E_CORE_WARNING';
            case E_CORE_ERROR: // 64 // 
                return 'E_COMPILE_ERROR';
            case E_CORE_WARNING: // 128 // 
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR: // 256 // 
                return 'E_USER_ERROR';
            case E_USER_WARNING: // 512 // 
                return 'E_USER_WARNING';
            case E_USER_NOTICE: // 1024 // 
                return 'E_USER_NOTICE';
            case E_STRICT: // 2048 // 
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR: // 4096 // 
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: // 8192 // 
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED: // 16384 // 
                return 'E_USER_DEPRECATED';
        }

        return $type;
    }

    /**
     * Generic wrapper for toFile()
     *
     * @param array/string $content
     * @param string $context
     * @return bool
     */
    static function __($content, $auditLevel = 1) {
        // make sure logging is enabled
        if (SITE_CONFIG_LOGGING_LOG_ENABLED != 'yes') {
            return;
        }

        // if this is a new session, add break
        if (self::$newSession) {
            self::breakInLogFile();
            self::$newSession = false;
        }

        // write to db and file
        self::toFile($content, true, $auditLevel);

        return true;
    }

    /**
     * Wrapper for main log function.
     *
     * @param string $content
     */
    static function info($content) {
        return self::__($content, 1);
    }

    /**
     * Wrapper for main log function.
     *
     * @param string $content
     */
    static function warning($content) {
        return self::__($content, 3);
    }

    /**
     * Wrapper for main log function.
     *
     * @param string $content
     */
    static function error($content) {
        return self::__($content, 9);
    }

    /**
     * Log to file
     *
     * @param array/string $content
     * @param bool $includeDate
     * @param string $context
     * @return bool
     */
    static function toFile($content, $includeDate = true, $auditLevel = 1) {
        $logFilePath = LOCAL_SITE_CONFIG_BASE_LOG_PATH . self::$context;

        // make sure the subdirectory exists
        if (!is_dir($logFilePath)) {
            @mkdir($logFilePath);
        }

        // log file name
        $logFilePath .= '/' . date('Ymd') . '.txt';
        $fh = fopen($logFilePath, 'a+');
        if (!$fh) {
            return false;
        }

        // if $content isn't an array, make it one
        if (!is_array($content)) {
            $content = array($content);
        }

        // loop items to be logged
        foreach ($content AS $contentItem) {
            if ($auditLevel == 9) {
                $contentItem = '*** ERROR *** ' . $contentItem;
            }
            elseif ($auditLevel == 3) {
                $contentItem = '*** WARNING *** ' . $contentItem;
            }
            
            $contentItem = '['.self::$logSessionTracker.'] - '.$contentItem;

            if ($includeDate) {
                $contentItem = date('Y-m-d H:i:s') . ' ' . $contentItem;
            }
            fwrite($fh, $contentItem . "\r\n");
        }

        fclose($fh);

        return true;
    }

    /**
     * Add break in log file
     *
     * @param string $context
     */
    static function breakInLogFile() {
        self::toFile('===========================================================', false);
    }

    /**
     * Set context of log files
     *
     * @param string $context
     */
    static function setContext($context) {
        self::$oldContext = self::$context;
        self::$context = $context;
    }

    /**
     * Get the current log file context
     *
     * @return string
     */
    static function getContext() {
        return self::$context;
    }

    static function revertContext() {
        if (self::$oldContext != null) {
            self::$context = self::$oldContext;
            self::$oldContext = null;
        }
    }

    /**
     * Set default parameters for logging
     *
     * @param array $defaults
     */
    static function setParamDefaults($defaults) {
        self::$paramsDefaults = $defaults;
    }

    /**
     * Get local param defaults
     *
     * @return array
     */
    static function getParamDefaults() {
        return self::$paramsDefaults;
    }

    /**
     * Reset param defaults to empty array
     */
    static function resetParamDefaults() {
        self::$paramsDefaults = [];
    }

    /**
     * Reads the last $lines within a file
     */
    static function readLogFile($file, $lines) {
        $handle = fopen($file, "r");
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];
        while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) {
                break;
            }
        }
        fclose($handle);

        return array_reverse($text);
    }

}
