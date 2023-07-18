<?php

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\UserActionLogHelper;
use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileFolderShare;
use App\Models\User;
use App\Helpers\AuthHelper;
use App\Helpers\CacheHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\FileFolderHelper;
use App\Helpers\FileManagerHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\SharingHelper;
use App\Helpers\ValidationHelper;

class AccountFolderController extends AccountController
{

    public function viewFolder($folderUrlHash, $folderName = null) {
        // pickup request
        $request = $this->getRequest();
        $db = Database::getDatabase();

        // load folder
        $fileFolder = FileFolder::loadOne('urlHash', $folderUrlHash);
        if (!$fileFolder) {
            // support legacy folder urls (there were based on the folder id instead
            // of the urlHash)
            if(SITE_CONFIG_SUPPORT_LEGACY_FOLDER_URLS === 'Enabled') {
                $fileFolder = FileFolder::loadOne('id', $folderUrlHash);
            }
            
            if(!$fileFolder) {
                // no folder found
                return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountWebRoot());
            }
        }

        // for inactive folders
        if ($fileFolder->status !== 'active') {
            // no folder found
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountWebRoot());
        }

        // store 'sharekey' if we have it
        $isValid = false;
        if ($request->query->has('sharekey')) {
            // check if the key is valid
            $sharekey = trim($request->query->get('sharekey'));
            if (strlen($sharekey) === 64) {
                $isValid = $db->getValue('SELECT file_folder_share.id '
                        . 'FROM file_folder_share '
                        . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                        . 'WHERE access_key = ' . $db->quote($sharekey) . ' '
                        . 'AND folder_id = ' . (int) $fileFolder->id . ' '
                        . 'LIMIT 1');
                if ($isValid) {
                    $db->query('UPDATE file_folder_share '
                            . 'SET last_accessed = NOW() '
                            . 'WHERE id = ' . (int) $isValid . ' '
                            . 'LIMIT 1');
                    $_SESSION['sharekeyFolder' . $fileFolder->id] = true;
                }
            }
        }
        // clear it if we don't have it
        if ($isValid === false) {
            $_SESSION['sharekeyFolder' . $fileFolder->id] = false;
            unset($_SESSION['sharekeyFolder' . $fileFolder->id]);
        }

        // load cover details for OG image
        $coverData = FileFolderHelper::getFolderCoverData($fileFolder->id);
        $coverId = (int) $coverData['file_id'];

        // page OG info (for facebook)
        define("PAGE_OG_TITLE", $fileFolder->folderName . ' ' . TranslateHelper::t("folder_page_name", "Folder"));
        define("PAGE_OG_SITE_NAME", SITE_CONFIG_SITE_NAME);
        define("PAGE_OG_DESCRIPTION", defined('PAGE_DESCRIPTION')?PAGE_DESCRIPTION:'');
        if ($coverId) {
            $file = File::loadOneById($coverId);
            if ($file) {
                define("PAGE_OG_TITLE", substr(UCWords(TranslateHelper::t('View', 'view')) . ' ' . $file->originalFilename . ' ' . TranslateHelper::t('on', 'on') . ' ' . SITE_CONFIG_SITE_NAME, 0, 150));
                define("PAGE_OG_SITE_NAME", SITE_CONFIG_SITE_NAME);

                // don't show thumbnail if the album is private or has a password
                if ((int) $file->folderId) {
                    // check for password
                    $folderPassword = $fileFolder->accessPassword;

                    // check for privacy
                    $public = true;
                    $Auth = $this->getAuth();
                    if (((int) $fileFolder->userId > 0) && ($fileFolder->userId != $Auth->id)) {
                        if (CoreHelper::getOverallPublicStatus($fileFolder->userId, $fileFolder->id) == false) {
                            $public = false;
                        }
                    }
                    if (($public === true) && ($folderPassword != true)) {
                        define("PAGE_OG_IMAGE", FileHelper::getIconPreviewImageUrl($file, false, 64, false, 280, 280, 'middle'));
                    }
                }
            }
        }

        // prep params for template
        $templateParams = $this->getFileManagerTemplateParams();
        $templateParams = array_merge([
            'pageTitle' => $fileFolder->folderName . ' ' . TranslateHelper::t("folder_page_name", "Folder"),
            'pageType' => 'folder',
            'initialLoadFolderId' => $fileFolder->id,
            'initialFileId' => null,
            'sharekeyOriginalUrl' => $_SESSION['sharekeyOriginalUrl'],
        ], $templateParams);

        // load template
        return $this->render('account/index.html', $templateParams);
    }

    public function nonFolderFileManagerPage() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // switch content depending on the route
        $pageType = '-1';
        switch ($this->getCurrentRoute()) {
            case '/account/shared_with_me':
                $pageType = 'shared';
                break;
            case '/account/recent':
                $pageType = 'recent';
                break;
            case '/account/all_files':
                $pageType = 'all';
                break;
            case '/account/trash':
                $pageType = 'trash';
                break;
        }

        // prep params for template
        $templateParams = $this->getFileManagerTemplateParams();
        $templateParams = array_merge([
            'pageType' => $pageType,
        ], $templateParams);

        // load template
        return $this->render('account/index.html', $templateParams);
    }

    public function ajaxAddEditFolder() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get the current logged in user
        $Auth = AuthHelper::getAuth();
        $db = Database::getDatabase();

        // pickup request for later
        $request = $this->getRequest();

        // load folder structure as array
        $folderListing = FileFolderHelper::loadAllActiveForSelect($Auth->id);

        // initial parent folder
        $parentId = '-1';
        if ($request->request->has('parentId')) {
            $parentId = (int) $request->request->get('parentId');
        }

        // defaults
        $isPublic = 1;
        $editFolderId = null;
        $folderName = '';
        $accessPassword = null;
        $watermarkPreviews = 0;
        $showDownloadLinks = 1;
        $folderUrl = '';
        if ($request->request->has('editFolderId')) {
            // load existing folder data
            $fileFolder = FileFolder::loadOneById((int) $request->request->get('editFolderId'));
            if ($fileFolder) {
                // check current user has permission to edit the fileFolder
                if ($fileFolder->userId === $Auth->id) {
                    // setup edit folder
                    $editFolderId = $fileFolder->id;
                    $folderName = $fileFolder->folderName;
                    $parentId = $fileFolder->parentId;
                    $isPublic = $fileFolder->isPublic;
                    $accessPassword = $fileFolder->accessPassword;
                    $watermarkPreviews = (int) $fileFolder->watermarkPreviews;
                    $showDownloadLinks = (int) $fileFolder->showDownloadLinks;
                    $folderUrl = $fileFolder->getFolderUrl();
                }
            }
        }

        $userIsPublic = 1;
        $folderIsPublic = 1;
        $globalPublic = 1;

        if (CoreHelper::getUserPublicStatus($Auth->id) === false) {
            $userIsPublic = 0;
        }

        if (CoreHelper::getUserFoldersPublicStatus($editFolderId) === false || CoreHelper::getUserFoldersPublicStatus($parentId) === false) {
            $folderIsPublic = 0;
        }

        if (CoreHelper::getOverallSitePrivacyStatus() === false) {
            $globalPublic = 0;
        }

        // load template
        return $this->render('account/ajax/add_edit_folder.html', [
                    'editFolderId' => $editFolderId,
                    'folderName' => $folderName,
                    'parentId' => $parentId,
                    'isPublic' => $isPublic,
                    'accessPassword' => $accessPassword,
                    'watermarkPreviews' => $watermarkPreviews,
                    'showDownloadLinks' => $showDownloadLinks,
                    'folderListing' => $folderListing,
                    'userIsPublic' => $userIsPublic,
                    'folderIsPublic' => $folderIsPublic,
                    'globalPublic' => $globalPublic,
                    'folderUrl' => $folderUrl,
                    'currentFolderStr' => $editFolderId !== null ? $folderListing[$editFolderId] : '',
        ]);
    }

    public function ajaxAddEditFolderProcess() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get the current logged-in user
        $Auth = AuthHelper::getAuth();
        $db = Database::getDatabase();

        // pickup request for later
        $request = $this->getRequest();

        // load folder structure as array
        $folderListing = FileFolderHelper::loadAllActiveForSelect($Auth->id);

        // handle submission
        if ($request->request->has('submitme')) {
            // validation
            $folderName = trim($request->request->get('folderName'));
            $isPublic = (int) $request->request->get('isPublic');
            $enablePassword = false;
            if ($request->request->has('enablePassword')) {
                $enablePassword = true;
                $password = trim($request->request->get('password'));
            }
            $watermarkPreviews = (int) $request->request->get('watermarkPreviews');
            $showDownloadLinks = (int) $request->request->get('showDownloadLinks');

            $parentId = (int) $request->request->get('parentId');
            $editFolderId = null;
            if (!strlen($folderName)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_the_foldername", "Please enter the folder name"));
            }
            elseif ($this->inDemoMode()) {
                NotificationHelper::setError(TranslateHelper::t("no_changes_in_demo_mode"));
            }
            else {
                if ($request->request->has('editFolderId')) {
                    // load existing folder data
                    $fileFolder = FileFolder::loadOneById((int) $request->request->get('editFolderId'));
                    if ($fileFolder) {
                        // check current user has permission to edit the fileFolder
                        if ($fileFolder->userId == $Auth->id) {
                            // setup edit folder
                            $editFolderId = $fileFolder->id;
                        }
                    }
                }

                $extraClause = '';
                if ($editFolderId !== null) {
                    $extraClause = ' AND id != ' . (int) $editFolderId;
                }

                // check for existing folder
                $rs = $db->getRow('SELECT id '
                        . 'FROM file_folder '
                        . 'WHERE status = "active" '
                        . 'AND folderName = :folderName '
                        . 'AND parentId ' . ($parentId == '-1' ? ('IS NULL') : ('= ' . (int) $parentId)) . ' '
                        . 'AND userId = :userId' . $extraClause, [
                    'folderName' => $folderName,
                    'userId' => $Auth->id,
                ]);
                if ($rs) {
                    if (count($rs)) {
                        NotificationHelper::setError(TranslateHelper::t("already_an_folder_with_that_name", "You already have an folder with that name, please use another"));
                    }
                }
            }

            // create the folder
            if (!NotificationHelper::isErrors()) {
                // make sure the user owns the parent folder to stop tampering
                if (!isset($folderListing[$parentId])) {
                    $parentId = 0;
                }

                if ($parentId == 0) {
                    $parentId = null;
                }

                // get database connection
                $db = Database::getDatabase();

                // update folder
                $oldFileFolder = [];
                if ($editFolderId !== null) {
                    $fileFolder = FileFolder::loadOneById($editFolderId);
                    $oldFileFolder = (array) $fileFolder;
                    $originalWatermarkPreviews = (int)$fileFolder->watermarkPreviews;
                }
                // add folder
                else {
                    $fileFolder = FileFolder::create();
                    
                    // the userId should always be the same as the parentId, unless in root
                    $folderUserId = $Auth->id;
                    if($parentId != null) {
                        $parentFolder = FileFolder::loadOneById($parentId);
                        if($parentFolder) {
                            $folderUserId = $parentFolder->userId;
                        }
                    }
                    $fileFolder->userId = $folderUserId;
                    $fileFolder->addedUserId = $Auth->id;
                    $fileFolder->urlHash = FileFolderHelper::generateRandomFolderHash();
                }

                $fileFolder->folderName = $folderName;
                $fileFolder->isPublic = $isPublic;
                $fileFolder->parentId = $parentId;
                $fileFolder->watermarkPreviews = $watermarkPreviews;
                $fileFolder->showDownloadLinks = $showDownloadLinks;

                // update password
                $passwordHash = '';
                if ($enablePassword == true) {
                    if ((strlen($password)) && ($password != '**********')) {
                        $passwordHash = md5($password);
                    }
                }
                else {
                    // remove existing password
                    $passwordHash = null;
                }
                if (($passwordHash === null) || (strlen($passwordHash))) {
                    $fileFolder->accessPassword = $passwordHash;
                }

                // save folder
                $fileFolder->date_updated = CoreHelper::sqlDateTime();
                $fileFolder->save();

                // extra section for adds
                if ($editFolderId === null) {
                    // ensure we've setup the sharing permissions for the new folder
                    if ($parentId !== NULL) {
                        FileFolderHelper::copyPermissionsToNewFolder($parentId, $fileFolder->id);
                    }

                    // user action logs
                    UserActionLogHelper::log('Folder added', 'FOLDER', 'ADD', [
                        'folder_id' => $fileFolder->id,
                        'data' => [
                            'folder_name' => $fileFolder->folderName,
                            'is_public' => $fileFolder->isPublic,
                            'parent_id' => $fileFolder->parentId,
                        ],
                    ]);

                    // success message
                    NotificationHelper::setSuccess(TranslateHelper::t("folder_created", "Folder created."));
                    $editFolderId = $fileFolder->id;
                }
                else {
                    // if the watermark option has changed, ensure we remove any cached previews
                    if ((int) $originalWatermarkPreviews != (int) $watermarkPreviews) {
                        $files = FileHelper::loadAllActiveByFolderId($editFolderId);
                        if ($files) {
                            $pluginObj = PluginHelper::getInstance('filepreviewer');
                            foreach ($files AS $file) {
                                $pluginObj->deleteImagePreviewCache($file->id);
                            }
                        }
                    }

                    // user action logs
                    UserActionLogHelper::log('Folder edited', 'FOLDER', 'UPDATE', [
                        'folder_id' => $fileFolder->id,
                        'data' => UserActionLogHelper::getChangedData($oldFileFolder, $fileFolder),
                    ]);

                    // success message
                    NotificationHelper::setSuccess(TranslateHelper::t("folder_updated", "Folder updated."));
                }
            }
        }

        // prepare result
        $returnJson = [];
        $returnJson['success'] = false;
        $returnJson['msg'] = TranslateHelper::t("problem_updating_folder", "There was a problem updating the folder, please try again later.");
        if (NotificationHelper::isErrors()) {
            // error
            $returnJson['success'] = false;
            $returnJson['msg'] = implode('<br/>', NotificationHelper::getErrors());
        }
        else {
            // success
            $returnJson['success'] = true;
            $returnJson['msg'] = implode('<br/>', NotificationHelper::getSuccess());
        }
        $returnJson['folder_id'] = $editFolderId;

        // rebuild folder html
        $folderArr = [];
        if ($Auth->loggedIn()) {
            // clear any cache to allow for the new folder
            CacheHelper::clearCache('FOLDER_ACTIVE_OBJECTS_BY_USERID_' . (int) $Auth->id);
            $folderArr = FileFolderHelper::loadAllActiveForSelect($Auth->id);
        }
        $returnJson['folder_listing_html'] = '<select id="upload_folder_id" name="upload_folder_id" class="form-control" ' . (!$Auth->loggedIn() ? 'DISABLED="DISABLED"' : '') . '>';
        $returnJson['folder_listing_html'] .= '	<option value="">' . (!$Auth->loggedIn() ? TranslateHelper::t("index_login_to_enable", "- login to enable -") : TranslateHelper::t("index_default", "- default -")) . '</option>';
        if (count($folderArr)) {
            foreach ($folderArr AS $id => $folderLabel) {
                $returnJson['folder_listing_html'] .= '<option value="' . (int) $id . '"';
                if ($fid == (int) $id) {
                    $returnJson['folder_listing_html'] .= ' SELECTED';
                }
                $returnJson['folder_listing_html'] .= '>' . ValidationHelper::safeOutputToScreen($folderLabel) . '</option>';
            }
        }
        $returnJson['folder_listing_html'] .= '</select>';

        // output response
        return $this->renderJson($returnJson);
    }

    public function ajaxGenerateFolderSharingUrl() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get params for later
        $Auth = $this->getAuth();
        $db = Database::getDatabase();

        // prepare result
        $result = [];
        $result['error'] = true;
        $result['msg'] = 'Error generating url.';

        // get items and validate
        $fileIds = [];
        $folderIds = [];
        $safeItems = $this->getSafeFileFolderIdsFromRequest();
        if (!$safeItems) {
            NotificationHelper::setError(TranslateHelper::t("failed_loading_file_or_folder_ids", "Failed loading file or folders."));
        }
        else {
            $fileIds = $safeItems['fileIds'];
            $folderIds = $safeItems['folderIds'];
        }

        // create new share
        $fileFolderShare = SharingHelper::createShare($fileIds, $folderIds, null, 'view');

        if ($fileFolderShare) {
            $sharingUrl = $fileFolderShare->getFullSharingUrl();

            // only log each for < 100 for performance reasons
            if(count($fileIds) < 100 && count($folderIds) < 100) {
                foreach($fileIds as $fileId) {
                    // user action logs
                    UserActionLogHelper::log('File shared via URL', 'SHARE', 'ADD', [
                        'file_id' => $fileId,
                        'data' => [
                            'sharing_url' => $sharingUrl,
                            'file_folder_share_id' => $fileFolderShare->id,
                        ],
                    ]);
                }

                foreach($folderIds as $folderId) {
                    // user action logs
                    UserActionLogHelper::log('Folder shared via URL', 'SHARE', 'ADD', [
                        'folder_id' => $folderId,
                        'data' => [
                            'sharing_url' => $sharingUrl,
                            'file_folder_share_id' => $fileFolderShare->id,
                        ],
                    ]);
                }
            }
            else {
                // user action logs
                UserActionLogHelper::log('Sharing URL created', 'SHARE', 'ADD', [
                    'file_folder_share_id' => $fileFolderShare->id,
                    'data' => [
                        'sharing_url' => $sharingUrl,
                        'file_ids' => $fileIds,
                        'folder_ids' => $folderIds,
                    ],
                ]);
            }

            $result['error'] = false;
            $result['msg'] = $sharingUrl;
        }
        else {
            $result['error'] = true;
            $result['msg'] = TranslateHelper::t('could_not_create_sharing_url', 'Could not create sharing url.');
        }

        return $this->renderJson($result);
    }

    public function ajaxShareFileFolder() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get the current logged-in user
        $Auth = AuthHelper::getAuth();
        $db = Database::getDatabase();

        // pickup request for later
        $request = $this->getRequest();
        $safeItems = $this->getSafeFileFolderIdsFromRequest();
        if (!$safeItems) {
            return $this->render404();
        }

        $fileIds = $safeItems['fileIds'];
        $folderIds = $safeItems['folderIds'];

        // @TODO Lookup existing based on md5 hash of $fileIds & $folderIds
        // load template
        return $this->render('account/ajax/share_file_folder.html', [
                    'fileIds' => $fileIds,
                    'folderIds' => $folderIds,
                    'fileCount' => count($fileIds),
                    'fileFolderCount' => count($folderIds),
                    'shareLink' => 'SHARE_LINK',
        ]);
    }

    public function ajaxShareFileFolderInternally() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get the current logged-in user
        $Auth = AuthHelper::getAuth();
        $db = Database::getDatabase();

        // pickup request for later
        $request = $this->getRequest();
        $safeItems = $this->getSafeFileFolderIdsFromRequest();
        if (!$safeItems) {
            return $this->render404();
        }

        $fileIds = $safeItems['fileIds'];
        $folderIds = $safeItems['folderIds'];
        $registeredEmailAddress = strtolower(trim($request->request->get('registeredEmailAddress')));
        $registeredEmailAddressExp = explode(',', $registeredEmailAddress);
        $permissionType = $request->request->get('permissionType');
        if (!in_array($permissionType, ['view', 'upload_download', 'all'])) {
            $permissionType = 'view';
        }

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = TranslateHelper::t('share_folder_internally_success', 'If the email address exists within our system, this folder will now be available to the user within their account.');

        if (strlen($registeredEmailAddress) == 0) {
            $result['error'] = true;
            $result['msg'] = TranslateHelper::t('please_enter_an_email_address_to_share_with', 'Please enter an existing account email address to share this folder with.');
        }

        if ($result['error'] === false) {
            // add user(s) to folder
            foreach ($registeredEmailAddressExp AS $registeredEmailAddressItem) {
                // lookup account based on email
                $user = User::loadOne('email', $registeredEmailAddressItem);
                if ($user) {
                    // make sure the user isn't adding themselves
                    if ($user->id === $Auth->id) {
                        continue;
                    }

                    // add the share
                    $fileFolderShare = SharingHelper::createShare($fileIds, $folderIds, $user->id, $permissionType);

                    // send email to the recipient
                    $subject = TranslateHelper::t('share_items_internally_subject', 'Some items have been shared with you on [[[SITE_NAME]]]', ['SITE_NAME' => SITE_CONFIG_SITE_NAME]);

                    $sharingUrl = $fileFolderShare->getFullSharingUrl();
                    $replacements = [
                        'FIRST_NAME' => $user->firstname,
                        'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                        'WEB_ROOT' => WEB_ROOT,
                        'SHARE_URL' => $sharingUrl,
                    ];
                    $defaultContent = "Dear [[[FIRST_NAME]]],<br/><br/>";
                    $defaultContent .= "Some items have been shared with you on [[[SITE_NAME]]]. Login to your account or click the unique url below to access the items.<br/><br/>";
                    $defaultContent .= "<strong>Url:</strong> <a href='[[[SHARE_URL]]]'>[[[SHARE_URL]]]</a><br/><br/>";
                    $defaultContent .= "Feel free to contact us if you need any support.<br/><br/>";
                    $defaultContent .= "Regards,<br/>";
                    $defaultContent .= "[[[SITE_NAME]]] Admin";
                    $htmlMsg = TranslateHelper::t('share_items_internally_content', $defaultContent, $replacements);

                    CoreHelper::sendHtmlEmail($user->email, $subject, $htmlMsg, SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM, strip_tags(str_replace("<br/>", "\n", $htmlMsg)));

                    // only log each for < 100 for performance reasons
                    if(count($fileIds) < 100 && count($folderIds) < 100) {
                        foreach($fileIds as $fileId) {
                            // user action logs
                            UserActionLogHelper::log('File shared internally', 'SHARE', 'ADD', [
                                'file_id' => $fileId,
                                'data' => [
                                    'sharing_url' => $sharingUrl,
                                    'file_folder_share_id' => $fileFolderShare->id,
                                    'to_email' => $user->email,
                                ],
                            ]);
                        }

                        foreach($folderIds as $folderId) {
                            // user action logs
                            UserActionLogHelper::log('Folder shared internally', 'SHARE', 'ADD', [
                                'folder_id' => $folderId,
                                'data' => [
                                    'sharing_url' => $sharingUrl,
                                    'file_folder_share_id' => $fileFolderShare->id,
                                    'to_email' => $user->email,
                                ],
                            ]);
                        }
                    }
                    else {
                        // user action logs
                        UserActionLogHelper::log('Sharing URL created', 'SHARE', 'ADD', [
                            'file_folder_share_id' => $fileFolderShare->id,
                            'data' => [
                                'sharing_url' => $sharingUrl,
                                'to_email' => $user->email,
                                'file_ids' => $fileIds,
                                'folder_ids' => $folderIds,
                            ],
                        ]);
                    }
                }
            }
        }

        // output response
        return $this->renderJson($result);
    }

    private function getSafeFileFolderIdsFromRequest() {
        // prepare db connection
        $db = Database::getDatabase();
        $Auth = $this->getAuth();

        // pickup request
        $request = $this->getRequest();

        // get items and validate
        $fileIds = $request->request->has('fileIds') ? $request->request->get('fileIds') : [];
        $safeFileIds = array_map('intval', $fileIds);
        $folderIds = $request->request->has('folderIds') ? $request->request->get('folderIds') : [];
        $safeFolderIds = array_map('intval', $folderIds);

        // make sure we have some items
        if (count($fileIds) === 0 && count($folderIds) === 0) {
            // exit
            return false;
        }

        // validate ownership
        $fileCount = 0;
        if (count($safeFileIds)) {
            $fileCount = (int) $db->getValue('SELECT count(id) '
                            . 'FROM file '
                            . 'WHERE id IN (' . implode(',', $safeFileIds) . ') '
                            . 'AND userId = :user_id', [
                        'user_id' => $Auth->id,
            ]);
        }
        $fileFolderCount = 0;
        if (count($safeFolderIds)) {
            $fileFolderCount = (int) $db->getValue('SELECT count(id) '
                            . 'FROM file_folder '
                            . 'WHERE id IN (' . implode(',', $safeFolderIds) . ') '
                            . 'AND userId = :user_id', [
                        'user_id' => $Auth->id,
            ]);
        }
        if ((count($fileIds) !== $fileCount) || (count($folderIds) !== $fileFolderCount)) {
            // exit
            return false;
        }

        return [
            'fileIds' => $safeFileIds,
            'folderIds' => $safeFolderIds,
        ];
    }

    public function ajaxShareFileFolderInternallyExisting() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get params for later
        $Auth = $this->getAuth();
        $request = $this->getRequest();
        $db = Database::getDatabase();

        // get items and validate
        $safeItems = $this->getSafeFileFolderIdsFromRequest();
        if (!$safeItems) {
            NotificationHelper::setError(TranslateHelper::t("failed_loading_file_or_folder_ids", "Failed loading file or folders."));
        }
        else {
            $fileIds = $safeItems['fileIds'];
            $folderIds = $safeItems['folderIds'];
        }

        // get list of shares
        $sharedUsers = SharingHelper::getSharedUsersForFilesAndFolders($fileIds, $folderIds);

        // load template
        return $this->render('account/ajax/share_file_folder_internally_existing.html', [
                    'folderShares' => $sharedUsers,
                    'fileIds' => $fileIds,
                    'folderIds' => $folderIds,
        ]);
    }

    public function ajaxShareFileFolderInternallyRemove() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get params for later
        $Auth = $this->getAuth();

        // prepare result
        $result = [];

        // get request
        $request = $this->getRequest();
        $shareId = (int) $request->query->get('folderShareId');

        // load folder share
        $fileFolderShare = FileFolderShare::loadOneById($shareId);
        if (!$fileFolderShare) {
            $result['error'] = true;
            $result['msg'] = TranslateHelper::t('could_not_load_share', 'Could not load share for removal.');

            return $this->renderJson($result);
        }

        // check ownership
        if ($fileFolderShare->created_by_user_id != $Auth->id) {
            $result['error'] = true;
            $result['msg'] = TranslateHelper::t('could_not_load_share', 'Could not load share for removal.');

            return $this->renderJson($result);
        }

        // remove the share
        SharingHelper::removeShareById($fileFolderShare->id);

        // user action logs
        UserActionLogHelper::log('Shared removed', 'SHARE', 'DELETE', [
            'file_folder_share_id' => $fileFolderShare->id,
        ]);

        // send the result
        $result['error'] = false;
        $result['msg'] = TranslateHelper::t('share_folder_internally_success_removed', 'Access to this folder by the selected user has been removed.');

        return $this->renderJson($result);
    }

    public function ajaxEmailFolderUrl() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get params for later
        $Auth = $this->getAuth();

        // get request
        $request = $this->getRequest();
        $safeItems = $this->getSafeFileFolderIdsFromRequest();
        if (!$safeItems) {
            NotificationHelper::setError(TranslateHelper::t("failed_loading_file_or_folder_ids", "Failed loading file or folders."));
        }
        else {
            $fileIds = $safeItems['fileIds'];
            $folderIds = $safeItems['folderIds'];
            $shareEmailAddress = strip_tags(substr(strtolower(trim($request->request->get('shareEmailAddress'))), 0, 255));
            $shareExtraMessage = strip_tags(trim($request->request->get('shareExtraMessage')));
        }

        // validation
        if (strlen($shareEmailAddress) === 0) {
            NotificationHelper::setError(TranslateHelper::t("please_enter_the_recipient_email_address", "Please enter the recipient email address."));
        }
        elseif (ValidationHelper::validEmail($shareEmailAddress) == false) {
            NotificationHelper::setError(TranslateHelper::t("please_enter_a_valid_recipient_email_address", "Please enter a valid recipient email address."));
        }

        // send the email
        if (!NotificationHelper::isErrors()) {
            // add the share url
            $fileFolderShare = SharingHelper::createShare($fileIds, $folderIds, null, 'view');
            $sharingUrl = $fileFolderShare->getFullSharingUrl();

            // setup shared by names
            $sharedBy = TranslateHelper::t('guest', 'Guest');
            $sharedByEmail = '';
            if ($Auth->loggedIn()) {
                $sharedBy = $Auth->getAccountScreenName();
                $sharedByEmail = $Auth->email;
            }

            // send the email
            $subject = TranslateHelper::t('email_items_url_process_subject', 'Items shared by [[[SHARED_BY_NAME]]] on [[[SITE_NAME]]]', [
                        'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                        'SHARED_BY_NAME' => $sharedBy,
                ]
            );

            $replacements = [
                'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                'WEB_ROOT' => ThemeHelper::getLoadedInstance()->getAccountWebRoot(),
                'SHARED_BY_NAME' => $sharedBy,
                'SHARED_EMAIL_ADDRESS' => $sharedByEmail,
                'EXTRA_MESSAGE' => strlen($shareExtraMessage) ? nl2br($shareExtraMessage) : TranslateHelper::t('not_applicable_short', 'n/a'),
                'SHARING_URL' => $sharingUrl,
            ];
            $defaultContent = "[[[SHARED_BY_NAME]]] has shared the following items with you via <a href='[[[WEB_ROOT]]]'>[[[SITE_NAME]]]</a>:<br/><br/>";
            $defaultContent .= "<strong>View:</strong> [[[SHARING_URL]]]<br/>";
            $defaultContent .= "<strong>From:</strong> [[[SHARED_BY_NAME]]] [[[SHARED_EMAIL_ADDRESS]]]<br/>";
            $defaultContent .= "<strong>Message:</strong><br/>[[[EXTRA_MESSAGE]]]<br/><br/>";
            $defaultContent .= "Feel free to contact us if you have any difficulties viewing the items.<br/><br/>";
            $defaultContent .= "Regards,<br/>";
            $defaultContent .= "[[[SITE_NAME]]] Admin";
            $htmlMsg = TranslateHelper::t('email_items_url_process_content', $defaultContent, $replacements);

            CoreHelper::sendHtmlEmail($shareEmailAddress, $subject, $htmlMsg, SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM, strip_tags(str_replace("<br/>", "\n", $htmlMsg)));

            // only log each for < 100 for performance reasons
            if(count($fileIds) < 100 && count($folderIds) < 100) {
                foreach($fileIds as $fileId) {
                    // user action logs
                    UserActionLogHelper::log('File shared via email', 'SHARE', 'ADD', [
                        'file_id' => $fileId,
                        'data' => [
                            'sharing_url' => $sharingUrl,
                            'file_folder_share_id' => $fileFolderShare->id,
                            'to_email' => $shareEmailAddress,
                        ],
                    ]);
                }

                foreach($folderIds as $folderId) {
                    // user action logs
                    UserActionLogHelper::log('Folder shared via email', 'SHARE', 'ADD', [
                        'folder_id' => $folderId,
                        'data' => [
                            'sharing_url' => $sharingUrl,
                            'file_folder_share_id' => $fileFolderShare->id,
                            'to_email' => $shareEmailAddress,
                        ],
                    ]);
                }
            }
            else {
                // user action logs
                UserActionLogHelper::log('Sharing URL created', 'SHARE', 'ADD', [
                    'file_folder_share_id' => $fileFolderShare->id,
                    'data' => [
                        'sharing_url' => $sharingUrl,
                        'to_email' => $shareEmailAddress,
                        'file_ids' => $fileIds,
                        'folder_ids' => $folderIds,
                    ],
                ]);
            }

            NotificationHelper::setSuccess(TranslateHelper::t("email_share_url_process_folder_send_via_email_to", "Items shared via email to [[[RECIPIENT_EMAIL_ADDRESS]]]", [
                        'RECIPIENT_EMAIL_ADDRESS' => $shareEmailAddress,
                ]
            ));
        }

        // prepare result
        $result = [];
        $result['success'] = false;
        $result['msg'] = TranslateHelper::t("problem_sending_email", "There was a problem sending the email, please try again later.");
        if (NotificationHelper::isErrors()) {
            // error
            $result['success'] = false;
            $result['msg'] = implode('<br/>', NotificationHelper::getErrors());
        }
        else {
            // success
            $result['success'] = true;
            $result['msg'] = implode('<br/>', NotificationHelper::getSuccess());
        }

        return $this->renderJson($result);
    }

    public function ajaxHomeV2FolderListing() {
        // pickup params
        $request = $this->getRequest();
        $folder = -1;
        if ($request->query->has('folder')) {
            $folder = $request->query->get('folder');
        }
        $Auth = $this->getAuth();
        $db = Database::getDatabase();

        // prepare clause for user owned folders
        $clause = '(userId = ' . (int) $Auth->id . ' AND file_folder.status = "active" AND ';
        $clause2 = '(file_folder.id IN (SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE shared_with_user_id = ' . (int) $Auth->id . ') AND ';
        if ($folder != -1) {
            $clause .= 'parentId = ' . (int) $folder;
            $clause2 .= 'parentId = ' . (int) $folder;
        }
        else {
            $clause .= 'parentId IS NULL';

            // clause to add any shared folders
            $clause2 .= '(file_folder_share.shared_with_user_id = ' . (int) $Auth->id . ' '
                    . 'AND (file_folder.parentId NOT IN '
                    . '(SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE shared_with_user_id = ' . (int) $Auth->id . ') '
                    . 'OR file_folder.parentId IS NULL)'
                    . ')';
        }
        $clause .= ')';
        $clause2 .= ')';

        $rs = [];

        // load folder data for user
        $rows = $db->getRows('SELECT file_folder.id, folderName, totalSize, users.username, file_folder.urlHash, '
                . '(SELECT COUNT(ffchild.id) AS total FROM file_folder ffchild WHERE ffchild.parentId = file_folder.id) AS childrenCount, '
                . 'accessPassword, (SELECT COUNT(file.id) AS total FROM file WHERE folderId = file_folder.id AND '
                . 'file.status = "active") AS fileCount '
                . 'FROM file_folder '
                . 'LEFT JOIN users ON file_folder.userId = users.id '
                . 'WHERE ' . $clause . ' '
                . 'ORDER BY folderName '
                . 'LIMIT 150');
        if ($rows) {
            foreach ($rows AS $row) {
                $folderType = 'folder';
                if (((int) $row['fileCount'] > 0) || ((int) $row['childrenCount'] > 0)) {
                    $folderType = 'folderfull';
                }

                if (strlen($row['accessPassword'])) {
                    $folderType = 'folderpassword';
                }

                if ($row['shared_with_user_id'] == $Auth->id) {
                    $folderType = 'foldershared';
                }

                $permission = 'all';
                $totalSize = $row['totalSize'];
                if ($totalSize === NULL) {
                    $totalSize = FileFolderHelper::updateFolderFilesize($row['id']);
                }

                if ((int) $row['childrenCount'] > 0) {
                    $rs[$row['folderName']] = [
                        'data' => $row['folderName'] . (((int) $row['fileCount'] > 0) ? (' (' . number_format($row['fileCount']) . ')') : '') . ' ',
                        'attr' => [
                            'id' => $row['id'],
                            'owner' => $row['username'],
                            'permission' => $permission,
                            'total_size' => CoreHelper::formatSize($totalSize),
                            'title' => TranslateHelper::t('account_home_folder_treeview_double_click', 'Double click to view/hide subfolders'),
                            'rel' => $folderType,
                            'pageType' => 'folder',
                        ],
                        'children' => [
                            'state' => 'closed'
                        ],
                        'state' => 'closed'
                    ];
                }
                else {
                    $rs[$row['folderName']] = [
                        'data' => $row['folderName'] . (((int) $row['fileCount'] > 0) ? (' (' . number_format($row['fileCount']) . ')') : ''),
                        'attr' => [
                            'id' => $row['id'],
                            'owner' => $row['username'],
                            'permission' => $permission,
                            'total_size' => CoreHelper::formatSize($totalSize),
                            'title' => '',
                            'rel' => $folderType,
                            'pageType' => 'folder',
                        ]
                    ];
                }
            }
        }

        // sort by keys to order folder listing
        uksort($rs, "strnatcasecmp");

        // remove keys as they cause issues with the treeview
        $rs = array_values($rs);

        return $this->renderJson($rs);
    }

    public function ajaxLoadFiles() {
        // pickup params
        $request = $this->getRequest();

        // setup session params
        if (!isset($_SESSION['search'])) {
            $_SESSION['search'] = [];
        }
        if (!isset($_SESSION['search']['perPage'])) {
            $_SESSION['search']['perPage'] = FileManagerHelper::getPerPageDefault();
        }
        if (!isset($_SESSION['search']['filterOrderBy'])) {
            $_SESSION['search']['filterOrderBy'] = FileManagerHelper::getSortingDefault();
        }
        if (!isset($_SESSION['browse']['viewType'])) {
            $_SESSION['browse']['viewType'] = 'fileManagerIcon';
            if (FileManagerHelper::getViewLayoutDefault() == 'list') {
                $_SESSION['browse']['viewType'] = 'fileManagerList';
            }
        }

        // pickup initial params
        $pageType = $request->request->get('pageType');
        $pageStart = $request->request->get('pageStart');
        $perPage = $request->request->get('perPage') > 0 ? $request->request->get('perPage') : $_SESSION['search']['perPage'];
        $filterOrderBy = strlen($request->request->get('filterOrderBy')) ? $request->request->get('filterOrderBy') : $_SESSION['search']['filterOrderBy'];

        // advanced filters
        $searchTerm = '';
        $filterUploadedDateRange = null;
        if($request->request->has('additionalParams')) {
            $additionalParams = $request->request->get('additionalParams');
            $searchTerm = isset($additionalParams['searchTerm'])?$additionalParams['searchTerm'] : '';
            $filterUploadedDateRange = (isset($additionalParams['filterUploadedDateRange']) && strlen($additionalParams['filterUploadedDateRange']))?$additionalParams['filterUploadedDateRange'] : '';
        }

        // setup our file manager class
        $fileManager = FileManagerHelper::init($pageType);
        if (!$fileManager) {
            return $this->render404();
        }

        // set parameters on object
        $fileManager->setParameters([
            'pageType' => $pageType,
            'nodeId' => $request->request->get('nodeId'),
            'pageStart' => $pageStart,
            'perPage' => $perPage,
            'filterOrderBy' => $filterOrderBy,
            'searchTerm' => $searchTerm,
            'filterUploadedDateRange' => $filterUploadedDateRange,
        ]);

        // validate login, if required
        if ($fileManager->isCurrentUserAuthenticated() === false) {
            return $this->render404();
        }

        // get file manager html (returns an array with html and javascript items)
        $returnJson = $fileManager->getFileManagerHtml();
        $returnJson['page_title'] = !isset($returnJson['page_title']) ? $fileManager->getPageTitle() : $returnJson['page_title'];
        $returnJson['page_url'] = !isset($returnJson['page_url']) ? $fileManager->getPageUrl() : $returnJson['page_url'];

        // output response
        return $this->renderJson($returnJson);
    }

}
