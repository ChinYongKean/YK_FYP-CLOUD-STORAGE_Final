<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\FileFolderShare;
use App\Models\FileFolderShareItem;

class SharingHelper
{

    /**
     * Main function to create a share of files and/or folders.
     *
     * @param $fileIds
     * @param $folderIds
     * @param $shareUserId
     * @param $permissionLevel
     * @param $folderRecursive
     * @param $isGlobal
     * @param $createdByUserId
     * @return \App\Core\Model
     */
    static function createShare($fileIds, $folderIds, $shareUserId = null, $permissionLevel = 'view', $folderRecursive = true, $isGlobal = 0, $createdByUserId = null) {
        // get current user
        $Auth = AuthHelper::getAuth();
        $createdByUserId = $createdByUserId===null?$Auth->id:$createdByUserId;

        // generate random accessKey
        $accessKey = self::generateUniqueAccessKey();

        // create new sharing object
        $fileFolderShare = FileFolderShare::create();
        $fileFolderShare->access_key = $accessKey;
        $fileFolderShare->date_created = CoreHelper::sqlDateTime();
        $fileFolderShare->created_by_user_id = $createdByUserId;
        $fileFolderShare->shared_with_user_id = ((int) $shareUserId ? (int) $shareUserId : null);
        $fileFolderShare->share_permission_level = $permissionLevel;
        $fileFolderShare->is_global = $isGlobal;
        $fileFolderShare->save();

        // prepare folder ids
        $folderIdsToAdd = [];
        if (count($folderIds)) {
            foreach ($folderIds AS $folderId) {
                $folderIdsToAdd[] = $folderId;
                if ($folderRecursive === true) {
                    $folderIdsToAdd = array_merge($folderIdsToAdd, FileFolderHelper::getAllChildFolderIdsRecurrsive($folderId));
                }
            }
        }

        // ensure folder array is unique
        $folderIdsToAdd = array_unique($folderIdsToAdd);

        // add actual folders
        if (count($folderIdsToAdd)) {
            foreach ($folderIdsToAdd AS $folderIdToAdd) {
                $fileFolderShareItem = FileFolderShareItem::create();
                $fileFolderShareItem->file_folder_share_id = $fileFolderShare->id;
                $fileFolderShareItem->folder_id = $folderIdToAdd;
                $fileFolderShareItem->date_created = CoreHelper::sqlDateTime();
                $fileFolderShareItem->save();
            }
        }

        // add files
        if (count($fileIds)) {
            foreach ($fileIds AS $fileId) {
                $fileFolderShareItem = FileFolderShareItem::create();
                $fileFolderShareItem->file_folder_share_id = $fileFolderShare->id;
                $fileFolderShareItem->file_id = $fileId;
                $fileFolderShareItem->date_created = CoreHelper::sqlDateTime();
                $fileFolderShareItem->save();
            }
        }

        return $fileFolderShare;
    }

    static function generateUniqueAccessKey() {
        return CoreHelper::generateRandomString(128);
    }

    static function getTotalSharedByUser($userId) {
        // get DB connection
        $db = Database::getDatabase();

        // return the total shared items in the root only
        $totalFolders = (int) $db->getValue('SELECT COUNT(DISTINCT file_folder.id) AS totalFolderCount '
                        . 'FROM file_folder '
                        . 'WHERE file_folder.status = "active" '
                        . 'AND file_folder.id IN ('
                        . 'SELECT folder_id '
                        . 'FROM file_folder_share '
                        . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                        . 'LEFT JOIN file_folder ON file_folder_share_item.folder_id = file_folder.id '
                        . 'WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) '
                        . 'AND folder_id IS NOT NULL '
                        . 'AND (file_folder.parentId IS NULL OR file_folder.parentId NOT IN ('
                        . 'SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE shared_with_user_id = :user_id AND folder_id IS NOT NULL '
                        . '))'
                        . ')', array(
                    'user_id' => $userId,
        ));

        $totalFiles = (int) $db->getValue('SELECT COUNT(DISTINCT file.id) AS totalFileCount '
                        . 'FROM file '
                        . 'WHERE file.status = "active" '
                        . 'AND file.id IN ('
                        . 'SELECT file_id '
                        . 'FROM file_folder_share '
                        . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                        . 'WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) '
                        . 'AND file_id IS NOT NULL '
                        . ')', array(
                    'user_id' => $userId,
        ));

        return $totalFolders + $totalFiles;
    }

    /**
     * Used for reloading/editing existing shares, based on the fileIds/folderIds
     * 
     * @param type $fileIds
     * @param type $folderIds
     * @return type
     */
    static function generateEditTrackerHash($fileIds, $folderIds) {
        return md5('files=' . implode('-', $fileIds) . '-folders=' . implode('-', $folderIds));
    }
    
