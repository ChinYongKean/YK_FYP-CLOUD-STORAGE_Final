<?php

/*
 * Title: Auto Prune Cron Script
 * Author: Yetishare.com
 * Period: Run once a day
 * 
 * Description:
 * Script to clear old temp data within the database.
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php auto_prune.cron
 * 
 * Configure as a cron like this:
 * 0 0 * * * php /path/to/yetishare/app/tasks/auto_prune.cron.php
 */

namespace App\Tasks;

// include framework
use App\Core\Database;
use App\Core\Framework;
use App\Helpers\BackgroundTaskHelper;
use App\Helpers\CrossSiteActionHelper;
use App\Helpers\UserHelper;
require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// setup light environment
Framework::runLight();

// get database
$db = Database::getDatabase();

// background task logging
BackgroundTaskHelper::start();

// delete old sessions
$db->query('DELETE FROM `sessions` '
        . 'WHERE `updated_on` < :updated_on', array(
            'updated_on' => time() - (60*60*24*2),
            )
        );

// delete old stats, comment out to remove all stats over 1 year old
//$db->query('DELETE FROM `stats` WHERE download_date < DATE_SUB(NOW(), INTERVAL 1 YEAR)');

// delete old download tracker
$db->query("DELETE FROM download_tracker "
        . "WHERE date_started < DATE_SUB(NOW(), INTERVAL ".(int)DOWNLOAD_TRACKER_PURGE_PERIOD." day)");

// delete old download tokens
$db->query('DELETE FROM download_token '
        . 'WHERE expiry < :expiry', array(
            'expiry' => date('Y-m-d H:i:s'),
            )
        );

// delete old internal notifications
$db->query('DELETE FROM `internal_notification` '
        . 'WHERE date_added < DATE_SUB(NOW(), INTERVAL 90 DAY)');

// delete old login success
$db->query('DELETE FROM `login_success` '
        . 'WHERE date_added < DATE_SUB(NOW(), INTERVAL 180 DAY)');

// delete old login error
$db->query('DELETE FROM `login_failure` '
        . 'WHERE date_added < DATE_SUB(NOW(), INTERVAL 180 DAY)');

// delete old pending premium orders
$db->query('DELETE FROM `premium_order` '
        . 'WHERE date_created < DATE_SUB(NOW(), INTERVAL 7 DAY) '
        . 'AND order_status = \'pending\'');

// delete old file actions, kept for 6 months
$db->query('DELETE FROM `file_action` '
        . 'WHERE date_created < DATE_SUB(NOW(), INTERVAL 180 DAY)');

// delete cross_site_action
$db->query('DELETE FROM `cross_site_action` '
        . 'WHERE date_added < DATE_SUB(NOW(), INTERVAL 30 DAY)');

// remove any old cross site action data
CrossSiteActionHelper::purgeOldData();

// disable any expired accounts (mainly used for trial accounts)
UserHelper::disableExpiredAccounts();

// delete any old server resource data older than 7 days
$db->query('DELETE FROM `file_server_resource_usage` '
    . 'WHERE date_created < DATE_SUB(NOW(), INTERVAL 7 DAY)');

// delete any background_task_log entries older than 1 month
$db->query('DELETE FROM `background_task_log` '
    . 'WHERE end_time < DATE_SUB(NOW(), INTERVAL 30 DAY)');

// background task logging
BackgroundTaskHelper::end();