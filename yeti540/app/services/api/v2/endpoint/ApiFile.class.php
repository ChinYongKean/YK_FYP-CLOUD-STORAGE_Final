<?php

/*
 * API endpoint class
 */

namespace App\Services\Api\V2\Endpoint;

use App\Services\Api\V2\ApiV2;
use App\Core\Database;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\FileFolderHelper;
use App\Helpers\LogHelper;
use App\Models\File;

class ApiFile extends ApiV2
{

    public function __construct($request, $origin)
    {
        parent::__construct($request);

        // all api requests require the access_token and account_id (apart from the initial authorize
        if (!array_key_exists('access_token', $this->request) || (strlen($this->request['access_token']) == 0)) {
            throw new \Exception('Please provide the access_token param.');
        } elseif (!array_key_exists('account_id', $this->request) || (strlen($this->request['account_id']) == 0)) {
            throw new \Exception('Please provide the account_id param.');
        }

        // validate access_token and account_id
        $rs = $this->_validateAccessToken($this->request['access_token'], $this->request['account_id']);
        if (!$rs) {
            throw new \Exception('Could not validate access_token and account_id, please reauthenticate or try again.');
        }
    }

    /**
     * endpoint action
     */
    protected function upload()
    {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validation
        $uploadedFile = $_FILES['upload_file'];
        if (!is_array($uploadedFile)) {
            throw new \Exception('Did not receive uploaded file.');
        }

        // check filesize
        if ($uploadedFile['size'] == 0) {
            throw new \Exception('Filesize received was zero.');
        }

        // check for curl
        if (!function_exists('curl_init')) {
            throw new \Exception('PHP Curl module does not exist on your server/web '
                .'hosting. It will need to be enable to use this upload feature.');
        }

        // load users username for the upload api
        $db = Database::getDatabase();
        $username = $db->getValue('SELECT username '
            .'FROM users '
            .'WHERE id = :id '
            .'LIMIT 1', [
                'id' => (int)$this->request['account_id'],
            ]
        );

        // load api key
        $apiKey = $db->getValue("SELECT apikey "
            ."FROM users "
            ."WHERE id = :id "
            ."LIMIT 1", [
            'id' => (int)$this->request['account_id'],
        ]);
        if (!$apiKey) {
            // no api key so add it
            $apiKey = MD5(microtime().(int)$this->request['account_id'].microtime());
            $db->query('UPDATE users '
                .'SET apikey = :apikey '
                .'WHERE id = :id '
                .'AND username = :username '
                .'LIMIT 1', [
                'apikey' => $apiKey,
                'id' => (int)$this->request['account_id'],
                'username' => $username,
            ]);
        }

        // prepare the params
        $post = [];
        $post['folderId'] = (int)$this->request['folder_id'] == 0 ? -1 : (int)$this->request['folder_id'];
        $post['api_key'] = $apiKey;
        $post['username'] = $username;
        $post['action'] = 'upload';
        $post['files'] = curl_file_create($uploadedFile['tmp_name'], null, $uploadedFile['name']);

        // simulate posting the file using curl
        $serverUploadUrl = FileHelper::getUploadUrl();
        if (!$serverUploadUrl) {
            // no available upload servers
            $error = 'No available upload servers, exiting.';
            LogHelper::error($error);

            throw new \Exception($error);
        }

        $url = $serverUploadUrl.'/api_upload_handler';
        LogHelper::info('Curl request to: '.$url);
        LogHelper::info('Curl params: '.print_r($post, true));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        //curl_setopt($ch, CURLOPT_HEADER, 1);
        //$headers = array(
        //    'Transfer-Encoding: chunked',
        //);
        //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'curlProgress');
        curl_setopt($ch, CURLOPT_NOPROGRESS, true);
        //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        $msg = curl_exec($ch);
        $error = '';

        if (curl_errno($ch)) {
            $error = 'Error uploading file to '.$url.': '.curl_error($ch);
        } else {
            // try to read the json response
            if (strlen($msg) == 0) {
                $error = 'Error uploading file. No response received from: '.$url;
            } else {
                $responseArr = json_decode($msg, true);
                if (is_array($responseArr)) {
                    // got data as array
                    if (isset($responseArr[0]['error'])) {
                        $error = 'Error on: '.$url.'. '.$responseArr[0]['error'];
                    }
                } else {
                    $error = 'Failed reading response from: '.$url.'. Response: '.$msg;
                }
            }
        }

        // close curl
        curl_close($ch);

        // error
        if (strlen($error)) {
            // log
            LogHelper::error($error);

            throw new \Exception($error);
        }

        return ['response' => 'File uploaded', 'data' => $responseArr];
    }

