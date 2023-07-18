<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\File;
use App\Models\FileFolder;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\FileFolderHelper;
use App\Helpers\FileManagerHelper;
use App\Helpers\InternalNotificationHelper;
use App\Helpers\PluginHelper;
use App\Helpers\UserHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\StatsHelper;

class AccountController extends BaseController
{

    public function index($initialFileId = null, $restrictFileView = false)
    {
        // page OG info (for facebook)
        define("PAGE_OG_SITE_NAME", SITE_CONFIG_SITE_NAME);
        if ($initialFileId !== null) {
            $file = File::loadOneById($initialFileId);
            if ($file) {
                define("PAGE_OG_TITLE", $file->originalFilename.' - '.SITE_CONFIG_SITE_NAME);
                define("PAGE_OG_DESCRIPTION", strlen($file->description) ? substr($file->description, 0,
                    250) : substr(UCWords(TranslateHelper::t('view_on', 'View on')).' '.SITE_CONFIG_SITE_NAME, 0, 300));
                define("PAGE_OG_KEYWORDS", $file->getFileKeywords());
                define("PAGE_OG_URL", $file->getFullShortUrl());

                // don't show thumbnail if the album is private or has a password
                if ((int)$file->folderId) {
                    // check for password
                    $folderPassword = null;
                    $folder = FileFolder::loadOneById($file->folderId);
                    if ($folder) {
                        $folderPassword = $folder->accessPassword;
                    }

                    // check for privacy
                    $public = true;
                    if (((int)$folder->userId > 0) && ($folder->userId != $Auth->id)) {
                        if (!CoreHelper::getOverallPublicStatus($folder->userId, $folder->id)) {
                            $public = false;
                        }
                    }
                    if ($public && !$folderPassword) {
                        $imageLink = FileHelper::getIconPreviewImageUrl($file, false, 64, false, 280, 280, 'middle');
                        define("PAGE_OG_IMAGE", $imageLink);

                        // if this an image, set the actual size
                        if ($file->isImage()) {
                            define("PAGE_OG_IMAGE_WIDTH", 280);
                            define("PAGE_OG_IMAGE_HEIGHT", 280);
                        }
                    }
                }
            }
        } else {
            // require user login
            if (($response = $this->requireLogin()) !== false) {
                return $response;
            }
        }

        // page OG info (for facebook)
        // @TODO - move to template
        if (!defined('PAGE_OG_TITLE')) {
            define("PAGE_OG_TITLE", TranslateHelper::t("loading", "Loading..."));
        }
        if (!defined('PAGE_OG_DESCRIPTION')) {
            define("PAGE_OG_DESCRIPTION", defined('PAGE_DESCRIPTION') ? PAGE_DESCRIPTION : '');
        }
        if (!defined('PAGE_OG_KEYWORDS')) {
            define("PAGE_OG_KEYWORDS", '');
        }
        define("FROM_ACCOUNT_HOME", true);

        // get params for later
        $Auth = $this->getAuth();
        $request = $this->getRequest();

        // reload session encase they've just upgraded
        $Auth->reloadSession();

        // prep params for template
        $templateParams = $this->getFileManagerTemplateParams();
        $templateParams = array_merge([
            'pageTitle' => PAGE_OG_TITLE,
            'initialFileId' => $initialFileId !== null ? (int)$initialFileId : null,
            'restrictFileView' => $restrictFileView,
            'pageType' => 'folder',
            'totalActiveFiles' => isset($Auth->user) ? $Auth->user->getTotalActiveFiles() : 0,
            'totalRootFiles' => isset($Auth->user) ? (int)FileHelper::getTotalActiveFilesByUserFolderId($Auth->user->id,
                null) : 0,
            'totalSharedWithMeFiles' => isset($Auth->user) ? $Auth->user->getTotalSharedWithMeFiles() : 0,
            'totalTrash' => isset($Auth->user) ? $Auth->user->getTotalTrashFiles() : 0,
            'initialLoadFolderId' => -1,
            'triggerUpload' => (int)$request->query->has('triggerUpload'),
        ], $templateParams);

        // load template
        return $this->render('account/index.html', $templateParams);
    }

