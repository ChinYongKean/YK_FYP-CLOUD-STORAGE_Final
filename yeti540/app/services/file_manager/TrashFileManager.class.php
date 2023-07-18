<?php

namespace App\Services\File_Manager;

use App\Core\Database;
use App\Helpers\AuthHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;

class TrashFileManager extends BaseFileManager
{
    public function getData() {
        // preload current user and DB
        $Auth = AuthHelper::getAuth();

        // prepare the SQL clause
        $foldersClause = 'WHERE file_folder.userId = :user_id '
                . 'AND file_folder.status = "trash" '
                . 'AND file_folder.parentId IS NULL ';
        $foldersClauseReplacements = [];
        $foldersClauseReplacements['user_id'] = $Auth->id;
        $filesClauseReplacements = [];
        $filesClause = 'WHERE (file.userId = :user_id '
                . 'OR file.uploadedUserId = :user_id) '
                . 'AND file.status = "trash" '
                . 'AND file.folderId IS NULL ';
        $filesClauseReplacements['user_id'] = $Auth->id;
        
        // load the folders data into the object
        $this->getFoldersData($foldersClause, $foldersClauseReplacements);
        
        // load the files data into the object
        $this->getFilesData($filesClause, $filesClauseReplacements);
    }

    public function requiresLogin() {
        // allow logged in only users
        return true;
    }

    public function getRequiredPrechecks() {
        return false;
    }

    public function getPageTitle() {
        return TranslateHelper::t('trash_can', 'Trash Can');
    }

    public function getPageUrl() {
        return ThemeHelper::getLoadedInstance()->getAccountWebRoot().'/trash';
    }
    
    public function getUserOwnsFolder() {
        return true;
    }
    
    public function getNoResultsHtml() {
        return '<div class="alert alert-warning"><i class="entypo-attention"></i> '
                . TranslateHelper::t('no_trash_files_found', 'Your trash can is empty.')
                . '</div>';
    }

    public function getBreadcrumbs() {
        // get base level first
        $breadcrumbs = $this->getBaseBreadcrumbs();
        
        $dropdownMenu = '';
        if(SITE_CONFIG_USER_CAN_EMPTY_TRASH_CAN === 'yes') {
            $dropdownMenu .= '<ul role="menu" class="dropdown-menu dropdown-white pull-left"><li>';
            $dropdownMenu .= '    <li><a href="#" onClick="confirmEmptyTrash(); return false;"><span class="context-menu-icon"><span class="glyphicon glyphicon-trash"></span></span>' . TranslateHelper::t('empty_trash', 'Empty Trash') . '</a></li>';
            $dropdownMenu .= '</ul>';
        }

        // add on any custom breadcrumbs
        $breadcrumbs[] = '<a href="#" class="btn btn-white"'.(strlen($dropdownMenu)?' data-toggle="dropdown"':'').'>' . $this->getPageTitle() . $this->getBreadcrumbTotalText() . (strlen($dropdownMenu)?'&nbsp;&nbsp;<i class="caret"></i>':'') . '</a>'.$dropdownMenu;

        return $breadcrumbs;
    }
    
    public function getPageTypeForFolders() {
        return 'folder';
    }

}
