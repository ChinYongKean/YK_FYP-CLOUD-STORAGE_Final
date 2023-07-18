<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\File;
use App\Models\User;

/**
 * Admin API class for remote file management.
 */
class AdminApiHelper
{
    public static $_internalStatusTracker = false;

    /*
     * Validate API access.
     */

    public static function validateAccess($apiKey, $userName) {
        // make sure username and key is valid, currently this API is for admin users only
        return User::loadOneByClause('apikey = :apiKey '
                        . 'AND username = :username '
                        . 'AND level_id IN (SELECT id FROM user_level WHERE level_type = \'admin\') '
                        . 'AND status = \'active\'', array(
                    'apiKey' => $apiKey,
                    'username' => $userName,
        ));
    }

    /**
     * Move a file to another server
     *
     * @param array $params
     * @return bool|int|string
     */
    public static function apiMovefile(array $params = []) {
        // make sure we have the file_id
        $fileId = null;
        if (isset($params['file_id'])) {
            $fileId = (int) $params['file_id'];
        }

        if ((int) $fileId == 0) {
            return self::produceError('Error: Method requires a valid file_id.');
        }

        // load file
        $file = File::loadOneById($fileId);

        // make sure the file is active
        if ($file->status !== 'active') {
            return self::produceError('Error: File is not active.');
        }

        // this method can only be used on the receiving server, if this isn't is, forward the request
        $newServerDetails = null;
        if (($params['server_id'] != FileHelper::getCurrentServerId()) && (!isset($params['___internal_redirect_move_file']))) {
            // set flag to stop loops of doom!
            $params['___internal_redirect_move_file'] = true;

            // create new url
            $correctServerPath = FileHelper::getFileDomainAndPath($file->id, $params['server_id'], true);
            $url = self::createApiUrl($correctServerPath, $params['key'], $params['username'], 'movefile', $params);
            $rs = CoreHelper::getRemoteUrlContent($url);
            if (strlen($rs) == 0) {
                // try to get the url headers for better errors
                $headers = get_headers($url);
                if (isset($headers[0])) {
                    return self::produceError('Error: Problem contacting new file server to move file. ' . $headers[0]);
                }
                return self::produceError('Error: Problem contacting new file server to move file, does the api exist?');
            }

            self::$_internalStatusTracker = true;

            return $rs;
        }
        elseif ($params['server_id'] != FileHelper::getCurrentServerId()) {
            // fail for non-local servers
            $newServerDetails = FileHelper::loadServerDetails($params['server_id']);
            if ($newServerDetails['serverType'] !== 'local') {
                return self::produceError('(' . FileHelper::getCurrentServerId() . ' ' . $params['server_id'] . ') Error: Problem contacting new file server to move file.');
            }
        }

        // by this stage we should be on the receiving file server, get the file contents
        if (!$newServerDetails) {
            $newServerDetails = FileHelper::loadServerDetails($params['server_id']);
        }

        $storagePath = '';
        if (strlen($newServerDetails['storagePath'])) {
            $storagePath = FileServerHelper::getDocRoot($params['server_id']) . '/' . $newServerDetails['storagePath'];
        }
        if (substr($storagePath, strlen($storagePath) - 1, 1) == '/') {
            $storagePath = substr($storagePath, 0, strlen($storagePath) - 1);
        }
        $storagePath .= '/';

        // currently this api method only works for local or direct file servers
        if (($newServerDetails['serverType'] !== 'direct') && ($newServerDetails['serverType'] !== 'local')) {
            return self::produceError('Error: Only \'direct\' or \'local\' file servers support this API method.');
        }

        // check if already on this server
        if ($file->getPrimaryServerId() == $params['server_id']) {
            return self::produceError('Error: File already exists on server #' . $params['server_id'] . ' (' . $newServerDetails['serverLabel'] . ').');
        }

        // get file contents
        $downloadToken = $file->generateDirectDownloadToken(0, 0, true, false, false, true);
        if (!$downloadToken) {
            // fail
            return self::produceError('Error: Could not create url (token) to get file.');
        }

        // compile full url
        $downloadUrl = $file->getFullShortUrl(true) . '?' . File::DOWNLOAD_TOKEN_VAR . '=' . $downloadToken;

        // see if we have an amendment to the storage path, i.e. for caching
        if (isset($params['storage_path_append'])) {
            $storagePath .= $params['storage_path_append'] . '/';
            @mkdir($storagePath);
        }

        // make sure sub-folder exists
        $subFolder = current(explode('/', $file->getLocalFilePath()));
        @mkdir($storagePath . $subFolder);

        // prepare full file path
        $newServerFilePath = $storagePath . $file->getLocalFilePath();

        // save file locally
        $rs = file_put_contents($newServerFilePath, CoreHelper::getRemoteUrlContent($downloadUrl));
        if (!$rs) {
            // create folder and try again
            $fullPath = dirname($newServerFilePath);
            if (!is_dir($fullPath)) {
                mkdir($fullPath);
            }
            $rs = file_put_contents($newServerFilePath, CoreHelper::getRemoteUrlContent($downloadUrl));
            if (!$rs) {
                return self::produceError('Error: Failed writing file on local server. (' . $newServerFilePath . ')');
            }
        }

        // ensure the filesize matches
        if(filesize($newServerFilePath) !== $file->getPrimaryFileSize()) {
            return self::produceError('Error: Downloaded file failed filsize check. (Downloaded: ' . filesize($newServerFilePath) . '. Expected: '.$file->getPrimaryFileSize().' )');
        }

        // delete original file
        $paramsDelete = $params;
        $paramsDelete['server_id'] = $file->getPrimaryServerId();
        $paramsDelete['file_path'] = $file->getLocalFilePath();
        self::apiRawdeletefile($paramsDelete);

        // update database including
        $db = Database::getDatabase(true, true);
        if (strlen($file->getPrimaryFileHash())) {
            // update all with the same file hash
            $db->query('UPDATE file_artifact_storage '
                . 'SET file_server_id = :file_server_id '
                . 'WHERE file_artifact_id IN (SELECT id FROM file_artifact WHERE file_hash = :file_hash AND file_artifact_type = "primary") ', [
                    'file_server_id' => $params['server_id'],
                    'file_hash' => $file->getPrimaryFileHash(),
            ]);
        }
        else {
            // no file hash found, update database
            $db->query('UPDATE file_artifact_storage '
                . 'SET file_server_id = :file_server_id '
                . 'WHERE file_artifact_id IN (SELECT id FROM file_artifact WHERE file_id = :file_id AND file_artifact_type = "primary") ', [
                'file_server_id' => $params['server_id'],
                'file_id' => $file->id,
            ]);
        }

        // finish up
        $data = 'File moved to ' . $newServerDetails['serverLabel'] . ' (' . $newServerFilePath . ').';

        return self::produceSuccess($data);
    }

