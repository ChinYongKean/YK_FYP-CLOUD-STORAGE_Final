<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\File;
use App\Models\FileFolder;
use App\Helpers\AuthHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;

class AccountSearchController extends AccountController
{
    public function search() {
        // pickup request
        $request = $this->getRequest();
        $db = Database::getDatabase();
        $Auth = AuthHelper::getAuth();

        // pickup params
        $searchTerm = trim($request->query->get('t'));
        if (strlen($searchTerm) == 0) {
            return $this->redirect(CoreHelper::getCoreSitePath());
        }

        $pageTitle = TranslateHelper::t("search_page_title", "Searching [[[TERM]]]", ['TERM' => str_replace(['"', '\''], '', $searchTerm)]);
        $filterUploadedDateRange = $request->query->has('filterUploadedDateRange') ? $request->query->get('filterUploadedDateRange') : '';

        // prep params for template
        $templateParams = $this->getFileManagerTemplateParams();
        $templateParams = array_merge([
            'pageTitle' => $pageTitle,
            'pageType' => 'search',
            'searchTerm' => $searchTerm,
            'initialFileId' => null,
            'filterUploadedDateRange' => $filterUploadedDateRange,
        ], $templateParams);

        // load template
        return $this->render('account/index.html', $templateParams);
    }

    
    public function ajaxSearchWidget() {
        // setup result array
        $rs = [];

        // load database
        $db = Database::getDatabase();
        $Auth = AuthHelper::getAuth();

        // get variables
        $request = $this->getRequest();
        $type = $request->query->get("type");
        $query = $request->query->get("query");
        if (strlen($query) == 0) {
            return $this->renderJson($rs);
        }

        switch ($type) {
            case 'images':
                // lookup images (in this theme this is actually all files)
                $images = $db->getRows('SELECT file.*, users.username, file_folder.folderName '
                        . 'FROM file '
                        . 'LEFT JOIN users ON file.userId = users.id '
                        . 'LEFT JOIN file_folder ON file.folderId = file_folder.id '
                        . 'WHERE file.status = "active" AND '
                        . '('
                        . 'file.userId = ' . (int) $Auth->id . ' OR file.uploadedUserId = ' . (int) $Auth->id . ' '
                        . 'OR ((file.folderId IN (SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE file_folder_share.shared_with_user_id = ' . (int) $Auth->id . ')))'
                        . ') '
                        . 'AND (file.originalFilename LIKE "%' . $db->escape($query) . '%" OR file.shortUrl LIKE "%' . $db->escape($query) . '%" OR file.keywords LIKE "%' . $db->escape($query) . '%") '
                        . 'ORDER BY uploadedDate DESC '
                        . 'LIMIT 10');
                if ($images) {
                    foreach ($images AS $image) {
                        // hydrate so we have access to the file object
                        $fileObj = File::hydrateSingleRecord($image);

                        // prepare data
                        $lRs = [];
                        $lRs['id'] = $image['id'];
                        $lRs['title'] = $image['originalFilename'];
                        $lRs['url'] = $fileObj->getFullShortUrl();
                        $lRs['thumbnail'] = FileHelper::getIconPreviewImageUrl($fileObj, false, 48, false, 100, 100, 'middle');
                        $lRs['owner'] = strlen($image['username']) ? $image['username'] : TranslateHelper::t('guest_user', 'Guest User');
                        $lRs['uploaded_date'] = CoreHelper::formatDate($image['uploadedDate']);
                        $lRs['folder_name'] = strlen($image['folderName']) ? ('in ' . $image['folderName']) : '';
                        $lRs['none'] = '';

                        // add to overall array
                        $rs[] = $lRs;
                    }
                }
                break;

            case 'folders':
                // lookup folders
                $folders = $db->getRows('SELECT file_folder.id, file_folder.userId, users.username, file_folder.parentId, file_folder.folderName, file_folder.accessPassword, file_folder.coverImageId, (SELECT COUNT(file.id) FROM file WHERE file.folderId = file_folder.id AND file.status = "active") AS total_files '
                        . 'FROM file_folder '
                        . 'LEFT JOIN users ON file_folder.userId = users.id '
                        . 'WHERE file_folder.status IN ("active", "trash") AND (file_folder.folderName LIKE "%' . $db->escape($query) . '%") AND '
                        . '('
                        . 'file_folder.userId = ' . (int) $Auth->id . ' '
                        . 'OR ((file_folder.id IN (SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE file_folder_share.shared_with_user_id = ' . (int) $Auth->id . ')))'
                        . ') '
                        . 'ORDER BY date_added DESC, folderName ASC '
                        . 'LIMIT 10');
                if ($folders) {
                    foreach ($folders AS $folder) {
                        // hydrate so we have access to the folder object
                        $folderObj = FileFolder::hydrateSingleRecord($folder);

                        $icon = 'folder_full_fm_grid.png';
                        if ($folder['fileCount'] == 0 && $folder['isPublic'] == 1) {
                            $icon = 'folder_fm_grid.png';
                        }
                        elseif ($folder['fileCount'] >= 0 && $folder['isPublic'] == 0) {
                            $icon = 'folder_lock_fm_grid.png';
                        }

                        // prepare data
                        $lRs = [];
                        $lRs['id'] = $folder['id'];
                        $lRs['title'] = $folder['folderName'];
                        $lRs['url'] = $folderObj->getFolderUrl();
                        $lRs['thumbnail'] = ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/' . $icon;
                        $lRs['owner'] = strlen($folder['username']) ? $folder['username'] : TranslateHelper::t('guest_user', 'Guest User');
                        $lRs['total_files'] = $folder['total_files'] . ' ' . ($folder['total_files'] == 1 ? (TranslateHelper::t('file', 'file')) : (TranslateHelper::t('files', 'files')));
                        $lRs['none'] = '';

                        // add to overall array
                        $rs[] = $lRs;
                    }
                }
                break;
        }

        return $this->renderJson($rs);
    }
}
