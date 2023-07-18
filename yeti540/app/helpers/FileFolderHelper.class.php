<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileFolderShareItem;

class FileFolderHelper
{
    static function createFolder(
        int $userId,
        string $folderName,
        ?int $parentId = null,
        int $isPublic = 0,
        int $showDownloadLinks = 1,
        ?string $accessPassword = null
    ) {
        $fileFolder = FileFolder::create();
        $fileFolder->userId = $userId;
        $fileFolder->addedUserId = $userId;
        $fileFolder->urlHash = self::generateRandomFolderHash();
        $fileFolder->folderName = $folderName;
        $fileFolder->isPublic = $isPublic;
        $fileFolder->parentId = $parentId;
        $fileFolder->watermarkPreviews = 0;
        $fileFolder->showDownloadLinks = $showDownloadLinks;
        $fileFolder->accessPassword = $accessPassword;
        $fileFolder->save();

        // ensure we've setup the sharing permissions for the new folder
        if ($parentId !== null) {
            self::copyPermissionsToNewFolder($parentId, $fileFolder->id);
        }

        return $fileFolder;
    }

    static function getActiveFoldersByUser($userId, $limit = null) {
        // prepare limiter
        if($limit === null) {
            $limit = 9999999;
        }
        
        // first check for folders in cache and load it if found
        if (!CacheHelper::cacheExists('FOLDER_ACTIVE_OBJECTS_BY_USERID_'.(int)$userId.'_'.(int)$limit)) {
            $db = Database::getDatabase();
            $rows = $db->getRows('SELECT file_folder.*, '
                    . 'file_folder_share.shared_with_user_id, file_folder_share.share_permission_level '
                    . 'FROM file_folder '
                    . 'LEFT JOIN file_folder_share_item ON file_folder.id = file_folder_share_item.folder_id '
                    . 'LEFT JOIN file_folder_share ON file_folder_share_item.file_folder_share_id = file_folder_share.id '
                    . 'WHERE (file_folder.userId = ' . (int) $userId . ' '
                    . 'OR ((file_folder_share.shared_with_user_id = ' . (int) $userId . ' OR (file_folder_share.shared_with_user_id IS NULL AND file_folder_share.is_global = 1)) '
                    . 'AND file_folder_share.share_permission_level IN ("upload_download", "all"))) '
                    . 'AND file_folder.status = "active" '
                    . 'ORDER BY folderName ASC '
                    . 'LIMIT '.$limit);

            // cache for later
            CacheHelper::setCache('FOLDER_ACTIVE_OBJECTS_BY_USERID_' . (int) $userId.'_'.(int)$limit, $rows);
        }

        // get from cache
        return CacheHelper::getCache('FOLDER_ACTIVE_OBJECTS_BY_USERID_' . (int) $userId.'_'.(int)$limit);
    }

    static function trashFolder($folderId) {
        // get db
        $db = Database::getDatabase();

        // load folder details for later
        $fileFolder = FileFolder::loadOneById($folderId);

        // recurrsive delete folders
        self::_trashFolder($fileFolder);

        // update parent folder total filesize
        if ($fileFolder->parentId !== NULL) {
            self::updateFolderFilesize($fileFolder->parentId);
        }

        // set the parentId to null so it shows in the root of deleted
        $db->query('UPDATE file_folder '
                . 'SET parentId = NULL, '
                . 'date_updated=NOW() '
                . 'WHERE id = :id '
                . 'LIMIT 1', array(
            'id' => (int) $folderId,
        ));

        return true;
    }

    static function _trashFolder($fileFolder) {
        // get db
        $db = Database::getDatabase();

        // load children
        $subFolders = FileFolder::loadByClause('parentId = :parent_id', array(
                    'parent_id' => $fileFolder->id,
        ));
        if ($subFolders) {
            foreach ($subFolders AS $subFolder) {
                self::_trashFolder($subFolder);
            }
        }

        // delete any shared item entries
        $db->query('DELETE '
                . 'FROM file_folder_share_item '
                . 'WHERE folder_id = :folder_id', array(
            'folder_id' => (int) $fileFolder->id,
        ));
        
        // clear any shares which now have no items
        $db->query('DELETE '
                . 'FROM file_folder_share '
                . 'WHERE (SELECT count(id) FROM file_folder_share_item WHERE file_folder_share_item.file_folder_share_id = file_folder_share.id) = 0');

        // delete the folder
        $db->query('UPDATE file SET status = "trash", date_updated=NOW() WHERE folderId = ' . (int) $fileFolder->id);
        $db->query('UPDATE file_folder SET status = "trash", date_updated=NOW() WHERE id = ' . (int) $fileFolder->id . ' LIMIT 1');
    }