    /*
     * Get the contents of a file from the file server
     */

    public static function apiRawgetfilecontent(array $params = []) {
        // make sure we have the file_id
        $file_id = null;
        if (isset($params['file_id'])) {
            $file_id = (int) $params['file_id'];
        }

        if ((int) $file_id == 0) {
            return self::produceError('Error: Method requires a valid file_id.');
        }

        // load file
        $file = File::loadOneById($file_id);

        // make sure the file is active
        if ($file->status != 'active') {
            return self::produceError('Error: File is not active.');
        }

        // get file contents
        $downloadToken = $file->generateDirectDownloadToken(0, 0, true, true, false);
        if (!$downloadToken) {
            // fail
            return self::produceError('Error: Could not create url (token) to get file.');
        }

        // compile full url
        $downloadUrl = $file->getFullShortUrl(true) . '?' . File::DOWNLOAD_TOKEN_VAR . '=' . $downloadToken;
        if (!$downloadUrl) {
            return self::produceError('Error: Could not get file contents.');
        }

        self::$_internalStatusTracker = true;

        return file_get_contents($downloadUrl);
    }

    /*
     * Get the contents of a file from the file server
     */

    public static function apiRawdeletefile(array $params = []) {
        // make sure we have the file_path
        $filePath = null;
        if (isset($params['file_path'])) {
            $filePath = $params['file_path'];
        }

        if ($filePath === null) {
            return self::produceError('Error: File path (file_path) not found in rawdeletefile action.');
        }

        // make sure we have a server_id to delete from
        if (!isset($params['server_id'])) {
            return self::produceError('Error: Server id (server_id) not found in rawdeletefile action.');
        }

        // this method can only be used on the original file store server, if this isn't it, forward the request
        if (($params['server_id'] != FileHelper::getCurrentServerId()) && (!isset($params['___internal_redirect_raw_delete']))) {
            // set flag to stop loops of doom!
            $params['___internal_redirect_raw_delete'] = true;

            // create new url
            $correctServerPath = FileHelper::getFileDomainAndPath(null, $params['server_id'], true);
            $url = self::createApiUrl($correctServerPath, $params['key'], $params['username'], 'rawdeletefile', $params);
            $rs = CoreHelper::getRemoteUrlContent($url);
            if (strlen($rs) == 0) {
                // try to get the url headers for better errors
                $headers = get_headers($url);
                if (isset($headers[0])) {
                    return self::produceError('Error: Problem contacting file server to delete stored file. ' . $headers[0]);
                }
                return self::produceError('Error: Problem contacting file server to to delete stored file, does the api exist?');
            }

            self::$_internalStatusTracker = true;

            return $rs;
        }
        elseif ($params['server_id'] != FileHelper::getCurrentServerId()) {
            return self::produceError('Error: Problem contacting file server to delete stored file.');
        }

        // by this stage we should be on the file server with the file stored, get the file contents
        $newServerDetails = FileHelper::loadServerDetails($params['server_id']);
        if (strlen($newServerDetails['storagePath'])) {
            $storagePath = FileServerHelper::getDocRoot($params['server_id']) . '/' . $newServerDetails['storagePath'];
        }
        if (substr($storagePath, strlen($storagePath) - 1, 1) == '/') {
            $storagePath = substr($storagePath, 0, strlen($storagePath) - 1);
        }
        $storagePath .= '/';

        // full file path on the file system
        $filePath = $storagePath . $filePath;

        // make sure file exists
        if (!file_exists($filePath)) {
            return self::produceError('Error: File does not exist on ' . $newServerDetails['serverLabel'] . ' (' . $filePath . ').');
        }

        // delete file
        $rs = unlink($filePath);
        if (!$rs) {
            return self::produceError('Error: Failed removing file on ' . $newServerDetails['serverLabel'] . ' (' . $filePath . ').');
        }

        // echo file content
        self::$_internalStatusTracker = true;
        $data = 'File deleted from ' . $newServerDetails['serverLabel'] . '.';

        return self::produceSuccess($data);
    }
    
