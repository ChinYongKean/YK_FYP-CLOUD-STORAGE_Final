<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\BackgroundTask;
use App\Models\BackgroundTaskLog;

/**
 * Background Task Helper for managing task logging
 */
class BackgroundTaskHelper
{
    // used to track current BackgroundTaskLog
    private static $backgroundTaskLog = null;

    public static function start() {
        // load relevant background task
        $backgroundTask = BackgroundTask::loadOne('task', self::getTaskName());
        if (!$backgroundTask) {
            // create task entry if it doesn't already exist
            $backgroundTask = BackgroundTask::create();;
            $backgroundTask->task = self::getTaskName();
        }
        $backgroundTask->last_update = CoreHelper::sqlDateTime();
        $backgroundTask->status = 'running';
        $backgroundTask->save();

        // log record
        self::$backgroundTaskLog = new BackgroundTaskLog();
        self::$backgroundTaskLog->task_id = $backgroundTask->id;
        self::$backgroundTaskLog->start_time = CoreHelper::sqlDateTime();
        self::$backgroundTaskLog->server_name = self::getCurrentServerName();
        self::$backgroundTaskLog->status = 'started';
        self::$backgroundTaskLog->save();

        // return the $backgroundTaskLog object for tracking
        return self::$backgroundTaskLog;
    }

    public static function end() {
        // force a reconnect to the DB to avoid disconnects after long processes
        Database::getDatabase(true, true);
        
        // update end time
        // load relevant background task
        $backgroundTask = BackgroundTask::loadOne('task', self::getTaskName());
        if (!$backgroundTask) {
            // create task entry if it doesn't already exist
            $backgroundTask = BackgroundTask::create();
            $backgroundTask->task = self::getTaskName();
        }
        $backgroundTask->last_update = CoreHelper::sqlDateTime();
        $backgroundTask->status = 'finished';
        $backgroundTask->save();

        // log record
        self::$backgroundTaskLog->end_time = CoreHelper::sqlDateTime();
        self::$backgroundTaskLog->status = 'finished';
        self::$backgroundTaskLog->save();
    }

    public static function getTaskName() {
        // get requesting task name
        $taskName = self::getCallingCronName();
        if (strlen($taskName) == 0) {
            $taskName = 'unknown';
        }

        return $taskName;
    }

    public static function getCallingCronName() {
        // figure out the name of the cron calling this method
        $callers = debug_backtrace();
        $filePath = $callers[count($callers)-1]['file'];
        $filename = basename($filePath);
        if (strlen($filename)) {
            return $filename;
        }

        return false;
    }

    public static function getCurrentServerName() {
        $hostName = gethostname();
        if (strlen($hostName)) {
            return $hostName;
        }

        $hostName = php_uname('n');
        if (strlen($hostName)) {
            return $hostName;
        }

        return 'Unknown';
    }

}