    static function untrashFolder($folderId, $restoreFolderId = null) {
        // get db
        $db = Database::getDatabase();

        // recurrsive delete folders
        self::_untrashFolder($folderId, $restoreFolderId);

        // update parent folder total filesize
        if ($restoreFolderId !== null) {
            self::updateFolderFilesize($restoreFolderId);
        }

        // set the parentId to $restoreFolderId so it's full restored
        $db->query('UPDATE file_folder '
                . 'SET parentId = :restoreFolderId, '
                . 'date_updated=NOW() '
                . 'WHERE id = :id '
                . 'LIMIT 1', array(
            'id' => (int) $folderId,
            'restoreFolderId' => $restoreFolderId,
        ));

        return true;
    }

    static function _untrashFolder($folderId, $restoreFolderId = null) {
        // get db
        $db = Database::getDatabase(true);

        // load children
        $subFolders = $db->getRows('SELECT id '
                . 'FROM file_folder '
                . 'WHERE parentId = :parent_id', array(
            'parent_id' => $folderId,
        ));
        if ($subFolders) {
            foreach ($subFolders AS $subFolder) {
                self::_untrashFolder($subFolder['id'], $restoreFolderId);
            }
        }

        // delete the folder
        $db->query('UPDATE file SET status = "active", date_updated=NOW() WHERE folderId = ' . (int) $folderId);
        $db->query('UPDATE file_folder SET status = "active", date_updated=NOW() WHERE id = ' . (int) $folderId . ' LIMIT 1');
    }

    static function deleteFolder($folderId, $recursive = true) {
        // get db
        $db = Database::getDatabase(true);

        // load folder details for later
        $folder = FileFolder::loadOneById($folderId);

        // recurrsive delete folders
        self::_deleteFolder($folder, $recursive);

        return true;
    }

    static function _deleteFolder($folder, $recursive = true) {
        // get db
        $db = Database::getDatabase();

        // load children
        if($recursive === true) {
            $subFolders = $db->getRows('SELECT id '
                    . 'FROM file_folder '
                    . 'WHERE parentId = :parent_id', array(
                'parent_id' => (int) $folder->id,
            ));
            if ($subFolders) {
                foreach ($subFolders AS $subFolder) {
                    self::_deleteFolder($subFolder['id'], $recursive);
                }
            }
        }

        // delete any shared entries
        $db->query('DELETE '
                . 'FROM file_folder_share_item '
                . 'WHERE folder_id = :folder_id', array(
                    'folder_id' => $folder->id,
                ));
        
        // clear any shares which now have no items
        $db->query('DELETE '
                . 'FROM file_folder_share '
                . 'WHERE (SELECT count(id) FROM file_folder_share_item WHERE file_folder_share_item.file_folder_share_id = file_folder_share.id) = 0');

        // get all files and schedule for deletion
        $files = File::loadByClause('folderId = :folderId AND status != "deleted"', array(
                    'folderId' => (int) $folder->id
        ));
        if ($files) {
            foreach ($files AS $file) {
                // schedule for removal
                $file->removeByUser();
            }
        }

        // delete the folder
        $db->query('UPDATE file_folder '
                . 'SET status = "deleted", '
                . 'date_updated=NOW(), '
                . 'totalSize = 0 '
                . 'WHERE id = :id '
                . 'LIMIT 1', array(
            'id' => (int) $folder->id,
        ));
    }

    static function loadAllActiveByAccount($accountId, $limit = null) {
        return self::getActiveFoldersByUser($accountId, $limit);
    }

    static function loadAllActiveForSelect($accountId, $delimiter = '/', $excludeFolderIds = [], $limit = 1000) {
        $rs = [];
        $folders = self::loadAllActiveByAccount($accountId, $limit);
        if ($folders) {
            // first prepare local array for easy lookups
            $lookupArr = [];
            foreach ($folders AS $folder) {
                // add to the list
                $folderId = $folder['id'];
                $lookupArr[$folderId] = array('l' => $folder['folderName'], 'p' => $folder['parentId']);
            }

            // populate data
            foreach ($folders AS $folder) {
                $inExcluded = false;
                // ignore this folder if it's "on the list"
                if (in_array($folder['id'], $excludeFolderIds)) {
                    continue;
                }

                // add to the list
                $folderLabelArr = [];
                $folderLabelArr[] = $folder['folderName'];
                $failSafe = 0;
                $parentId = $folder['parentId'];

                while (($parentId != null) && ($failSafe < 30)) {
                    // ignore this folder if it's "on the list"
                    if (in_array($parentId, $excludeFolderIds)) {
                        $inExcluded = true;
                        $parentId = null;
                        continue;
                    }

                    $failSafe++;
                    if (isset($lookupArr[$parentId])) {
                        $folderLabelArr[] = $lookupArr[$parentId]['l'];
                        $parentId = $lookupArr[$parentId]['p'];
                    }
                }

                $folderLabelArr = array_reverse($folderLabelArr);
                $fullFolderLabel = implode($delimiter, $folderLabelArr);

                // ignore this folder if it's "on the list"
                if ($inExcluded) {
                    continue;
                }

                $folderId = $folder['id'];
                $rs[$folderId] = $fullFolderLabel;
            }
        }

        // make pretty
        natcasesort($rs);

        return $rs;
    }

