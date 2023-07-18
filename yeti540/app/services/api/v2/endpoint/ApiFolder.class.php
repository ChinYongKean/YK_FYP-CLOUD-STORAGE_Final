<?php

/*
 * API endpoint class
 */

namespace App\Services\Api\V2\Endpoint;

use App\Services\Api\V2\ApiV2;
use App\Core\Database;
use App\Helpers\CoreHelper;
use App\Helpers\FileFolderHelper;
use App\Models\File;
use App\Models\FileFolder;

class ApiFolder extends ApiV2
{

    public function __construct($request, $origin) {
        parent::__construct($request);

        // all api requests require the access_token and account_id (apart from the initial authorize
        if (!array_key_exists('access_token', $this->request) || (strlen($this->request['access_token']) == 0)) {
            throw new \Exception('Please provide the access_token param.');
        }
        elseif (!array_key_exists('account_id', $this->request) || (strlen($this->request['account_id']) == 0)) {
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
    protected function create() {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validation
        if (!array_key_exists('folder_name', $this->request) || (strlen($this->request['folder_name']) == 0)) {
            throw new \Exception('Please provide the folder_name param.');
        }

        // update item
        $params = [];
        $sQLClauseLeft = [];
        $sQLClauseRight = [];

        // folder_name
        $params['folderName'] = trim($this->request['folder_name']);
        $sQLClauseLeft[] = 'folderName';
        $sQLClauseRight[] = ':folderName';

        $params['parentId'] = (int) $this->request['parent_id'] == 0 ? null : (int) $this->request['parent_id'];
        $sQLClauseLeft[] = 'parentId';
        $sQLClauseRight[] = ':parentId';

        $isPublic = (int) $this->request['is_public'];
        if ($isPublic < 0 || $isPublic > 2) {
            $isPublic = 0;
        }
        $params['isPublic'] = $isPublic;
        $sQLClauseLeft[] = 'isPublic';
        $sQLClauseRight[] = ':isPublic';

        $params['accessPassword'] = strlen($this->request['access_password']) != 32 ? null : $this->request['access_password'];
        $sQLClauseLeft[] = 'accessPassword';
        $sQLClauseRight[] = ':accessPassword';

        // other params
        $params['userId'] = (int) $this->request['account_id'];
        $sQLClauseLeft[] = 'userId';
        $sQLClauseRight[] = ':userId';

        // insert
        $db = Database::getDatabase();
        $rs = $db->query('INSERT INTO file_folder (' . implode(', ', $sQLClauseLeft) . ', date_added) '
                . 'VALUES (' . implode(', ', $sQLClauseRight) . ', NOW())', $params);
        if (!$rs) {
            // error
            throw new \Exception('Failed creating the folder.');
        }

        // return the folder details
        $this->request['folder_id'] = $db->insertId();

        // return the updated file item
        return array_merge(['response' => 'Folder successfully created.'], $this->info());
    }

    /**
     * endpoint action
     */
    protected function listing() {
        $db = Database::getDatabase();

        // validation
        if ((int) $this->request['parent_folder_id'] > 0) {
            // make sure the folder_id belongs to the current user
            $rs = (int) $db->getValue('SELECT COUNT(id) AS total '
                    . 'FROM file_folder '
                    . 'WHERE userId = :user_id '
                    . 'AND id = :folder_id '
                    . 'LIMIT 1', [
                        'user_id' => $this->request['account_id'],
                        'folder_id' => $this->request['parent_folder_id'],
                ]
                    );
            if (!$rs) {
                throw new \Exception('Could not find folder based on folder_id.');
            }
        }

        // load folder details
        $sQL = 'SELECT id, parentId, folderName, totalSize, isPublic, date_added, '
                . 'date_updated, status, urlHash '
                . 'FROM file_folder '
                . 'WHERE status IN ("active", "trash") '
                . 'AND parentId ';
        if ((int) $this->request['parent_folder_id'] == 0) {
            $sQL .= ' IS NULL';
        }
        else {
            $sQL .= ' = ' . $this->request['parent_folder_id'];
        }

        $sQL .= ' AND userId = :user_id '
                . 'ORDER BY folderName';
        $folderDetails = $db->getRows($sQL, [
            'user_id' => (int) $this->request['account_id'],
        ], \PDO::FETCH_ASSOC);
        if ($folderDetails) {
            // append file urls
            foreach ($folderDetails AS $k => $folderDetail) {
                $folderDetail['userId'] = (int) $this->request['account_id'];
                $fileFolder = FileFolder::hydrateSingleRecord($folderDetail);
                if ($fileFolder) {
                    $folderDetails[$k]['url_folder'] = $fileFolder->getFolderUrl();
                    $folderDetails[$k]['total_downloads'] = $fileFolder->getTotalViews();
                    $folderDetails[$k]['child_folder_count'] = $fileFolder->totalChildFolderCount();
                    $folderDetails[$k]['file_count'] = $fileFolder->totalFileCount();
                }
            }
        }

        // load file details
        $sQL = 'SELECT f.id, originalFilename AS filename, shortUrl, fa.file_type AS fileType, '
                . 'extension, fa.file_size AS fileSize, status, visits AS downloads, folderId, '
                . 'keywords '
                . 'FROM file f '
                . 'LEFT JOIN file_artifact fa ON f.id = fa.file_id '
                . 'WHERE file_artifact_type = "primary" AND f.folderId ';
        if ((int) $this->request['parent_folder_id'] == 0) {
            $sQL .= ' IS NULL';
        }
        else {
            $sQL .= ' = ' . $this->request['parent_folder_id'];
        }

        $sQL .= ' AND userId = :user_id '
                . 'AND status IN ("active", "trash") '
                . 'ORDER BY originalFilename';
        $fileDetails = $db->getRows($sQL, [
            'user_id' => (int) $this->request['account_id'],
        ], \PDO::FETCH_ASSOC);
        if ($fileDetails) {
            // append file urls
            foreach ($fileDetails AS $k => $fileDetail) {
                $fileDetail['userId'] = (int) $this->request['account_id'];
                $file = File::hydrateSingleRecord($fileDetail);
                if ($file) {
                    $fileDetails[$k]['url_file'] = $file->getShortUrlPath();
                }
            }
        }

        return array('data' => ['folders' => $folderDetails, 'files' => $fileDetails]);
    }

    /**
     * endpoint action
     */
    protected function info() {
        $db = Database::getDatabase();

        // validation
        if (!array_key_exists('folder_id', $this->request) || (strlen($this->request['folder_id']) == 0)) {
            throw new \Exception('Please provide the folder_id param.');
        }

        // make sure the folder_id belongs to the current user
        $rs = (int) $db->getValue('SELECT COUNT(id) AS total '
                . 'FROM file_folder '
                . 'WHERE userId = :user_id '
                . 'AND id = :folder_id '
                . 'LIMIT 1', [
                    'user_id' => $this->request['account_id'],
                    'folder_id' => $this->request['folder_id'],
            ]
                );
        if (!$rs) {
            throw new \Exception('Could not find folder based on folder_id.');
        }

        // load folder details
        $sQL = 'SELECT id, parentId, folderName, totalSize, isPublic, accessPassword, status, '
                . 'date_added, date_updated '
                . 'FROM file_folder '
                . 'WHERE id = :folder_id '
                . 'AND userId = :user_id '
                . 'LIMIT 1';
        $folderDetails = $db->getRow($sQL, [
            'user_id' => (int) $this->request['account_id'],
            'folder_id' => ((int) $this->request['folder_id'])
        ], \PDO::FETCH_ASSOC);
        if ($folderDetails) {
            // append file urls
            $fileFolder = FileFolder::loadOneById((int) $this->request['folder_id']);
            if ($fileFolder) {
                $folderDetails['url_folder'] = $fileFolder->getFolderUrl();
                $folderDetails['total_downloads'] = $fileFolder->getTotalViews();
                $folderDetails['child_folder_count'] = $fileFolder->totalChildFolderCount();
                $folderDetails['file_count'] = $fileFolder->totalFileCount();
            }
        }

        return ['data' => $folderDetails];
    }

    /**
     * endpoint action
     */
    protected function edit() {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validation
        if (!array_key_exists('folder_id', $this->request) || (strlen($this->request['folder_id']) == 0)) {
            throw new \Exception('Please provide the folder_id param.');
        }

        // update item
        $params = [];
        $sQLClause = [];
        if (array_key_exists('folder_name', $this->request) && (strlen($this->request['folder_name']) > 0)) {
            $params['folderName'] = trim($this->request['folder_name']);
            $sQLClause[] = 'folderName = :folderName';
        }

        if (array_key_exists('parent_id', $this->request) && (strlen($this->request['parent_id']) > 0)) {
            // make sure user owns folder_id
            $canUpdate = false;
            if (strtolower($this->request['parent_id']) != 'null') {
                $folderListing = FileFolderHelper::loadAllActiveForSelect((int) $this->request['account_id']);
                if (isset($folderListing[$this->request['parent_id']])) {
                    $canUpdate = true;
                }
            }
            else {
                $canUpdate = true;
            }

            if ($canUpdate === true) {
                $params['parentId'] = $this->request['parent_id'] == 'null' ? null : (int) $this->request['parent_id'];
                $sQLClause[] = 'parentId = :parentId';
            }
        }

        if (array_key_exists('is_public', $this->request) && (strlen($this->request['is_public']) > 0)) {
            $isPublic = (int) $this->request['is_public'];
            if ($isPublic < 0 || $isPublic > 2) {
                $isPublic = 0;
            }
            $params['isPublic'] = $isPublic;
            $sQLClause[] = 'isPublic = :isPublic';
        }

        if (array_key_exists('access_password', $this->request) && (strlen($this->request['access_password']) > 0)) {
            if (strtolower($this->request['access_password']) == 'null') {
                $sQLClause[] = 'accessPassword = NULL';
            }
            else {
                $params['accessPassword'] = trim($this->request['access_password']);
                $sQLClause[] = 'accessPassword = :accessPassword';
            }
        }

        // if there's items to update, so the sql
        if (count($params)) {
            // prep sql
            $sQL = 'UPDATE file_folder '
                    . 'SET ' . implode(', ', $sQLClause) . ', date_updated = NOW() '
                    . 'WHERE id = :folder_id '
                    . 'AND userId = :user_id '
                    . 'LIMIT 1';

            // update params
            $params['user_id'] = (int) $this->request['account_id'];
            $params['folder_id'] = (int) $this->request['folder_id'];

            // execute sql
            $db = Database::getDatabase();
            $rs = $db->query($sQL, $params);
        }

        // return the updated file item
        return array_merge(['response' => 'Folder successfully updated.'], $this->info());
    }

    /**
     * endpoint action
     */
    protected function delete() {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validation
        if (!array_key_exists('folder_id', $this->request) || (strlen($this->request['folder_id']) == 0)) {
            throw new \Exception('Please provide the folder_id param.');
        }

        // update item
        $params = [];

        // load the fileFolder object
        $fileFolder = FileFolder::loadOneById((int) $this->request['folder_id']);

        // double check that the owner matches the current user
        if ($fileFolder->userId != (int) $this->request['account_id']) {
            // fail
            throw new \Exception('Failed deleting the folder.');
        }

        // remove the file
        $fileFolder->trashByUser();

        // return the updated file item
        return array_merge(['response' => 'Folder successfully set as deleted.']);
    }

    /**
     * endpoint action
     */
    protected function move() {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // validation
        if (!array_key_exists('folder_id', $this->request) || (strlen($this->request['folder_id']) == 0)) {
            throw new \Exception('Please provide the folder_id param.');
        }

        // validation
        if (!array_key_exists('new_parent_folder_id', $this->request) || (strlen($this->request['new_parent_folder_id']) == 0)) {
            throw new \Exception('Please provide the new_parent_folder_id param.');
        }

        // make sure the destination and original folder do not match
        if ($this->request['folder_id'] == $this->request['new_parent_folder_id']) {
            throw new \Exception('Param folder_id can not match new_parent_folder_id.');
        }

        // make sure the user owns the new folder
        $canUpdate = false;
        if (strtolower($this->request['new_parent_folder_id']) != 'null') {
            $folderListing = FileFolderHelper::loadAllActiveForSelect((int) $this->request['account_id']);
            if (isset($folderListing[$this->request['new_parent_folder_id']])) {
                $canUpdate = true;
            }
        }
        else {
            $canUpdate = true;
        }

        if ($canUpdate === false) {
            throw new \Exception('Could not find the destination folder id defined by new_parent_folder_id.');
        }

        // load the fileFolder object
        $fileFolder = FileFolder::loadOneById((int) $this->request['folder_id']);

        // double check that the owner matches the current user
        if ($fileFolder->userId != (int) $this->request['account_id']) {
            // fail
            throw new \Exception('Failed moving the folder.');
        }

        // move the folder
        $fileFolder->updateParentFolder($this->request['new_parent_folder_id']);

        // return the updated file item
        return array_merge(['response' => 'Folder successfully moved.'], $this->info());
    }

}