    /*
     * Purge the applicaton cache on the current server
     */
    public static function apiPurgecache(array $params = []) {
        // purge application cache
        CacheHelper::removeCoreApplicationCache();
        $msg = "Application cache purged.";
        AdminHelper::setSuccess($msg);
        
        return self::produceSuccess($msg);
    }

    /*
     * Create success output.
     */

    public static function produceSuccess($dataArr) {
        $rs = [];
        $rs['success'] = true;
        $rs['response_time'] = time();
        $rs['result'] = $dataArr;

        return json_encode($rs);
    }

    public static function outputSuccess($dataArr) {
        $successStr = self::produceSuccess($dataArr);
        echo $successStr;
        exit;
    }

    /*
     * Create error output.
     */

    public static function produceError($errorMsg) {
        $rs = [];
        $rs['error'] = true;
        $rs['error_time'] = time();
        $rs['error_msg'] = $errorMsg;

        return json_encode($rs);
    }

    public static function outputError($errorMsg) {
        $errorStr = self::produceError($errorMsg);
        echo $errorStr;
        exit;
    }

    /**
     * Create admin API URL
     *
     * @param string $apiPath
     * @param string $privateKey
     * @param string $username
     * @param string $action
     * @param array $params
     * @return string
     */
    public static function createApiUrl(
        string $apiPath,
        string $privateKey,
        string $username,
        string $action,
        array $params = []
    ): string {
        if (substr($apiPath, strlen($apiPath) - 1, 1) == '/') {
            $apiPath = substr($apiPath, 0, strlen($apiPath) - 1);
        }

        // check for duplicates
        if (isset($params['key'])) {
            unset($params['key']);
        }
        if (isset($params['username'])) {
            unset($params['username']);
        }
        if (isset($params['action'])) {
            unset($params['action']);
        }

        // prepare extra params
        $extraParams = '';
        if (count($params)) {
            foreach ($params AS $k => $param) {
                $extraParams .= $k . '=' . urlencode($param) . '&';
            }
        }

        return _CONFIG_SITE_PROTOCOL . '://' . $apiPath . '/' . ADMIN_FOLDER_NAME . '/api/?key=' . urlencode($privateKey) . '&username=' . urlencode($username) . '&action=' . urlencode($action) . '&' . $extraParams;
    }

}