    static function loadAllChildren($parentFolderId = null) {
        $db = Database::getDatabase();
        $row = $db->getRows('SELECT * '
                . 'FROM file_folder '
                . 'WHERE parentId = ' . (int) $parentFolderId . ' '
                . 'ORDER BY folderName');
        if (!is_array($row)) {
            return false;
        }

        return $row;
    }

    static function loadAllPublicChildren($parentFolderId = null) {
        $db = Database::getDatabase();
        $row = $db->getRows('SELECT * '
                . 'FROM file_folder '
                . 'WHERE parentId = ' . (int) $parentFolderId . ' '
                . 'AND isPublic >= 1 '
                . 'ORDER BY folderName');
        if (!is_array($row)) {
            return false;
        }

        return $row;
    }

    static function convertFolderPathToId($pathStr, $accountId) {
        $folderListing = self::loadAllActiveForSelect($accountId, '/');
        if (count($folderListing)) {
            foreach ($folderListing AS $k => $folderListingItem) {
                if ($folderListingItem == $pathStr) {
                    return $k;
                }
            }
        }

        return null;
    }

    static function getFolderCoverData($folderId) {
        $folder = FileFolder::loadOneById($folderId);
        if (!$folder) {
            return false;
        }

        return $folder->getCoverData();
    }

    static function getTotalActivePublicFolders() {
        $db = Database::getDatabase();

        return $db->getValue('SELECT COUNT(DISTINCT file_folder.id) '
                . 'FROM file_folder '
                . 'LEFT JOIN file ON file_folder.id = file.folderId '
                . 'WHERE file_folder.isPublic = 2 '
                . 'AND file_folder.accessPassword IS NULL '
                . 'AND file.isPublic != 0');
    }

    static function getAllChildFolderIdsRecurrsive($folderId) {
        $children = [];
        $children[] = $folderId;
        $db = Database::getDatabase();
        $subFolders = $db->getRows('SELECT id '
                . 'FROM file_folder '
                . 'WHERE parentId = :parentId', array(
            'parentId' => $folderId
        ));

        if ($subFolders) {
            foreach ($subFolders AS $subFolder) {
                $children = array_merge($children, self::getAllChildFolderIdsRecurrsive($subFolder['id']));
            }
        }

        return $children;
    }

    static function updateFolderFilesize($folderId) {
        // get database
        $db = Database::getDatabase();

        // load folder
        $folder = FileFolder::loadOneById($folderId);

        // loop all folders from here up
        $loopTracker = 0;
        $fileSizes = [];
        while ($loopTracker < 30) {
            // get all child folder ids
            $folderIds = self::getAllChildFolderIdsRecurrsive((int) $folder->id);

            // load total filesize including all the child folder ids
            $fileSizes[(int) $folder->id] = $db->getValue('SELECT sum(file_size) AS total ' .
                'FROM file_artifact fa ' .
                'LEFT JOIN file f ON fa.file_id = f.id AND fa.file_artifact_type = "primary" ' .
                'WHERE folderId IN (' . implode(',', $folderIds) . ') AND f.status = "active"');

            // update the value stored in the database
            $rs = $db->query('UPDATE file_folder '
                    . 'SET totalSize = :total_size, '
                    . 'date_updated = NOW() '
                    . 'WHERE id = :id '
                    . 'LIMIT 1', array(
                'id' => (int) $folder->id,
                'total_size' => $fileSizes[(int) $folder->id],
            ));

            // loop again if we have a parentId
            if ($folder->parentId !== NULL) {
                $folder = FileFolder::loadOneById($folder->parentId);
                if (!$folder) {
                    $loopTracker = 30;
                }
                else {
                    $loopTracker++;
                }
            }
            else {
                $loopTracker = 30;
            }
        }

        return $fileSizes[$folderId];
    }

    static function generateRandomFolderHash() {
        return CoreHelper::generateRandomHash();
    }

    static function copyPermissionsToNewFolder($fromFolderId, $toFolderId) {
        // get database
        $db = Database::getDatabase();

        // load sharing for current folder
        $sharingItems = $db->getRows('SELECT file_folder_share_id '
                . 'FROM file_folder_share_item '
                . 'WHERE folder_id = :folder_id', array(
            'folder_id' => $fromFolderId,
        ));

        // get to folder object
        $toFolder = FileFolder::loadOneById($toFolderId);
        if (!$toFolder) {
            return false;
        }

        // loop existing and ensure they're added
        if (count($sharingItems)) {
            foreach ($sharingItems AS $sharingItem) {
                $fileFolderSharingItem = FileFolderShareItem::create();
                $fileFolderSharingItem->file_folder_share_id = $sharingItem['file_folder_share_id'];
                $fileFolderSharingItem->file_id = null;
                $fileFolderSharingItem->folder_id = $toFolderId;
                $fileFolderSharingItem->date_created = CoreHelper::sqlDateTime();
                $fileFolderSharingItem->save();
            }
        }
    }
}