    /**
     * endpoint action
     */
    protected function info()
    {
        // validate file_id
        if (!array_key_exists('file_id', $this->request) || (strlen($this->request['file_id']) == 0)) {
            throw new \Exception('Please provide the file_id param.');
        }

        $db = Database::getDatabase();

        // make sure the file id belongs to the current user
        $rs = (int)$db->getValue('SELECT COUNT(id) AS total FROM file WHERE userId = :user_id AND id = :file_id LIMIT 1',
            ['user_id' => $this->request['account_id'], 'file_id' => $this->request['file_id']]);
        if (!$rs) {
            throw new \Exception('Could not find file based on file_id.');
        }

        // load info
        $fileDetails = $db->getRow('SELECT file.id, originalFilename AS filename, shortUrl, fa.file_type AS fileType, extension, fa.file_size AS fileSize, uploadedIP, uploadedDate, '
            .'status AS file_status, visits AS downloads, lastAccessed, folderId, keywords, isPublic, uploadSource, description FROM file '
            .'LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = "primary" '
            .'WHERE file.id = :file_id AND userId = :user_id LIMIT 1',
            ['user_id' => (int)$this->request['account_id'], 'file_id' => (int)$this->request['file_id']],
            \PDO::FETCH_ASSOC);
        if ($fileDetails) {
            // append file urls
            $file = File::loadOneById((int)$this->request['file_id']);
            if ($file) {
                $fileDetails['url_file'] = $file->getShortUrlPath();
                $fileDetails['url_file_info'] = $file->getShortInfoUrl();
                $fileDetails['url_file_stats'] = $file->getStatisticsUrl();
                $fileDetails['url_file_delete'] = $file->getDeleteUrl();
            }
        }

        return ['data' => $fileDetails];
    }

    /**
     * endpoint action
     */
    protected function download()
    {
        // validate file_id
        if ((!array_key_exists('file_id', $this->request) || (strlen($this->request['file_id']) == 0))
            && (!array_key_exists('short_url', $this->request) || (strlen($this->request['short_url']) == 0))) {
            throw new \Exception('Please provide the file_id param.');
        }

        $db = Database::getDatabase();

        // prepare SQL
        $replacements = [
            'user_id' => $this->request['account_id'],
        ];
        $sQL = 'SELECT id '
            .'FROM file '
            .'WHERE userId = :user_id '
            .'AND ';
        if (!empty($this->request['file_id'])) {
            $sQL .= 'id = :file_id ';
            $replacements['file_id'] = $this->request['file_id'];
        } else {
            $sQL .= 'shortUrl = :short_url ';
            $replacements['short_url'] = $this->request['short_url'];
        }
        $sQL .= 'LIMIT 1';

        // make sure the file belongs to the current user
        $fileId = (int)$db->getValue($sQL, $replacements);
        if (!$fileId) {
            throw new \Exception('Could not find file based on file_id or short_url.');
        }

        // create download url for file
        $fileObj = File::loadOneById($fileId);
        $downloadToken = $fileObj->generateDirectDownloadToken(0, 0, true, true, false);
        if (!$downloadToken) {
            // fail
            throw new \Exception('Could not generate download url.');
        }

        // compile full url
        $downloadUrl = $fileObj->getFullShortUrl(true).'?'.File::DOWNLOAD_TOKEN_VAR.'='.$downloadToken;

        return [
            'data' => [
                'file_id' => $fileObj->id,
                'short_url' => $fileObj->shortUrl,
                'filename' => $fileObj->originalFilename,
                'download_url' => $downloadUrl,
            ],
        ];
    }

