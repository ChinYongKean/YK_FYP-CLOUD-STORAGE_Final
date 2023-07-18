<?php

/*
 * Title: Downgrade Expired Accounts
 * Author: Yetishare.com
 * Period: Every 15 monutes
 * 
 * Description:
 * Script to downgrade any accounts which are no longer premium
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php downgrade_accounts.cron.php
 * 
 * Configure as a cron like this:
 * 0 0 * * * php /path/to/yetishare/app/tasks/downgrade_accounts.cron.php
 */

namespace App\Tasks;

// include framework
use App\Core\Framework;
use App\Helpers\BackgroundTaskHelper;
use App\Helpers\UserHelper;
require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// setup light environment
Framework::runLight();

// background task logging
BackgroundTaskHelper::start();

// downgrade accounts
UserHelper::downgradeExpiredAccounts();

// background task logging
BackgroundTaskHelper::end();