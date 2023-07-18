<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\CacheHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\ValidationHelper;
use App\Models\File;

class ZipFile
{
    public $zip = null;
    public $fullZipPathAndFilename = null;
    public $tmpLocalCachePath = null;
    public $tmpLocalFullPath = null;
    // set max filesize allowed for each file, 500MB
    public $maxPermittedEachFileBytes = 1024 * 1024 * 500;

    function __construct($zipFilename) {
        $this->zip = new \ZipArchive();
        $zipStoragePath = CACHE_DIRECTORY_ROOT . '/zip';
        if (!is_dir($zipStoragePath)) {
            mkdir($zipStoragePath);
        }

        $this->fullZipPathAndFilename = $zipStoragePath . '/' . $zipFilename . '.zip';
        if (file_exists($this->fullZipPathAndFilename)) {
            @unlink($this->fullZipPathAndFilename);
        }

        // create tmp local directory for tmp files
        $this->tmpLocalCachePath = 'zip/_tmp/' . $zipFilename;
        $this->tmpLocalFullPath = CACHE_DIRECTORY_ROOT . '/' . $this->tmpLocalCachePath . '/';

        // make sure the folder path exists
        CoreHelper::checkCreateDirectory($this->tmpLocalFullPath);

        // setup zip
        if ($this->zip->open($this->fullZipPathAndFilename, \ZipArchive::CREATE) !== true) {
            echo TranslateHelper::t('account_home_failed_creating_zip_file', 'Error: Failed creating zip file: ' . $this->fullZipPathAndFilename);
            exit;
        }
    }

    public function addFileAndFolders($folderData, $baseFolderName = '') {
        foreach ($folderData AS $folderItem) {
            // add directory
            $this->zip->addEmptyDir($baseFolderName . $folderItem['basePath'] . $folderItem['folderName']);

            // check for subfolders
            if ((isset($folderItem['folders'])) && (count($folderItem['folders']))) {
                $this->addFileAndFolders($folderItem['folders'], $baseFolderName);
            }

            // output progress
            self::outputBufferToScreen(TranslateHelper::t('account_home_added_folder_to_zip', '- Added folder ') . $baseFolderName . $folderItem['basePath'] . $folderItem['folderName'] . '/');

            // add files into directory
            $this->addFilesTopZip($folderItem, $baseFolderName . $folderItem['basePath'] . $folderItem['folderName'] . '/');
        }
    }

    public function close() {
        // save the zip
        $this->zip->close();

        // remove any temp files
        if(file_exists($this->tmpLocalFullPath)) {
            CacheHelper::removeCacheSubFolder($this->tmpLocalCachePath);
        }
    }

    public function getFullFilePath() {
        return $this->fullZipPathAndFilename;
    }

    public function addFilesTopZip($loopBaseData, $basePath = '') {
        if (empty($loopBaseData['files'])) {
            return true;
        }

        foreach ($loopBaseData['files'] AS $file) {
            $this->addSingleFileToZip($file, $basePath);
        }

        return true;
    }

    public function addSingleFileToZip($file, $basePath = '') {
        // make sure filesize is less than $this->maxPermittedEachFileBytes
        if ($file->getPrimaryFileSize() > $this->maxPermittedEachFileBytes) {
            // output progress
            self::outputBufferToScreen(TranslateHelper::t('account_home_file_item_too_large_for_zip', '- File is too large to include in zip file ([[[FILE_NAME]]], [[[FILE_SIZE_FORMATTED]]])', array('FILE_NAME' => $basePath . $file->originalFilename, 'FILE_SIZE_FORMATTED' => CoreHelper::formatSize($file->fileSize))), 'red');

            return false;
        }

        // output progress
        self::outputBufferToScreen(TranslateHelper::t('account_home_getting', '- Getting ') . $basePath . $file->originalFilename . ' (' . CoreHelper::formatSize($file->getPrimaryFileSize()) . ') ...', null, ' ');

        // if file is stored locally, add by file path
        $rs = false;
        if ($file->fileIsOnCurrentServer() === true) {
            // output progress
            self::outputBufferToScreen('Done. Adding to zip file...', null, ' ');

            // add file to zip from the file system
            $rs = $this->zip->addFile($file->getFullFilePath(), $basePath . $file->originalFilename);
        }
        else {
            // download to temp local file while creating the zip
            $tmpLocalFile = $this->tmpLocalFullPath . md5($file->id . microtime());

            // load based on download token, create download url without any IP restrictions
            $downloadUrl = $file->generateDirectDownloadUrlForMedia(false);

            // get file content
            CoreHelper::getRemoteUrlContent($downloadUrl, $tmpLocalFile);
            if (file_exists($tmpLocalFile)) {
                // output progress
                self::outputBufferToScreen('Done. Adding to zip file...', null, ' ');

                // add file to zip
                $rs = $this->zip->addFile($tmpLocalFile, $basePath . $file->originalFilename);
            }
            else {
                // output progress
                self::outputBufferToScreen('Error: Failed getting file contents (' . $file->originalFilename . ').', 'red');
            }
        }

        if ($rs) {
            // output progress
            self::outputBufferToScreen('File added.', 'green');
        }
        else {
            // output progress
            self::outputBufferToScreen('Error: Failed adding \'' . $file->originalFilename . '\' to zip file.', 'red');
        }
    }