    protected function getFileManagerTemplateParams()
    {
        // get params for later
        $Auth = $this->getAuth();

        // load folders
        $folderArr = [];
        if ($Auth->loggedIn()) {
            $folderArr = FileFolderHelper::loadAllActiveForSelect($Auth->user->id);
        }

        // figure out max files
        $maxFiles = UserHelper::getMaxUploadsAtOnce($Auth->package_id);

        // failsafe
        if ((int)$maxFiles == 0) {
            $maxFiles = 200;
        }

        // if php restrictions are lower than permitted, override
        $phpMaxSize = CoreHelper::getPHPMaxUpload();
        $maxUploadSizeNonChunking = 0;
        if ($phpMaxSize < UserHelper::getMaxUploadFilesize()) {
            $maxUploadSizeNonChunking = $phpMaxSize;
        }

        // load folder structure as array
        $folderListing = FileFolderHelper::loadAllActiveForSelect($Auth->id, '|||');
        $folderListingArr = [];
        foreach ($folderListing as $k => $folderListingItem) {
            $folderListingArr[$k] = $folderListingItem;
        }

        // load all notifications in the past 14 days for current user
        $internalNotifications = InternalNotificationHelper::loadRecentByUser($Auth->id);
        $unreadNotificationCount = 0;
        foreach ($internalNotifications as $internalNotification) {
            if ($internalNotification->is_read == 0) {
                $unreadNotificationCount++;
            }
        }

        // plugins
        $fileManagerPluginLefthandBottom = PluginHelper::callHookRecursive('fileManagerLefthandBottom');
        ksort($fileManagerPluginLefthandBottom);
        $accountHomePluginJavascript = PluginHelper::callHookRecursive('accountHomeJavascript');
        ksort($accountHomePluginJavascript);
        $fileManagerPluginTabs = PluginHelper::callHookRecursive('fileManagerTab');
        ksort($fileManagerPluginTabs);
        $fileManagerPluginTabContents = PluginHelper::callHookRecursive('fileManagerTabContent');
        ksort($fileManagerPluginTabContents);

        return [
            'userAllowedToUpload' => UserHelper::getAllowedToUpload(),
            'userAllowedToRemoteUpload' => UserHelper::userTypeCanUseRemoteUrlUpload(),
            'maxUploadSize' => UserHelper::getMaxUploadFilesize(),
            'maxUploadSizeBoth' => CoreHelper::formatSize(UserHelper::getMaxUploadFilesize(), 'both'),
            'maxPermittedUrls' => (int)UserHelper::getMaxRemoteUrls(),
            'acceptedFileTypes' => UserHelper::getAcceptedFileTypes(),
            'acceptedFileTypesStr' => implode(', ', UserHelper::getAcceptedFileTypes()),
            'acceptedFileTypesUploaderStr' => str_replace('.', '', implode('|', UserHelper::getAcceptedFileTypes())),
            'folderArr' => $folderArr,
            'currentBrowserIsIE' => StatsHelper::currentBrowserIsIE(),
            'maxFiles' => $maxFiles,
            'maxUploadSizeNonChunking' => $maxUploadSizeNonChunking,
            'phpMaxSize' => $phpMaxSize,
            'orderByOptions' => FileManagerHelper::getFileBrowserSortingOptions(),
            'additionalHeaderNavigation' => PluginHelper::generateHeaderNavStructure($Auth->level_id),
            'folderListingArr' => $folderListingArr,
            'sessionId' => session_id(),
            'cTracker' => md5(microtime().CoreHelper::generateRandomHash()),
            'internalNotifications' => $internalNotifications,
            'unreadNotificationCount' => $unreadNotificationCount,
            'fileManagerPluginLefthandBottom' => $fileManagerPluginLefthandBottom,
            'accountHomePluginJavascript' => $accountHomePluginJavascript,
            'fileManagerPluginTabs' => $fileManagerPluginTabs,
            'fileManagerPluginTabContents' => $fileManagerPluginTabContents,
        ];
    }

}
