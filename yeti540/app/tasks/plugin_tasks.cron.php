<?php

/*
 * Title: Process Plugin Tasks
 * Author: Yetishare.com
 * Period: Run every hour
 * 
 * Description:
 * Script to process any tasks within plugins
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php plugin_tasks.cron.php
 * 
 * Configure as a cron like this:
 * 0 * * * * php /path/to/yetishare/app/tasks/plugin_tasks.cron.php
 */

namespace App\Tasks;

// include framework
use App\Core\Framework;
use App\Helpers\BackgroundTaskHelper;
use App\Helpers\PluginHelper;
require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// setup light environment
Framework::runLight();

// background task logging
BackgroundTaskHelper::start();

// do any batch tasks in the plugins
PluginHelper::includeAppends('batch_tasks');

// background task logging
BackgroundTaskHelper::end();