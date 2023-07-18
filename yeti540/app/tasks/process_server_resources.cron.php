<?php

/*
 * Title: Process local/direct server resource usage stats
 * Author: Yetishare.com
 * Period: Run every minute
 * 
 * Description:
 * Script to update local/direct server resource usage stats, if enabled.
 *
 * Should be set to run on any "local" or "direct" file servers.
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php process_server_resources.cron.php
 * 
 * Configure as a cron like this:
 * * * * * * php /path/to/yetishare/app/tasks/process_server_resources.cron.php
 */

namespace App\Tasks;

// include framework
use App\Core\Framework;
use App\Helpers\BackgroundTaskHelper;
use App\Helpers\FileHelper;
use App\Helpers\ServerResourceHelper;
require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// how often (in seconds) to update resource stats into the database
define('UPDATE_INTERVAL', 15);

// setup light environment
Framework::runLight();

// background task logging
BackgroundTaskHelper::start();

// make sure the server has the monitor option enabled
$currentServer = FileHelper::getCurrentServerDetails();
if((int)$currentServer['monitor_server_resources'] !== 1) {
    // background task logging
    BackgroundTaskHelper::end();
    exit;
}

// loop until 60 seconds
$loop = true;
$totalTime = 0;
while($loop) {
    // update current server resource stats
    ServerResourceHelper::logResources();

    // pause for UPDATE_INTERVAL
    sleep(UPDATE_INTERVAL);

    // track total time
    $totalTime += UPDATE_INTERVAL;
    if($totalTime >= 60) {
        $loop = false;
    }
}

// background task logging
BackgroundTaskHelper::end();