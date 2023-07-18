<?php

/*
 * Title: Process file action queue
 * Author: Yetishare.com
 * Period: Run every 5 minutes
 * 
 * Description:
 * Script to process any pending actions in the file_action table queue
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php process_file_queue.cron.php
 * 
 * Configure as a cron like this:
 * *\/5 * * * * php /path/to/yetishare/app/tasks/process_file_queue.cron.php
 */

namespace App\Tasks;

// include framework
use App\Core\Framework;
use App\Helpers\BackgroundTaskHelper;
use App\Helpers\FileActionHelper;
use App\Helpers\FileHelper;
require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// setup light environment
Framework::runLight();

// background task logging
BackgroundTaskHelper::start();          
                            
// process delete queue
FileActionHelper::processQueue('delete');

// process move queue
FileActionHelper::processQueue('move', 1);

// process restoration queue
FileActionHelper::processQueue('restore', 50);

// update storage stats
FileHelper::updateFileServerStorageStats();

// background task logging
BackgroundTaskHelper::end();