    public static function getSharedUsersForFilesAndFolders($fileIds = array(), $folderIds = array()) {
        // make sure our input is safe
        $safeFileIds = array_map('intval', $fileIds);
        $safeFolderIds = array_map('intval', $folderIds);

        if(count($safeFileIds) === 0 && count($safeFolderIds) === 0) {
            return array();
        }
        
        // prep clause
        $clause = [];
        if(count($safeFileIds)) {
            $clause[] = 'file_folder_share_item.file_id IN ('.implode(', ', $safeFileIds).')';
        }
        if(count($safeFolderIds)) {
            $clause[] = 'file_folder_share_item.folder_id IN ('.implode(', ', $safeFolderIds).')';
        }
        
        // get db
        $db = Database::getDatabase();

        // get list of shares
        return $db->getRows('SELECT DISTINCT users.email, users.id AS user_id, file_folder_share.id, '
                . 'file_folder_share.share_permission_level '
                . 'FROM file_folder_share '
                . 'LEFT JOIN users ON file_folder_share.shared_with_user_id = users.id '
                . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                . 'WHERE file_folder_share.shared_with_user_id IS NOT NULL '
                . 'AND ('.implode(' OR ', $clause).')');
    }

    public static function removeShareById($shareId) {
        // get db
        $db = Database::getDatabase();

        // remove the share
        $db->query('DELETE '
                . 'FROM file_folder_share_item '
                . 'WHERE file_folder_share_id = :file_folder_share_id', array(
                    'file_folder_share_id' => $shareId,
                ));
        
        // clear any shares which now have no items
        $db->query('DELETE '
                . 'FROM file_folder_share '
                . 'WHERE (SELECT count(id) FROM file_folder_share_item WHERE file_folder_share_item.file_folder_share_id = file_folder_share.id) = 0');
        
        return true;
    }

    public static function getSharedFolderClauseRootSQL() {
        return 'WHERE file_folder.status = "active" '
            . 'AND file_folder.id IN ('
            . 'SELECT folder_id '
            . 'FROM file_folder_share '
            . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
            . 'LEFT JOIN file_folder ON file_folder_share_item.folder_id = file_folder.id '
            . 'WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) '
            . 'AND folder_id IS NOT NULL '
            . 'AND (file_folder.parentId IS NULL OR file_folder.parentId NOT IN ('
            . 'SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) AND folder_id IS NOT NULL '
            . '))'
            . ')';
    }

    public static function getSharedFileClauseRootSQL() {
        return 'WHERE file.status = "active" '
            . 'AND file.id IN ('
            . 'SELECT file_id '
            . 'FROM file_folder_share '
            . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
            . 'WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) '
            . 'AND file_id IS NOT NULL '
            . ')';
    }

    public static function getSharedFolderClauseSQLByParentFolderId($parentId) {
        return 'WHERE file_folder.status = "active" '
            . 'AND file_folder.id IN ('
            . 'SELECT folder_id '
            . 'FROM file_folder_share '
            . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
            . 'LEFT JOIN file_folder ON file_folder_share_item.folder_id = file_folder.id '
            . 'WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) '
            . 'AND file_folder.parentId = '.(int)$parentId.' '
            . ')';
    }

    public static function getSharedFileClauseSQLByParentFolderId($parentId) {
        return 'WHERE file.status = "active" '
            . 'AND file.folderId = '.(int)$parentId.' '
            . 'AND file.folderId IN ('
            . 'SELECT folder_id '
            . 'FROM file_folder_share '
            . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
            . 'LEFT JOIN file_folder ON file_folder_share_item.folder_id = file_folder.id '
            . 'WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) '
            . 'AND file_folder.id = '.(int)$parentId.' '
            . ')';
    }

    public static function getFileAccessLevel($fileId, $userId) {
        // get db
        $db = Database::getDatabase();

        // prepare query
        $sQL = 'SELECT share_permission_level ';
        $sQL .= 'FROM file_folder_share ';
        $sQL .= 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id ';
        $sQL .= 'WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) ';
        $sQL .= 'AND file_id = :file_id ';
        $sQL .= 'LIMIT 1';

        // get result
        return $db->getValue($sQL, [
            'file_id' => $fileId,
            'user_id' => $userId,
        ]);
    }

    public static function getFolderAccessLevel($folderId, $userId) {
        // get db
        $db = Database::getDatabase();

        // prepare query
        $sQL = 'SELECT share_permission_level ';
        $sQL .= 'FROM file_folder_share ';
        $sQL .= 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id ';
        $sQL .= 'WHERE (shared_with_user_id = :user_id OR (shared_with_user_id IS NULL AND is_global = 1)) ';
        $sQL .= 'AND folder_id = :folder_id ';
        $sQL .= 'LIMIT 1';

        // get result
        return $db->getValue($sQL, [
            'folder_id' => $folderId,
            'user_id' => $userId,
        ]);
    }

}
