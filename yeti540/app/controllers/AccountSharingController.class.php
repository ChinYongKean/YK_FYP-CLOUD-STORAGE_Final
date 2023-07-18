<?php

namespace App\Controllers;

use App\Models\FileFolderShare;
use App\Helpers\CoreHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;

class AccountSharingController extends AccountController
{
    public function viewShare($accessKey) {
        // load share
        $fileFolderShare = null;
        if ((strlen($accessKey) === 64) || (strlen($accessKey) === 128)) {
            $fileFolderShare = FileFolderShare::loadOne('access_key', $accessKey);
        }
        if (!$fileFolderShare) {
            // no share found
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountWebRoot());
        }

        // set last accessed
        $fileFolderShare->last_accessed = CoreHelper::sqlDateTime();
        $fileFolderShare->save();

        // load all folder ids and store in the session for later, note that this
        // if only the top level for the initial page
        $fileFolderShareItems = $fileFolderShare->getFileFolderShareItems();
        if(count($fileFolderShareItems)) {
            foreach($fileFolderShareItems AS $fileFolderShareItem) {
                if($fileFolderShareItem->folder_id !== null) {
                    $_SESSION['sharekeyFolder' . $fileFolderShareItem->folder_id] = true;
                }
                else {
                    $_SESSION['sharekeyFile' . $fileFolderShareItem->file_id] = true;
                }
            }
        }
        
        // store the original url in the session
        $_SESSION['sharekeyFileFolderShareId'] = $fileFolderShare->id;
        $_SESSION['sharekeyOriginalUrl'] = $fileFolderShare->getFullSharingUrl();

        // page OG info (for facebook)
        define("PAGE_OG_TITLE", TranslateHelper::t("shared_files_and_folders", "Shared Files & Folders"));
        define("PAGE_OG_SITE_NAME", SITE_CONFIG_SITE_NAME);

        // prep params for template
        $templateParams = $this->getFileManagerTemplateParams();
        $templateParams = array_merge([
            'pageTitle' => PAGE_OG_TITLE,
            'pageType' => 'nonaccountshared',
            'initialFileId' => null,
            'sharekeyOriginalUrl' => $_SESSION['sharekeyOriginalUrl'],
        ], $templateParams);

        // load template
        return $this->render('account/index.html', $templateParams);
    }
}
