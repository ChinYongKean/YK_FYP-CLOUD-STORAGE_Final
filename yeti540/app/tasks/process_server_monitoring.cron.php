<?php

/*
 * Title: Process direct server monitoring
 * Author: Yetishare.com
 * Period: Run every minute
 * 
 * Description:
 * Script to update direct server availability
 *
 * Should be set to run only on the main server.
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php process_server_monitoring.cron.php
 * 
 * Configure as a cron like this:
 * * * * * * php /path/to/yetishare/app/tasks/process_server_monitoring.cron.php
 */

namespace App\Tasks;

// include framework
use App\Core\Framework;
use App\Helpers\BackgroundTaskHelper;
use App\Helpers\ServerResourceHelper;
require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// setup light environment
Framework::runLight();

// background task logging
BackgroundTaskHelper::start();

// update server availabilities
ServerResourceHelper::updateAllServerAvailability();

// background task logging
BackgroundTaskHelper::end();