    /**
     * endpoint action
     */
    protected function edit()
    {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validate file_id
        if (!array_key_exists('file_id', $this->request) || (strlen($this->request['file_id']) == 0)) {
            throw new \Exception('Please provide the file_id param.');
        }

        $db = Database::getDatabase();

        // make sure the file id belongs to the current user
        $rs = (int)$db->getValue('SELECT COUNT(id) AS total FROM file WHERE userId = :user_id AND id = :file_id LIMIT 1',
            ['user_id' => $this->request['account_id'], 'file_id' => $this->request['file_id']]);
        if (!$rs) {
            throw new \Exception('Could not find file based on file_id.');
        }

        // update item
        $params = [];
        $sQLClause = [];
        if (array_key_exists('filename', $this->request) && (strlen($this->request['filename']) > 0)) {
            $params['originalFilename'] = trim($this->request['filename']);
            $sQLClause[] = 'originalFilename = :originalFilename';

            // ensure the extension is correct
            $parts = explode(".", trim($this->request['filename']));
            $lastPart = end($parts);
            $extension = strtolower($lastPart);

            $params['extension'] = $extension;
            $sQLClause[] = 'extension = :extension';
        }

        if (array_key_exists('folder_id', $this->request) && (strlen($this->request['folder_id']) > 0)) {
            // make sure user owns folder_id
            $canUpdate = false;
            if (strtolower($this->request['folder_id']) != 'null') {
                $folderListing = FileFolderHelper::loadAllActiveForSelect((int)$this->request['account_id']);
                if (isset($folderListing[$this->request['folder_id']])) {
                    $canUpdate = true;
                }
            } else {
                $canUpdate = true;
            }

            if ($canUpdate === true) {
                $params['folderId'] = $this->request['folder_id'] == 'null' ? null : (int)$this->request['folder_id'];
                $sQLClause[] = 'folderId = :folderId';
            }
        }

        // update the item
        if (count($params)) {
            // prep sql
            $sQL = 'UPDATE file SET '.implode(', ', $sQLClause).' '
                .'WHERE id = :file_id AND userId = :user_id LIMIT 1';

            // update params
            $params['user_id'] = (int)$this->request['account_id'];
            $params['file_id'] = (int)$this->request['file_id'];

            // execute sql
            $rs = $db->query($sQL, $params);

            // check for artifact updates
            if (array_key_exists('fileType', $this->request) && (strlen($this->request['fileType']) > 0)) {
                // prep sql
                $sQL = 'UPDATE file_artifact SET file_type = :file_type '
                    .'WHERE file_id = :file_id AND file_artifact_type = "primary" AND file_id IN (SELECT id FROM file WHERE userId = :user_id) LIMIT 1';

                // update params
                $params['file_type'] = trim($this->request['fileType']);
                $params['user_id'] = (int)$this->request['account_id'];
                $params['file_id'] = (int)$this->request['file_id'];

                // execute sql
                $rs = $db->query($sQL, $params);
            }
        }

        // return the updated file item
        return array_merge(['response' => 'File successfully updated.'], $this->info());
    }

    /**
     * endpoint action
     */
    protected function delete()
    {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validate file_id
        if (!array_key_exists('file_id', $this->request) || (strlen($this->request['file_id']) == 0)) {
            throw new \Exception('Please provide the file_id param.');
        }

        $db = Database::getDatabase();

        // make sure the file id belongs to the current user
        $rs = (int)$db->getValue('SELECT COUNT(id) AS total FROM file WHERE userId = :user_id AND id = :file_id LIMIT 1',
            ['user_id' => $this->request['account_id'], 'file_id' => $this->request['file_id']]);
        if (!$rs) {
            throw new \Exception('Could not find file based on file_id.');
        }

        // load the file object
        $file = File::loadOneById((int)$this->request['file_id']);

        // double check that the owner matches the current user
        if ($file->userId != (int)$this->request['account_id']) {
            // fail
            throw new \Exception('Failed deleting the file.');
        }

        // remove the file
        $file->trashByUser();

        // return the updated file item
        return array_merge(['response' => 'File successfully set as deleted.'], $this->info());
    }

