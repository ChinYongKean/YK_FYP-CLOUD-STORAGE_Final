<?php

namespace App\Services\File_Manager;

use App\Core\Database;
use App\Helpers\AuthHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;

class AllFileManager extends BaseFileManager
{
    public function getData() {
        // preload current user and DB
        $Auth = AuthHelper::getAuth();

        // note that there are no folders on the recent page, only files
        $filesClauseReplacements = [];
        $filesClause = 'WHERE (file.userId = :user_id '
                . 'OR file.uploadedUserId = :user_id) '
                . 'AND file.status = "active" ';
        $filesClauseReplacements['user_id'] = $Auth->id;
        
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
        return TranslateHelper::t('all_files', 'All Files');
    }

    public function getPageUrl() {
        return ThemeHelper::getLoadedInstance()->getAccountWebRoot().'/all_files';
    }
    
    public function getShowToolbarActionButtons() {
        return true;
    }

    public function getBreadcrumbs() {
        // get base level first
        $breadcrumbs = $this->getBaseBreadcrumbs();

        // add on any custom breadcrumbs
        $breadcrumbs[] = '<a href="#" onClick="loadImages(\''.$this->getPageType().'\', \'\', 1); return false;" class="btn btn-white">' . $this->getPageTitle() . $this->getBreadcrumbTotalText() . '</a>';

        return $breadcrumbs;
    }

}
