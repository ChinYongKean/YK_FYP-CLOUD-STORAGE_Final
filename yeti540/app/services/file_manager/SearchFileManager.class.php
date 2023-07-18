<?php

namespace App\Services\File_Manager;

use App\Core\Database;
use App\Helpers\AuthHelper;
use App\Helpers\CoreHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\ValidationHelper;

class SearchFileManager extends BaseFileManager
{
    public function getData() {
        // preload current user and DB
        $Auth = AuthHelper::getAuth();
        $db = Database::getDatabase();

        // note that there are no folders on the recent page, only files
        $filesClauseReplacements = [];
        $filesClause = 'WHERE (file.userId = :user_id '
                . 'OR file.uploadedUserId = :user_id) '
                . 'AND file.status = "active" ';
        $filesClauseReplacements['user_id'] = $Auth->id;
        
        // add search filtering
        $filesClause .= ' AND (file.originalFilename LIKE "%' . $db->escape($this->getParameter('searchTerm')) . '%" '
                . 'OR file.shortUrl LIKE "%' . $db->escape($this->getParameter('searchTerm')) . '%" '
                . 'OR file.keywords LIKE "%' . $db->escape($this->getParameter('searchTerm')) . '%")';
        
        // filter by date range
        if ($this->getParameter('filterUploadedDateRange') !== null) {
            // validate date
            $expDate = explode('|', $this->getParameter('filterUploadedDateRange'));
            if (count($expDate) == 2) {
                $startDate = $expDate[0];
                $endDate = $expDate[1];
            }
            else {
                $startDate = $expDate[0];
                $endDate = $expDate[0];
            }

            if ((ValidationHelper::validDate($startDate, 'Y-m-d')) && (ValidationHelper::validDate($endDate, 'Y-m-d'))) {
                // dates are valid
                $filesClause .= " AND (UNIX_TIMESTAMP(file.uploadedDate) >= " . CoreHelper::convertDateToTimestamp($startDate, 'Y-m-d') . " "
                        . "AND UNIX_TIMESTAMP(file.uploadedDate) <= " . (CoreHelper::convertDateToTimestamp($endDate, 'Y-m-d') + (60 * 60 * 24) - 1) . ")";
            }
        }
        
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
        return '"'.ValidationHelper::safeOutputToScreen(str_replace(array('\'', '"'), '', $this->getParameter('searchTerm'))).'" '.TranslateHelper::t('file_search_results', 'File Search Results');
    }

    public function getPageUrl() {
        return ThemeHelper::getLoadedInstance()->getAccountWebRoot().'/search/?filterAllFolders='.$this->getParameter('filterAllFolders').'&filterUploadedDateRange='.$this->getParameter('filterUploadedDateRange').'&t='.urlencode($this->getParameter('searchTerm'));
    }
    
    public function getShowToolbarActionButtons() {
        return true;
    }

    public function getBreadcrumbs() {
        // get base level first
        $breadcrumbs = $this->getBaseBreadcrumbs();

        // add on any custom breadcrumbs
        $breadcrumbs[] = '<a href="#" onClick="loadImages(\''.$this->getPageType().'\', \'\', 1, 0, \'\''.$this->generateAdditionalParamsStringForPaging().'); return false;" class="btn btn-white">' . $this->getPageTitle() . $this->getBreadcrumbTotalText() . '</a>';

        return $breadcrumbs;
    }

    public function getSelectedNavItem() {
        return -1;
    }
    
    public function generateAdditionalParamsStringForPaging() {
        return ', {\'searchTerm\': \''.ValidationHelper::safeOutputToScreen(str_replace(array('\'', '"'), '', $this->getParameter('searchTerm'))).'\', \'filterUploadedDateRange\': \''.ValidationHelper::safeOutputToScreen(str_replace(array('\'', '"'), '', $this->getParameter('filterUploadedDateRange'))).'\'}';
    }
}
