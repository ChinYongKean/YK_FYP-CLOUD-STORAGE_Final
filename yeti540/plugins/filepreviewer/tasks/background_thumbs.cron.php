<?php

// Cron task to generate thumbnails of any uploaded files in the background
// Useful if you're allowing a lot of uploads via webdav where they're not automatically generated
// Should be run every minute via the command line like this:
// * * * * * php /path/to/your/html/plugins/filepreviewer/tasks/background_thumbs.cron.php
// This cron script should be run on only your main server

namespace Plugins\Filepreviewer\Tasks;

// include framework
use App\Core\Database;
use App\Core\Framework;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\LogHelper;
use App\Helpers\PluginHelper;
use App\Models\File;

require_once(realpath(dirname(__FILE__) . '/../../../app/core/Framework.class.php'));

// setup light environment
Framework::runLight();
$db = Database::getDatabase();

// setup logging
LogHelper::setContext('plugin_filepreviewer_background_thumbs');
LogHelper::info('Starting cron task.');

// preload plugin object for later
$pluginObj = PluginHelper::getInstance('filepreviewer');

// allow for the script to run for 10 minutes
set_time_limit(60 * 60 * 10);

// do as many as we can for 60 seconds
$secondsDone = 59;

// get latest image which does not have a thumbnail generated
$imageDataItems = $db->getRows('SELECT file.* '
        . 'FROM file '
        . 'LEFT JOIN plugin_filepreviewer_background_thumb ON file.id = plugin_filepreviewer_background_thumb.file_id '
        . 'WHERE plugin_filepreviewer_background_thumb.file_id IS NULL '
        . 'AND extension IN ('.FileHelper::getImageExtStringForSql().') '
        . 'AND status = "active" '
        . 'ORDER BY uploadedDate DESC '
        . 'LIMIT 5');
if ($imageDataItems) {
    foreach ($imageDataItems AS $imageData) {
        // add as processing to avoid overlaps
        $db->query('INSERT INTO plugin_filepreviewer_background_thumb '
                . '(file_id, thumb_status, date_added) VALUES '
                . '(' . (int) $imageData['id'] . ', \'processing\', NOW())');

        // log
        LogHelper::info('Set item processing: #' . (int) $imageData['id'] . ' (' . $imageData['originalFilename'] . ')');

        // hydrate file
        $file = File::hydrateSingleRecord($imageData);

        // tracking
        $started = microtime(true);

        // figure out which extension to use
        $o = 'jpg';
        if (in_array($file->extension, $pluginObj->getAnimatedFileExtensions())) {
            $o = 'gif';
        }

        // create thumbnail url
        $url = $pluginObj->createImageCacheUrl($file, 280, 280, 'middle', $o);

        // log
        LogHelper::info('Created thumbnail url: ' . $url);

        // request the url to generate the thumbnails, just like on the website ui
        $rs = CoreHelper::getRemoteUrlContent($url);

        // tracking
        $ended = microtime(true);
        $processingTime = ($ended - $started);

        // update result
        $newStatus = 'created';
        if (($rs === false) || (strlen($rs) == 0)) {
            $newStatus = 'failed';

            // log
            LogHelper::info('Failed getting url contents in time.');
        }
        $db->query('UPDATE plugin_filepreviewer_background_thumb '
                . 'SET thumb_status = \'' . $newStatus . '\', '
                . 'processing_time = \'' . number_format($processingTime, 5) . '\' '
                . 'WHERE file_id = ' . (int) $file->id . ' '
                . 'LIMIT 1');

        $secondsDone = (int) $secondsDone - (int) ceil($processingTime);
        if ($secondsDone < 0) {
            // exit if we've already done 60 seconds worth
            LogHelper::info('Ended cron task after 60 seconds of processing.');
            exit;
        }
    }
}

LogHelper::info('Ended cron task after all pending images processed.');
