<?php

namespace App\Services\File_Manager;

use App\Core\Database;
use App\Models\FileFolder;
use App\Helpers\AuthHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;

class NonaccountsharedFileManager extends BaseFileManager
{

    public function getData() {
        // preload current user and DB
        $Auth = AuthHelper::getAuth();

        // prepare variables
        $foldersClauseReplacements = [];
        $filesClauseReplacements = [];

        // root level
        if ((int)$this->getParameter('nodeId') === 0) {
            // prepare the SQL clause for root level
            $foldersClause = 'WHERE file_folder.status = "active" '
                    . 'AND file_folder.id IN ('
                    . 'SELECT folder_id '
                    . 'FROM file_folder_share '
                    . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                    . 'LEFT JOIN file_folder ON file_folder_share_item.folder_id = file_folder.id '
                    . 'WHERE file_folder_share.id = :file_folder_share_id '
                    . 'AND folder_id IS NOT NULL '
                    . 'AND shared_with_user_id IS NULL '
                    . 'AND (file_folder.parentId IS NULL OR file_folder.parentId NOT IN ('
                    . 'SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE file_folder_share.id = :file_folder_share_id AND folder_id IS NOT NULL '
                    . '))'
                    . ')';
            $foldersClauseReplacements['file_folder_share_id'] = (int)$_SESSION['sharekeyFileFolderShareId'];

            // files SQL
            $filesClause = 'WHERE file.status = "active" '
                    . 'AND file.id IN ('
                    . 'SELECT file_id '
                    . 'FROM file_folder_share '
                    . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                    . 'WHERE file_folder_share.id = :file_folder_share_id '
                    . 'AND shared_with_user_id IS NULL '
                    . 'AND file_id IS NOT NULL '
                    . ')';
            $filesClauseReplacements['file_folder_share_id'] = (int)$_SESSION['sharekeyFileFolderShareId'];
        }
        // folder level
        else {
            if ($this->currentFolder === null) {
                $this->currentFolder = FileFolder::loadOneById($this->getParameter('nodeId'));
            }
            if (!$this->currentFolder) {
                $this->throwException('Failed loading folder.');
            }
            
            // prepare the SQL clause for folder level
            $foldersClause = 'WHERE file_folder.status = "active" '
                . 'AND file_folder.id IN ('
                . 'SELECT folder_id '
                . 'FROM file_folder_share '
                . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                . 'LEFT JOIN file_folder ON file_folder_share_item.folder_id = file_folder.id '
                . 'WHERE shared_with_user_id IS NULL '
                . 'AND file_folder_share.id = :file_folder_share_id '
                . 'AND file_folder.parentId = :parent_id '
                . ')';
            $foldersClauseReplacements['file_folder_share_id'] = (int)$_SESSION['sharekeyFileFolderShareId'];
            $foldersClauseReplacements['parent_id'] = $this->getParameter('nodeId');
            
            // files SQL - assuming any files which exist in shared folders can
            // be accessed
            $filesClause = 'WHERE file.status = "active" '
                    . 'AND file.folderId = :folder_id '
                    . 'AND file.folderId IN ('
                    . 'SELECT folder_id '
                    . 'FROM file_folder_share '
                    . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                    . 'LEFT JOIN file_folder ON file_folder_share_item.folder_id = file_folder.id '
                    . 'WHERE file_folder_share.id = :file_folder_share_id '
                    . 'AND shared_with_user_id IS NULL '
                    . 'AND file_folder.id = :folder_id '
                    . ')';
            $filesClauseReplacements['file_folder_share_id'] = (int)$_SESSION['sharekeyFileFolderShareId'];
            $filesClauseReplacements['folder_id'] = $this->getParameter('nodeId');
        }

        // load the folders data into the object
        $this->getFoldersData($foldersClause, $foldersClauseReplacements);
        
        // load the files data into the object
        $this->getFilesData($filesClause, $filesClauseReplacements);
    }

    public function requiresLogin() {
        // if no session id, skip
        if(!isset($_SESSION['sharekeyFileFolderShareId']) || (int)$_SESSION['sharekeyFileFolderShareId'] === 0) {
            return false;
        }

        // allow non logged in users
        return false;
    }

    public function getRequiredPrechecks() {
        return false;
    }

    public function getPageTitle() {
        return TranslateHelper::t("shared_files_and_folders", "Shared Files & Folders") . ' - ' . SITE_CONFIG_SITE_NAME;
    }

    public function getPageUrl() {
        return $_SESSION['sharekeyOriginalUrl'];
    }
    
    public function getNoResultsHtml() {        
        return '<div class="alert alert-warning"><i class="entypo-attention"></i> '
                . TranslateHelper::t('no_files_found_in_folder', 'No files found within this folder.')
                . '</div>';
    }

    public function getBreadcrumbs() {
        // get base level first
        $breadcrumbs = $this->getBaseBreadcrumbs();

        // add on any custom breadcrumbs
        $breadcrumbs[] = '<a href="#" onClick="loadImages(\'' . $this->getPageType() . '\', \'\', 1); return false;" class="btn btn-white">' . TranslateHelper::t("shared_files_and_folders", "Shared Files & Folders") . $this->getBreadcrumbTotalText() . '</a>';

        return $breadcrumbs;
    }
    
    public function getShareAccessLevel() {
        return 'view_download';
    }
}
