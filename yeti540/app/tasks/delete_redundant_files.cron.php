<?php

/*
 * Title: Delete Redundant Files
 * Author: Yetishare.com
 * Period: Every 1 hour
 * 
 * Description:
 * Script to delete any files which are no longer accessed
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php delete_redundant_files.cron.php
 * 
 * Configure as a cron like this:
 * 0 * * * * php /path/to/yetishare/app/tasks/delete_redundant_files.cron.php
 */

namespace App\Tasks;

// include framework
use App\Core\Framework;
use App\Helpers\BackgroundTaskHelper;
use App\Helpers\FileHelper;
require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// setup light environment
Framework::runLight();

// background task logging
BackgroundTaskHelper::start();

// delete any old files
FileHelper::deleteRedundantFiles();

// clear trash folders
FileHelper::deleteTrashedFiles();

// background task logging
BackgroundTaskHelper::end();