    static function getFolderStructureAsArray($rootFolderId, $startFolderId, $currentUserId = null, $basePathStr = '') {
        // setup database
        $db = Database::getDatabase();

        // load folder infomation
        $folderData = $db->getRow('SELECT folderName, parentId, userId '
                . 'FROM file_folder '
                . 'WHERE id = ' . (int) $startFolderId . ' '
                . 'LIMIT 1');
        if ($currentUserId === null) {
            $currentUserId = $folderData['userId'];
        }

        // get file data
        $fileData = [];
        $fileData[$folderData['folderName']] = array(
            'files' => FileHelper::loadAllActiveByFolderId($startFolderId),
            'folderName' => $folderData['folderName'],
            'basePath' => $basePathStr,
            'folders' => array(),
        );

        // get child folders and files
        $subArr = [];
        $folders = $db->getRows('SELECT id '
                . 'FROM file_folder '
                . 'WHERE parentId = ' . (int) $startFolderId . ' '
                . 'AND (userId = ' . (int) $currentUserId . ' '
                . 'OR (file_folder.id IN (SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE file_folder_share.shared_with_user_id = ' . (int) $currentUserId . ' '
                . 'AND share_permission_level IN ("upload_download", "all")))'
                . ') '
                . 'ORDER BY folderName');
        if ($folders) {
            foreach ($folders AS $folder) {
                $rs = self::getFolderStructureAsArray($rootFolderId, $folder['id'], $currentUserId, ($startFolderId != $rootFolderId ? ($basePathStr . $folderData['folderName'] . '/') : ''));
                $subArr = $subArr + $rs;
            }
        }

        $fileData[$folderData['folderName']]['folders'] = $subArr;

        return $fileData;
    }

    static function getTotalFileCount($loopBaseData) {
        $total = 0;
        if (is_array($loopBaseData['files']) && count($loopBaseData['files']) > 0) {
            $total = $total + count($loopBaseData['files']);
        }

        if (is_array($loopBaseData['folders']) && count($loopBaseData['folders'])) {
            foreach ($loopBaseData['folders'] AS $folder) {
                $total = $total + self::getTotalFileCount($folder);
            }
        }

        return $total;
    }

    static function getTotalFileSize($loopBaseData) {
        $total = 0;
        if (is_array($loopBaseData['files']) && count($loopBaseData['files']) > 0) {
            foreach ($loopBaseData['files'] AS $file) {
                $total = $total + $file->getPrimaryFileSize();
            }

            if (is_array($loopBaseData['folders']) && count($loopBaseData['folders'])) {
                foreach ($loopBaseData['folders'] AS $folder) {
                    $total = $total + self::getTotalFileSize($folder);
                }
            }
        }

        return $total;
    }

    // local helper functions
    static function outputInitialBuffer() {
        // 1KB of initial data, required by Webkit browsers
        echo "<span><!--" . str_repeat("0", 1000) . "--></span>";
        ob_flush();
        flush();
    }

    static function outputBufferToScreen($str, $colour = null, $lineBreak = '<br/>') {
        if ($colour !== null) {
            echo '<span style="color: ' . $colour . '">';
        }
        echo ValidationHelper::safeOutputToScreen($str);
        if ($colour !== null) {
            echo '</span>';
        }
        self::scrollIframe();
        echo $lineBreak;
        ob_flush();
        flush();
    }

    static function scrollIframe() {
        echo '<script>window.scrollBy(0,50);</script>';
    }

    static function purgeOldZipFiles() {
        // loop cache zip folder and clear any older than 3 days old
        $zipStoragePath = CACHE_DIRECTORY_ROOT . '/zip/';
        foreach (glob($zipStoragePath . "*.zip") as $file) {
            // protect the filename
            if (filemtime($file) < time() - 60 * 60 * 24 * 3) {
                // double check we're in the zip cache store
                if (substr($file, 0, strlen(CACHE_DIRECTORY_ROOT . '/zip/')) == CACHE_DIRECTORY_ROOT . '/zip/') {
                    @unlink($file);
                }
            }
        }
    }

}