    /**
     * endpoint action
     */
    protected function move()
    {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validate file_id
        if (!array_key_exists('file_id', $this->request) || (strlen($this->request['file_id']) == 0)) {
            throw new \Exception('Please provide the file_id param.');
        }

        $db = Database::getDatabase();

        // make sure the file id belongs to the current user
        $rs = (int)$db->getValue('SELECT COUNT(id) AS total FROM file WHERE userId = :user_id AND id = :file_id LIMIT 1',
            ['user_id' => $this->request['account_id'], 'file_id' => $this->request['file_id']]);
        if (!$rs) {
            throw new \Exception('Could not find file based on file_id.');
        }

        // validation
        if (!array_key_exists('new_parent_folder_id',
                $this->request) || (strlen($this->request['new_parent_folder_id']) == 0)) {
            throw new \Exception('Please provide the new_parent_folder_id param.');
        }

        // make sure the user owns the new folder
        $canUpdate = false;
        if (strtolower($this->request['new_parent_folder_id']) != 'null') {
            $folderListing = FileFolderHelper::loadAllActiveForSelect((int)$this->request['account_id']);
            if (isset($folderListing[$this->request['new_parent_folder_id']])) {
                $canUpdate = true;
            }
        } else {
            $canUpdate = true;
        }

        if ($canUpdate === false) {
            throw new \Exception('Could not find the destination folder id defined by new_parent_folder_id.');
        }

        // load the file object
        $file = File::loadOneById((int)$this->request['file_id']);

        // double check that the owner matches the current user
        if ($file->userId != (int)$this->request['account_id']) {
            // fail
            throw new \Exception('Failed moving the file.');
        }

        // move the file
        $file->updateFolder($this->request['new_parent_folder_id']);

        // return the updated file item
        return array_merge(['response' => 'File successfully moved.'], $this->info());
    }

    /**
     * endpoint action
     */
    protected function copy()
    {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validate file_id
        if (!array_key_exists('file_id', $this->request) || (strlen($this->request['file_id']) == 0)) {
            throw new \Exception('Please provide the file_id param.');
        }

        $db = Database::getDatabase();

        // make sure the file id belongs to the current user
        $rs = (int)$db->getValue('SELECT COUNT(id) AS total FROM file WHERE userId = :user_id AND id = :file_id LIMIT 1',
            ['user_id' => $this->request['account_id'], 'file_id' => $this->request['file_id']]);
        if (!$rs) {
            throw new \Exception('Could not find file based on file_id.');
        }

        // validation
        if (!array_key_exists('copy_to_folder_id',
                $this->request) || (strlen($this->request['copy_to_folder_id']) == 0)) {
            throw new \Exception('Please provide the copy_to_folder_id param.');
        }

        // make sure the user owns the new folder
        $canUpdate = false;
        if (strtolower($this->request['copy_to_folder_id']) != 'null') {
            $folderListing = FileFolderHelper::loadAllActiveForSelect((int)$this->request['account_id']);
            if (isset($folderListing[$this->request['copy_to_folder_id']])) {
                $canUpdate = true;
            }
        } else {
            $canUpdate = true;
        }

        if ($canUpdate === false) {
            throw new \Exception('Could not find the destination folder id defined by copy_to_folder_id.');
        }

        // load the file object
        $file = File::loadOneById((int)$this->request['file_id']);

        // double check that the owner matches the current user
        if ($file->userId != (int)$this->request['account_id']) {
            // fail
            throw new \Exception('Failed copying the file.');
        }

        $rs = [];
        $rs['original_file'] = $this->info();

        // copy the file
        $newFile = $file->duplicateFile(['folderId' => $this->request['copy_to_folder_id']]);
        $this->request['file_id'] = $newFile->id;
        $rs['new_file'] = $this->info();

        // return the updated file item
        return array_merge(['response' => 'File successfully copied.'], $rs);
    }

}
