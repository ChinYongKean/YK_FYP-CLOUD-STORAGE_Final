<?php

// plugin namespace

namespace Plugins\Fileimport;

// core includes
use App\Core\Database;
use App\Helpers\UserActionLogHelper;
use App\Models\File;
use App\Helpers\CoreHelper;
use App\Helpers\FileActionHelper;
use App\Helpers\FileHelper;
use App\Helpers\FileFolderHelper;
use App\Helpers\PluginHelper;
use App\Services\Plugin;
use App\Services\Uploader;
use Plugins\Fileimport\PluginConfig;

class PluginFileimport extends Plugin {

    // set folder privacy
    public $isFolderPublic = 0;

    public function __construct() {
        // load plugin config
        $this->config = (new PluginConfig())->getPluginConfig();

        // ensure we're using UTF8
        setlocale(LC_ALL, 'en_US.UTF-8');

        // don't copy the file itself, only create the DB entry
        defined('DB_ONLY') or define('DB_ONLY', true);
    }

    public function getPluginDetails() {
        return $this->config;
    }

    public function registerRoutes(\FastRoute\RouteCollector $r) {
        // register plugin routes
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/plugin/' . $this->config['folder_name'] . '/settings', '\plugins\\' . $this->config['folder_name'] . '\controllers\admin\PluginController/pluginSettings');
        $r->addRoute(['GET'], '/' . ADMIN_FOLDER_NAME . '/plugin/' . $this->config['folder_name'] . '/ajax/folder_listing', '\plugins\\' . $this->config['folder_name'] . '\controllers\admin\PluginController/ajaxFolderListing');
        $r->addRoute(['GET'], '/' . ADMIN_FOLDER_NAME . '/plugin/' . $this->config['folder_name'] . '/download_import_script', '\plugins\\' . $this->config['folder_name'] . '\controllers\admin\PluginController/downloadImportScript');
        $r->addRoute(['GET'], '/' . ADMIN_FOLDER_NAME . '/plugin/' . $this->config['folder_name'] . '/process_file_import', '\plugins\\' . $this->config['folder_name'] . '\controllers\admin\PluginController/processFileImport');
    }

    public function getLowestWritableBasePath() {
        // find the lowest folder this setup has access to
        // loop path until we can not read any more
        $failsafe = 0;
        $baseFolder = DOC_ROOT;
        $lastBaseFolder = $baseFolder;
        while (is_readable($baseFolder) && $failsafe < 20) {
            $lastBaseFolder = $baseFolder;
            $baseFolder = dirname($baseFolder);
            if (strlen(trim($baseFolder)) == 0) {
                $failsafe = 20;
            }
            $failsafe++;
        }

        // make sure it ends with a forward slash
        if (substr($lastBaseFolder, strlen($lastBaseFolder) - 1, 1) != DIRECTORY_SEPARATOR) {
            $lastBaseFolder .= DIRECTORY_SEPARATOR;
        }

        return $lastBaseFolder;
    }

    // local functions
    public function importFiles($localPath, $userId, $folderId, $folderPath = '') {
        // setup database
        $db = Database::getDatabase();

        // get items
        $items = CoreHelper::getDirectoryListing($localPath);
        if (count($items)) {
            foreach ($items AS $item) {
                if (is_dir($item)) {
                    // directory, first make sure we have the folder
                    $folderName = basename($item);
                    $childFolderId = (int) $db->getValue('SELECT id '
                                    . 'FROM file_folder '
                                    . 'WHERE userId = ' . (int) $userId . ' '
                                    . 'AND folderName = ' . $db->quote($folderName) . ' '
                                    . 'AND parentId ' . ((int) $folderId === 0 ? ' IS NULL' : ('=' . (int) $folderId)) . ' '
                                    . 'AND status = "active" '
                                    . 'LIMIT 1');
                    if (!$childFolderId) {
                        $db->query('INSERT INTO file_folder '
                                . '(folderName, isPublic, userId, addedUserId, parentId, urlHash, date_added) VALUES '
                                . '(:folderName, :isPublic, :userId, :addedUserId, :parentId, :urlHash, NOW())', array(
                            'folderName' => $folderName,
                            'isPublic' => $this->isFolderPublic,
                            'userId' => $userId,
                            'addedUserId' => $userId,
                            'urlHash' => FileFolderHelper::generateRandomFolderHash(),
                            'parentId' => $folderId,
                                )
                        );
                        $childFolderId = $db->insertId();

                        // user action logs
                        UserActionLogHelper::log('Folder imported', 'FOLDER', 'CREATE', [
                            'folder_id' => $childFolderId,
                            'data' => [
                                'name' => $folderName,
                                'original_path' => substr($item, strlen($localPath)),
                            ],
                        ]);
                    }

                    // loop again for files within
                    $this->importFiles($item, $userId, $childFolderId, $folderPath . '/' . $folderName);
                } else {
                    // file
                    $rs = $this->importFile($item, $userId, $folderId, $folderPath);
                    if ($rs) {
                        $this->output('Imported ' . $item);
                    } else {
                        $this->output('Error: File above failed import (' . $item . ')');
                    }
                }
            }
        }

        // update file size on folder
        if((int)$folderId > 0) {
            FileFolderHelper::updateFolderFilesize((int) $folderId);
        }
    }

