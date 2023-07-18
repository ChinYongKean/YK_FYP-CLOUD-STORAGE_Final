<?php

/*
 * Title: Process Background Remote Url Downloads
 * Author: Yetishare.com
 * Period: Run every minute
 * 
 * Description:
 * Script to process any pending remote file downloads.
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php process_remote_file_downloads.cron.php
 * 
 * Configure as a cron like this:
 * * * * * * php /path/to/yetishare/app/tasks/process_remote_file_downloads.cron.php
 */

namespace App\Tasks;

// include framework
use App\Core\Database;
use App\Core\Framework;
use App\Helpers\BackgroundTaskHelper;
use App\Helpers\FileHelper;
use App\Helpers\PluginHelper;
use App\Helpers\UserHelper;
use App\Models\User;
use App\Services\Uploader;

require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// setup light environment
Framework::runLight();

// get database
$db = Database::getDatabase();

// background task logging
BackgroundTaskHelper::start();

// limit concurrent downloads
define('CONCURRENT_DOWNLOADS', 30);

// clean up any old stuck ones
$db->query('UPDATE remote_url_download_queue '
        . 'SET job_status=\'failed\', finished=NOW(), notes="Previously stuck item/timed out." '
        . 'WHERE job_status=\'downloading\' '
        . 'AND started < DATE_SUB(NOW(), INTERVAL 1 day) '
        . 'LIMIT 10');

// check concurrent downloads
$totalDownloads = (int) $db->getValue('SELECT COUNT(id) '
                . 'FROM remote_url_download_queue '
                . 'WHERE job_status=\'downloading\'');
if ($totalDownloads <= CONCURRENT_DOWNLOADS) {
    // sync all data
    $urlDownloadData = $db->getRow('SELECT * '
            . 'FROM remote_url_download_queue '
            . 'WHERE job_status=\'pending\' '
            . 'AND file_server_id = ' . (int) FileHelper::getCurrentServerId() . ' '
            . 'ORDER BY `created` ASC '
            . 'LIMIT 1');
    if ($urlDownloadData) {
        // set pending
        $db->query('UPDATE remote_url_download_queue '
                . 'SET job_status=\'processing\', started=NOW() '
                . 'WHERE id=' . (int) $urlDownloadData['id'] . ' '
                . 'LIMIT 1');

        // update any completed downloading
        $db->query('UPDATE remote_url_download_queue '
                . 'SET job_status=\'downloading\' '
                . 'WHERE id=' . (int) $urlDownloadData['id'] . ' '
                . 'LIMIT 1');

        // get user max filesize
        $userObj = User::loadOneById((int) $urlDownloadData['user_id']);

        // start uploader class
        $upload_handler = new Uploader(array(
            'folder_id' => (int) $urlDownloadData['folder_id'],
            'user_id' => (int) $urlDownloadData['user_id'],
            'background_queue_id' => (int) $urlDownloadData['id'],
            'max_file_size' => UserHelper::getMaxUploadFilesize($userObj->level_id),
            'upload_source' => 'remote',
        ));

        // include plugin code
        $url = $urlDownloadData['url'];
        $params = PluginHelper::callHook('urlUploadHandler', array(
            'url' => $url,
            'rowId' => 0,
            'urlDownloadData' => $urlDownloadData,
            )
        );
        $url = $params['url'];

        // start download
        $upload_handler->handleRemoteUrlUpload($url);

        // on complete
        if ($upload_handler->fileUpload->error != null) {
            $db->query('UPDATE remote_url_download_queue '
                    . 'SET job_status=\'failed\', finished=NOW(), notes=' . $db->quote($upload_handler->fileUpload->error) . ' '
                    . 'WHERE id=' . (int) $urlDownloadData['id'] . ' '
                    . 'LIMIT 1');
        }
        else {
            $db->query('UPDATE remote_url_download_queue '
                    . 'SET job_status=\'complete\', finished=NOW(), new_file_id=' . (int) $upload_handler->fileUpload->file_id . ' '
                    . 'WHERE id=' . (int) $urlDownloadData['id'] . ' '
                    . 'LIMIT 1');
        }
    }
}

// background task logging
BackgroundTaskHelper::end();