    public function importFile($filePath, $userId, $folderId, $folderPath = '') {
        // setup database, ensure we reconnect to avoid timeouts
        $db = Database::getDatabase(true);

        // get original filename
        $pathParts = pathinfo($filePath);
        $filename = $pathParts['filename'];
        if (isset($pathParts['extension']) && strlen($pathParts['extension'])) {
            $filename .= '.' . $pathParts['extension'];
        }

        // remove any invalid characters in the filename
        $filename = FileHelper::makeFilenameSafe($filename);

        // don't re-import if we already have the file
        $foundFile = (int) $db->getValue('SELECT f.id '
                        . 'FROM file f '
                        . 'LEFT JOIN file_artifact fa ON f.id = fa.file_id AND fa.file_artifact_type = "primary" '
                        . 'WHERE f.folderId ' . ((int) $folderId === 0 ? ' IS NULL' : ('=' . (int) $folderId)) . ' '
                        . 'AND f.originalFilename = :original_filename '
                        . 'AND fa.file_size = :file_size '
                        . 'AND f.status = "active" '
                        . 'LIMIT 1', array(
                    'original_filename' => $filename,
                    'file_size' => filesize($filePath),
        ));
        if ($foundFile) {
            $this->output('Info: File already exists. (' . $filePath . ')');
            return true;
        }

        // get mime type
        $mimeType = FileHelper::estimateMimeTypeFromExtension($filename, 'application/octet-stream');
        if (($mimeType == 'application/octet-stream') && (class_exists('finfo', false))) {
            $finfo = new \finfo();
            $mimeType = $finfo->file($filePath, FILEINFO_MIME);
        }

        if (DB_ONLY === true) {
            // insert entry into the DB rather than move etc
            try {
                // store in db
                $file = FileHelper::createFileEntry([
                    'original_filename' => $filename,
                    'extension' => $pathParts['extension'],
                    'file_type' => $mimeType,
                    'temp_file_path' => $filePath,
                    'file_size' => filesize($filePath),
                    'local_file_path' => 'imported' . $folderPath . '/' . $filename,
                    'user_id' => $userId,
                    'file_server_id' => FileHelper::getCurrentServerId(),
                    'folder_id' => $folderId,
                    'upload_source' => 'fileimport',
                ]);

                // user action logs
                UserActionLogHelper::log('File imported', 'FILE', 'UPLOAD', [
                    'file_id' => $file->id,
                    'data' => [
                        'name' => $file->originalFilename,
                        'size' => $file->getFormattedFilesize(),
                        'folder' => $file->getFolderPath(),
                    ],
                ]);
            } catch (\Exception $e) {
                $this->output($e->getMessage());

                return false;
            }
        } else {
            // setup uploader
            $uploadHandler = new Uploader(array(
                'folder_id' => $folderId,
                'user_id' => $userId,
                'upload_source' => 'fileimport',
            ));

            $fileUpload = new \stdClass();
            $fileUpload->name = stripslashes($filename);
            $fileUpload->size = filesize($filePath);
            $fileUpload->type = $mimeType;
            $fileUpload->error = null;
            $fileUpload = $uploadHandler->moveIntoStorage($fileUpload, $filePath, true); // keeps the original
            if (strlen($fileUpload->error)) {
                $this->output('Error: ' . $fileUpload->error);
                return false;
            }
        }

        return true;
    }

    public function output($msg = '', $exit = false) {
        echo $msg;
        if (defined('CLI_MODE') && CLI_MODE == true) {
            echo "\n";
        } else {
            echo "<br/>";
            ob_start();
            ob_end_flush();
        }
        if ($exit == true) {
            die();
        }
    }

}
