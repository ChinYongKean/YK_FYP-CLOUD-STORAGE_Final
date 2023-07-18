<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Helpers\AuthHelper;
use App\Helpers\BannedIpHelper;
use App\Helpers\ChartsHelper;
use App\Helpers\CoreHelper;
use App\Helpers\CrossSiteActionHelper;
use App\Helpers\DownloadTrackerHelper;
use App\Helpers\FileFolderHelper;
use App\Helpers\FileHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\LogHelper;
use App\Helpers\PluginHelper;
use App\Helpers\StatsHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UploaderHelper;
use App\Helpers\UserActionLogHelper;
use App\Helpers\UserHelper;
use App\Helpers\ValidationHelper;
use App\Models\File;
use App\Models\User;
use App\Services\Uploader;
use App\Services\ZipFile;

class FileController extends BaseController
{
    
    public function downloadSlugHandler($downloadUrlSlug, $path = '') {
        // check for download token
        $downloadToken = null;
        if (isset($_REQUEST[File::DOWNLOAD_TOKEN_VAR])) {
            $downloadToken = $_REQUEST[File::DOWNLOAD_TOKEN_VAR];
        }
        
        if(!$downloadToken) {
            return $this->render404();
        }

        // validate slug against download token
        return $this->downloadHandler($path);
    }

    public function downloadHandler($path = '') {
        // used later
        define('_INT_DOWNLOAD_REQ', true);
        $Auth = $this->getAuth();
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // make sure uploading hasn't been disabled
        if (CoreHelper::downloadingDisabled() === true) {
            $errorMsg = TranslateHelper::t("downloading_all_blocked", "Downloading is currently disabled on the site, please try again later.");
            return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
        }

        // try to load the file object
        $file = null;
        if (isset($_REQUEST['_page_url'])) {
            // sanitise the url for compatibility with migrated scripts
            if (substr($_REQUEST['_page_url'], 0, 6) == 'image/') {
                $_REQUEST['_page_url'] = str_replace('image/', '', $_REQUEST['_page_url']);
            }
            if (substr($_REQUEST['_page_url'], strlen($_REQUEST['_page_url']) - 5) == '.html') {
                $_REQUEST['_page_url'] = str_replace('.html', '', $_REQUEST['_page_url']);
            }
            $pageUrl = ltrim(trim($_REQUEST['_page_url']));

            // only keep the initial part if there's a forward slash
            $urlParts = explode("/", $pageUrl);
            
            // allow for download slugs
            if($urlParts[0] === 'token' && $urlParts[1] === 'download') {
                $downloadUrlSlug = $urlParts[2];
                unset($urlParts[2]);
                unset($urlParts[1]);
                unset($urlParts[0]);
            }
            $shortUrl = current($urlParts);

            // allow for migrated sites
            if (substr($shortUrl, strlen($shortUrl) - 4, 4) == '.htm') {
                $shortUrl = substr($shortUrl, 0, strlen($shortUrl) - 4);
            }
            elseif (substr($shortUrl, strlen($shortUrl) - 5, 5) == '.html') {
                $shortUrl = substr($shortUrl, 0, strlen($shortUrl) - 5);
            }

            // load the file
            $file = File::loadOneByShortUrl($shortUrl);
        }

        // could not load the file
        if (!$file) {
            return $this->render404();
            //return $this->redirect(CoreHelper::getCoreSitePath());
        }

        // do we have a download token?
        $downloadToken = null;
        if (isset($_REQUEST[File::DOWNLOAD_TOKEN_VAR])) {
            $downloadToken = $_REQUEST[File::DOWNLOAD_TOKEN_VAR];
        }

        // check for download managers on original download url, ignore for token urls
        if (($downloadToken === null) && StatsHelper::isDownloadManager($_SERVER['HTTP_USER_AGENT'])) {
            // authenticate
            if (!isset($_SERVER['PHP_AUTH_USER'])) {
                header('WWW-Authenticate: Basic realm="Please enter a valid username and password"');
                header('HTTP/1.0 401 Unauthorized');
                header('status: 401 Unauthorized');
                exit;
            }

            // attempt login
            $loggedIn = $Auth->attemptLogin(trim($_SERVER['PHP_AUTH_USER']), trim($_SERVER['PHP_AUTH_PW']), false, false, null, true);
            if ($loggedIn === false) {
                header('WWW-Authenticate: Basic realm="Please enter a valid username and password"');
                header('HTTP/1.0 401 Unauthorized');
                header('status: 401 Unauthorized');
                exit;
            }

            // check account doesn't have to wait for downloads, i.e. is allowed to download directly
            // paid only for now
            if ($Auth->level_id >= 2) {
                // create token so file is downloaded below
                $downloadToken = $file->generateDirectDownloadToken();
            }
        }

        // download file
        if ($downloadToken !== null) {
            $rs = $file->download(true, true, $downloadToken);
            if (!$rs) {
                $errorMsg = TranslateHelper::t("error_can_not_locate_file", "File can not be located, please try again later.");
                if ($file->getErrorMsg() != null) {
                    $errorMsg = TranslateHelper::t("file_download_error", "Error") . ': ' . $file->getErrorMsg();
                }
                return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
            }
        }

        // setup page
        $fileKeywords = $file->getFileKeywords();
        $fileKeywords .= ',' . TranslateHelper::t("file_download_keywords", "download,file,upload,mp3,avi,zip");
        $fileDescription = $file->getFileDescription();
        define("PAGE_NAME", $file->originalFilename);
        define("PAGE_DESCRIPTION", strlen($fileDescription) ? $fileDescription : (TranslateHelper::t("file_download_description", "Download file") . ' - ' . $file->originalFilename));
        define("PAGE_KEYWORDS", $fileKeywords);
        define("TITLE_DESCRIPTION_LEFT", TranslateHelper::t("file_download_title_page_description_left", ""));
        define("TITLE_DESCRIPTION_RIGHT", TranslateHelper::t("file_download_title_page_description_right", ""));

        // clear any expired download trackers
        DownloadTrackerHelper::clearTimedOutDownloads();
        DownloadTrackerHelper::purgeDownloadData();

        // has the file been removed
        if ($file->status != 'active') {
            $errorMsg = TranslateHelper::t("error_file_has_been_removed_by_user", "File has been removed.");
            switch($file->status_reason_id) {
                case 3:
                    $errorMsg = TranslateHelper::t("error_file_has_been_removed_by_admin", "File has been removed by the site administrator.");
                    break;
                case 4:
                    $errorMsg = TranslateHelper::t("error_file_has_been_removed_due_to_copyright", "File has been removed due to copyright issues.");
                    break;
                case 5:
                    $errorMsg = TranslateHelper::t("error_file_has_expired", "File has been removed due to inactivity.");
                    break;
            }
            
            return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
        }

        // initial variables
        $skipCountdown = false;

        // call plugin hooks
        if (is_object($params = PluginHelper::callHook('fileDownloadTop', [
                            'skipCountdown' => $skipCountdown,
                            'file' => $file,
        ]))) {
            return $params;
        }
        $skipCountdown = $params['skipCountdown'];

        // if the user is not logged in, but we have http username/password. (for download managers)
        if ($Auth->loggedIn() === false) {
            if ((isset($_SERVER['PHP_AUTH_USER'])) && (isset($_SERVER['PHP_AUTH_PW']))) {
                $Auth->attemptLogin(trim($_SERVER['PHP_AUTH_USER']), MD5(trim($_SERVER['PHP_AUTH_PW'])), false);
                if ($Auth->loggedIn() === false) {
                    header('WWW-Authenticate: Basic realm="Please enter a valid username and password"');
                    header('HTTP/1.0 401 Unauthorized');
                    header('status: 401 Unauthorized');
                    exit;
                }
                else {
                    // assume download manager
                    $skipCountdown = true;
                }
            }
        }

        // whether to allow downloads or not if the user is not logged in
        if ((!$Auth->loggedIn()) && (SITE_CONFIG_REQUIRE_USER_ACCOUNT_DOWNLOAD == 'yes')) {
            return $this->redirect(CoreHelper::getCoreSitePath() . '/register?f=' . urlencode($file->shortUrl));
        }

        // check file permissions, allow owners, non-user uploads and admin/mods
        if ($file->userId != null) {
            if ((($file->userId != $Auth->id) && ($Auth->level_id < 10))) {
                // if this is a private file
                if (!CoreHelper::getOverallPublicStatus($file->userId, $file->folderId, $file->id)) {
                    $errorMsg = TranslateHelper::t("error_file_is_not_publicly_shared", "File is not publicly available.");
                    return $this->redirect(CoreHelper::getCoreSitePath() . '/error?e=' . urlencode($errorMsg));
                }
            }
        }

        // if we need to request the password
        if (strlen($file->accessPassword) && (($Auth->id != $file->userId) || ($Auth->id == ''))) {
            if (!isset($_SESSION['allowAccess' . $file->id])) {
                $_SESSION['allowAccess' . $file->id] = false;
            }

            // make sure they've not already set it
            if ($_SESSION['allowAccess' . $file->id] === false) {
                // check to see if it's been submitted
                if($request->request->has('filePassword')) {
                    // check password
                    if (md5($request->request->get('filePassword')) === $file->accessPassword)
                    {
                        $_SESSION['allowAccess' . $file->id] = true;
                    }
                    else
                    {
                        NotificationHelper::setError(TranslateHelper::t("file_password_is_invalid", "File password is invalid."));
                    }
                }
            }
            
            if ($_SESSION['allowAccess' . $file->id] === false) {
                return $this->render('file_password.html', [
                            'file' => $file,
                            'pt' => $pt,
                ]);
            }
        }

        // if the file is limited to a specific user type, check that they are permitted to see it
        if ($file->minUserLevel != null) {
            // check that the user has the correct file level
            if ((int) $Auth->level_id < (int) $file->minUserLevel) {
                if (($file->userId != NULL) && ($Auth->user_id == $file->userId)) {
                    // ignore the restriction if this is the original user which uploaded the file
                }
                else {
                    $userTypeLabel = $db->getValue('SELECT label '
                            . 'FROM user_level '
                            . 'WHERE level_id = :level_id '
                            . 'LIMIT 1', [
                                'level_id' => $file->minUserLevel,
                    ]);
                    $errorMsg = TranslateHelper::t("error_you_must_be_a_x_user_to_download_this_file", "You must be a [[[USER_TYPE]]] to download this file.", [
                        'USER_TYPE' => $userTypeLabel,
                    ]);

                    return $this->redirect(CoreHelper::getCoreSitePath() . '/upgrade?e=' . urlencode($errorMsg));
                }
            }
        }
        
        // check referrer on the file, if set via the owner
        if ($file->userId !== null) {
            // load user
            $user = User::loadOneById($file->userId);
            if($user) {
                // get allowed referrers array
                $allowedReferrers = $user->getFileReferrerWhitelist();

                // if null allow all, otherwise check
                if($allowedReferrers !== null) {
                    // get current referrer
                    $currentReferrer = CoreHelper::getReffererDomainOnly();

                    // if blank skip, otherwise make sure it's in the list of allowed
                    $allowedReferrers[] = _CONFIG_SITE_HOST_URL;
                    if(strlen($currentReferrer) && !in_array($currentReferrer, $allowedReferrers)) {
                        $errorMsg = TranslateHelper::t("error_linking_to_this_file_is_restricted_to_certain_hosts", "Referrer not allowed. The domain [[[REFERRING_DOMAIN]]] does not have approval to link to this file.", [
                            'DOMAINS' => implode(', ', $user->getFileReferrerWhitelist()),
                            'REFERRING_DOMAIN' => $currentReferrer,
                        ]);

                        return $this->redirect(CoreHelper::getCoreSitePath() . '/error?e=' . urlencode($errorMsg));
                    }
                }
            }
        }

        // free or non logged in users
        if ($Auth->level_id <= 1) {
            // make sure the user is permitted to download files of this size
            if ((int) UserHelper::getMaxDownloadSize() > 0) {
                if ((int) UserHelper::getMaxDownloadSize() < $file->getPrimaryFileSize()) {
                    $errorMsg = TranslateHelper::t("error_you_must_register_for_a_premium_account_for_filesize", "You must register for a premium account to download files of this size. Please use the links above to register or login.");
                    return $this->redirect(CoreHelper::getCoreSitePath() . '/error?e=' . urlencode($errorMsg));
                }
            }

            // check if the user has reached the max permitted concurrent downloads
            $maxThreads = UserHelper::getMaxDownloadThreads();
            if ((int) $maxThreads > 0) {
                // allow for the extra calls on an iphone
                if (($maxThreads == 1) && (StatsHelper::currentDeviceIsIos())) {
                    $maxThreads = 2;
                }

                $sQL = "SELECT count(download_tracker.id) AS total_threads ";
                $sQL .= "FROM download_tracker ";
                $sQL .= "WHERE download_tracker.status='downloading' AND download_tracker.ip_address = " . $db->quote(CoreHelper::getUsersIPAddress()) . " ";
                $sQL .= "GROUP BY download_tracker.ip_address ";
                $totalThreads = (int) $db->getValue($sQL);
                if ($totalThreads >= (int) $maxThreads) {
                    $errorMsg = TranslateHelper::t("error_you_have_reached_the_max_permitted_downloads", "You have reached the maximum concurrent downloads. Please wait for your existing downloads to complete or register for a premium account above.");
                    return $this->redirect(CoreHelper::getCoreSitePath() . '/error?e=' . urlencode($errorMsg));
                }
            }

            // make sure the user is permitted to download
            if ((int) UserHelper::getWaitTimeBetweenDownloads() > 0) {
                $sQL = "SELECT (UNIX_TIMESTAMP()-UNIX_TIMESTAMP(date_updated)) AS seconds ";
                $sQL .= "FROM download_tracker ";
                $sQL .= "WHERE download_tracker.status='finished' AND download_tracker.ip_address = " . $db->quote(CoreHelper::getUsersIPAddress()) . " ";
                $sQL .= "ORDER BY download_tracker.date_updated DESC ";
                $longAgoSeconds = (int) $db->getValue($sQL);
                if (($longAgoSeconds > 0) && ($longAgoSeconds < (int) UserHelper::getWaitTimeBetweenDownloads())) {
                    $errorMsg = TranslateHelper::t("error_you_must_wait_between_downloads", "You must wait [[[WAITING_TIME_LABEL]]] between downloads. Please try again later or register for a premium account above to remove the restriction.", ['WAITING_TIME_LABEL' => CoreHelper::secsToHumanReadable(UserHelper::getWaitTimeBetweenDownloads())]);
                    return $this->redirect(CoreHelper::getCoreSitePath() . '/error?e=' . urlencode($errorMsg));
                }
            }
        }

        // make sure the user has not reached the upper limit of daily quantity/filesize downloads
        if (UserHelper::getMaxDailyDownloads() > 0 || UserHelper::getMaxDailyDownloadSize() > 0) {
            // get total user downloads today
            $sQL = "SELECT count(stats.id) AS total_quantity, sum(file_size) AS total_filesize ";
            $sQL .= "FROM stats ";
            $sQL .= "LEFT JOIN file_artifact ON stats.file_id = file_artifact.file_id ";
            $orClause = [];
            if($Auth->loggedIn()) {
                $orClause[] = "stats.user_id = " . (int) $Auth->id;
            }
            else {
                $orClause[] = "stats.ip = " . $db->quote(CoreHelper::getUsersIPAddress());
            }
            $sQL .= "WHERE (" . implode(" OR ", $orClause) . ") ";
            $sQL .= "AND UNIX_TIMESTAMP(stats.download_date) >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 day))";
            $result = $db->getRow($sQL);
            $totalDownloadQuantity = $result['total_quantity'];
            $totalDownloadFilesize = $result['total_filesize'];

            if(UserHelper::getMaxDailyDownloads() > 0) {
                if (UserHelper::getMaxDailyDownloads() < $totalDownloadQuantity) {
                    $errorMsg = TranslateHelper::t("error_you_have_reached_the_maximum_permitted_downloads_in_the_last_24_hours", "You have reached the maximum permitted downloads in the last 24 hours.");
                    return $this->redirect(CoreHelper::getCoreSitePath() . '/error?e=' . urlencode($errorMsg));
                }
            }

            if(UserHelper::getMaxDailyDownloadSize() > 0) {
                if (UserHelper::getMaxDailyDownloadSize() < $totalDownloadFilesize) {
                    $errorMsg = TranslateHelper::t("error_you_have_reached_the_maximum_permitted_download_filesize_in_the_last_24_hours", "You have reached the maximum permitted download filesize in the last 24 hours.");
                    return $this->redirect(CoreHelper::getCoreSitePath() . '/error?e=' . urlencode($errorMsg));
                }
            }
        }

        // if user owns this file, skip download pages
        if (((int) $file->userId > 0) && ($file->userId === $Auth->id)) {
            $skipCountdown = true;
        }

        // show the download pages, if set
        if ($skipCountdown == false) {
            // increment next order
            $pt = isset($_REQUEST['pt']) ? $_REQUEST['pt'] : null;
            $downloadPageData = FileHelper::showDownloadPages($file, $pt);
            if ($downloadPageData !== false) {
                // load additional settings
                $additionalSettings = [];
                if (strlen($downloadPageData['additional_settings'])) {
                    $additionalSettings = json_decode($downloadPageData['additional_settings'], true);
                }
                
                // figure out upgrade url
                $auth = AuthHelper::getAuth();
                $upgradeUrl = CoreHelper::getCoreSitePath() . '/register?f=' . urlencode($file->shortUrl);
                if ($auth->loggedIn() === true) {
                    $upgradeUrl = CoreHelper::getCoreSitePath() . '/upgrade';
                }

                // get download page template
                $downloadPageTemplatePath = 'download_page/' . $downloadPageData['download_page'];
                $response = $this->render($downloadPageTemplatePath, [
                    'file' => $file,
                    'pt' => $pt,
                    'nextDownloadLink' => $file->getNextDownloadPageLink(),
                    'downloadPageData' => $downloadPageData,
                    'additionalSettings' => $additionalSettings,
                    'upgradeUrl' => $upgradeUrl,
                ]);

                // increment next page session tracker
                $_SESSION['_download_page_next_page_' . $file->id] ++;

                // render download page
                return $response;
            }
        }

        // do we need to display the captcha?
        if (UserHelper::showDownloadCaptcha()) {
            if (isset($_REQUEST['pt'])) {
                $_SESSION['_download_page_next_page_' . $file->id] = FileHelper::decodeNextPageHash($_REQUEST['pt']);
            }

            // do we require captcha validation?
            $showCaptcha = false;
            if (!isset($_REQUEST['g-recaptcha-response'])) {
                $showCaptcha = true;
            }

            // check captcha
            if (isset($_REQUEST['g-recaptcha-response'])) {
                $rs = CoreHelper::captchaCheck($_POST["g-recaptcha-response"]);
                if (!$rs) {
                    NotificationHelper::setError(TranslateHelper::t("invalid_captcha", "Captcha confirmation text is invalid."));
                    $showCaptcha = true;
                }
            }

            if ($showCaptcha == true) {
                // get captcha download page template
                $pt = isset($_REQUEST['pt']) ? $_REQUEST['pt'] : null;

                return $this->render('download_page/captcha.html', [
                            'file' => $file,
                            'pt' => $pt,
                ]);
            }
            else {
                if (isset($_REQUEST['pt'])) {
                    $_SESSION['_download_page_next_page_' . $file->id] = 1;
                }
            }
        }

        // include any plugin includes
        if (is_object($rs = PluginHelper::callHook('fileDownloadBottom', [
                            'file' => $file,
        ]))) {
            return $rs;
        }

        // close database so we don't cause locks during the download
        $db = Database::getDatabase();
        $db->close();

        // clear session tracker
        $_SESSION['_download_page_next_page_' . $file->id] = 1;

        // generate unique download url
        $downloadUrl = $file->generateDirectDownloadUrl();

        return $this->redirect($downloadUrl);
    }

    public function ajaxFileUploadHandler() {
        // for cross domain access
        CoreHelper::allowCrossSiteAjax();

        // no caching
        header('Pragma: no-cache');
        header('Cache-Control: private, no-cache');

        // handle OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            // for cross site CORS uploads
            return $this->renderContent('');
        }

        // log
        LogHelper::breakInLogFile();
        LogHelper::info('Upload request to ajaxFileUploadHandler: ' . http_build_query($_REQUEST));

        // process csaKeys and authenticate user
        $csaKey1 = trim($_REQUEST['csaKey1']);
        $csaKey2 = trim($_REQUEST['csaKey2']);
        if (strlen($csaKey1) && strlen($csaKey2)) {
            CrossSiteActionHelper::setAuthFromKeys($csaKey1, $csaKey2, false);
        }

        // double check user is logged in if required
        $Auth = AuthHelper::getAuth();
        if (!UserHelper::getAllowedToUpload()) {
            return $this->renderContent(CoreHelper::createUploadError(TranslateHelper::t('unavailable', 'Unavailable.'), TranslateHelper::t('uploading_has_been_disabled', 'Uploading has been disabled.')));
        }
        
        // should we require a login to upload
        if(UserHelper::loginRequiredToUpload() === true && $Auth->loggedIn() === false) {
            return $this->renderContent(CoreHelper::createUploadError(TranslateHelper::t('login_required', 'Login required.'), TranslateHelper::t('an_account_login_is_required', 'An account login is required to upload.')));
        }

        // check for banned ip
        $bannedIP = BannedIpHelper::getBannedType();
        if (strtolower($bannedIP) == 'uploading') {
            return $this->renderContent(CoreHelper::createUploadError(TranslateHelper::t('unavailable', 'Unavailable.'), TranslateHelper::t('uploading_has_been_disabled', 'Uploading has been disabled.')));
        }

        // check that the user has not reached their max permitted uploads
        $fileRemaining = UserHelper::getRemainingFilesToday();
        if ($fileRemaining === 0) {
            return $this->renderContent(CoreHelper::createUploadError(TranslateHelper::t('max_uploads_reached', 'Max uploads reached.'), TranslateHelper::t('reached_maximum_uploads', 'You have reached the maximum permitted uploads for today.')));
        }

        // check the user hasn't reached the maximum storage on their account
        if ((UserHelper::getAvailableFileStorage($Auth->id) !== null) && (UserHelper::getAvailableFileStorage($Auth->id) <= 0)) {
            return $this->renderContent(CoreHelper::createUploadError(TranslateHelper::t('file_upload_space_full', 'File upload space full.'), TranslateHelper::t('file_upload_space_full_text', 'Upload storage full, please delete some active files and try again.')));
        }

        // make sure the server meets the min upload size limits
        $uploadChunks = 100000000;
        if (isset($_REQUEST['maxChunkSize'])) {
            $uploadChunks = (int) trim($_REQUEST['maxChunkSize']);
            if ($uploadChunks === 0) {
                $uploadChunks = 100000000;
            }
        }
        if (CoreHelper::getPHPMaxUpload() < $uploadChunks) {
            return $this->renderContent(CoreHelper::createUploadError(TranslateHelper::t('file_upload_max_upload_php_limit', 'PHP Upload Limit.'), TranslateHelper::t('file_upload_max_upload_php_limit_text', 'Your PHP limits on [[[SERVER_NAME]]] need to be set to at least [[[MAX_SIZE]]] to allow larger files to be uploaded (currently [[[CURRENT_LIMIT]]]). Contact your host to set.', ['MAX_SIZE' => CoreHelper::formatSize($uploadChunks), 'SERVER_NAME' => _CONFIG_SITE_HOST_URL, 'CURRENT_LIMIT' => CoreHelper::formatSize(CoreHelper::getPHPMaxUpload())])));
        }

        // prepare upload
        $uploadHandler = new Uploader([
            'max_chunk_size' => (int) $_REQUEST['maxChunkSize'],
            'folder_id' => (int) $_REQUEST['folderId'],
        ]);

        // handle upload
        $uploadResults = $uploadHandler->post();
        $uploadResultsArr = json_decode($uploadResults, true);

        // user log on final chunk
        if(is_array($uploadResultsArr) && !empty($uploadResultsArr[0]['file_id'])) {
            // user action logs
            $file = File::loadOneById($uploadResultsArr[0]['file_id']);
            UserActionLogHelper::log('File uploaded', 'FILE', 'UPLOAD', [
                'file_id' => $file->id,
                'data' => [
                    'name' => $file->originalFilename,
                    'size' => $file->getFormattedFilesize(),
                    'folder' => $file->getFolderPath(),
                ],
            ]);
        }

        return $this->renderContent($uploadResults);
    }

    public function ajaxUpdateFileOptions() {
        // get params for later
        $Auth = $this->getAuth();

        // pickup request for later
        $request = $this->getRequest();

        // receive varables
        $fileToEmail = trim($request->request->get('fileToEmail'));
        $filePassword = trim($request->request->get('filePassword'));
        $fileFolder = (int) $request->request->get('fileFolder');
        $fileDeleteHashes = $request->request->get('fileDeleteHashes');
        $fileShortUrls = $request->request->get('fileShortUrls');

        // make sure we have some items
        if (is_array($fileDeleteHashes) && count($fileDeleteHashes) == 0) {
            // exit
            return $this->render404();
        }

        if (is_array($fileShortUrls) && (count($fileDeleteHashes) != count($fileShortUrls))) {
            // exit
            return $this->render404();
        }

        // loop items, load from the database and create email content/set password
        $fullUrls = [];
        foreach ($fileDeleteHashes AS $id => $fileDeleteHash) {
            // get short url
            $shortUrl = $fileShortUrls[$id];

            // load file
            $file = File::loadOneByShortUrl($shortUrl);
            if (!$file) {
                // failed lookup of file
                continue;
            }

            // make sure it matches the delete hash
            if ($file->deleteHash != $fileDeleteHash) {
                continue;
            }

            // update password
            if (strlen($filePassword)) {
                $file->updatePassword($filePassword);
            }

            // update folder
            if (($Auth->loggedIn()) && ($fileFolder > 0)) {
                // make sure folder is within their account
                $folders = FileFolderHelper::loadAllActiveForSelect($Auth->id);
                if (isset($folders[$fileFolder])) {
                    $file->updateFolder($fileFolder);
                }
            }

            // add full url to local array for email
            if (strlen($fileToEmail)) {
                $fullUrls[] = '<a href="' . $file->getFullShortUrl() . '">' . $file->getFullShortUrl() . '</a>';
            }
        }

        // send email
        if ((count($fullUrls)) && ValidationHelper::validEmail($fileToEmail)) {
            $subject = TranslateHelper::t('send_urls_by_email_subject', 'Your url links from [[[SITE_NAME]]]', [
                        'SITE_NAME' => SITE_CONFIG_SITE_NAME,
            ]);

            $replacements = [
                'FILE_URLS' => implode("<br/>", $fullUrls),
                'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                'WEB_ROOT' => WEB_ROOT,
                'UPDATE_COMPLETED_DATE_TIME' => date(SITE_CONFIG_DATE_TIME_FORMAT)
            ];
            $defaultContent = "Copies of your urls, which completed uploading on [[[UPDATE_COMPLETED_DATE_TIME]]] are below:<br/><br/>";
            $defaultContent .= "[[[FILE_URLS]]]<br/><br/>";
            $defaultContent .= "Regards,<br/>";
            $defaultContent .= "[[[SITE_NAME]]]";
            $htmlMsg = TranslateHelper::t('send_urls_by_email_html_content', $defaultContent, $replacements);

            CoreHelper::sendHtmlEmail($fileToEmail, $subject, $htmlMsg, SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM, strip_tags(str_replace("<br/>", "\n", $htmlMsg)));
        }

        // exit
        return $this->renderEmpty200Response();
    }

    public function ajaxUrlUploadHandler() {
        // allow for a long time to get the files
        set_time_limit(60 * 60 * 4);

        // for cross domain access
        CoreHelper::allowCrossSiteAjax();

        // no caching
        header('Pragma: no-cache');
        header('Cache-Control: private, no-cache');

        // log
        logHelper::breakInLogFile();
        logHelper::info('Remote upload request to ajaxUrlUploadHandler: ' . http_build_query($_REQUEST));

        // get url
        $url = !empty($_REQUEST["url"]) && stripslashes($_REQUEST["url"]) ? stripslashes($_REQUEST["url"]) : null;
        $rowId = (int) $_REQUEST['rowId'];

        // process csaKeys and authenticate user
        $csaKey1 = trim($_REQUEST['csaKey1']);
        $csaKey2 = trim($_REQUEST['csaKey2']);
        if (strlen($csaKey1) && strlen($csaKey2)) {
            CrossSiteActionHelper::setAuthFromKeys($csaKey1, $csaKey2, false);
        }

        // double check user is logged in if required
        $Auth = AuthHelper::getAuth();
        $userId = null;
        if ($Auth->loggedIn()) {
            $userId = (int) $Auth->id;
        }
        $folderId = (int) $_REQUEST['folderId'];

        // start uploader class
        $upload_handler = new Uploader([
            'folder_id' => (int) $folderId,
            'user_id' => $userId,
            'upload_source' => 'remote',
        ]);
        $fileUploadError = null;

        // should we require a login to upload
        if($fileUploadError === null && UserHelper::loginRequiredToUpload() === true && $userId === null) {
            $fileUploadError = CoreHelper::createUploadError(TranslateHelper::t('login_required', 'Login required.'), TranslateHelper::t('an_account_login_is_required', 'An account login is required to upload.'));
        }
        
        // check the url structure is valid
        if (($fileUploadError === null) && (filter_var($url, FILTER_VALIDATE_URL) === false)) {
            $fileUploadError = CoreHelper::createUploadError(TranslateHelper::t('url_is_invalid', 'Url is invalid.'), TranslateHelper::t('url_is_invalid_please_check', 'The structure of the url is invalid, please check and try again.'));
        }

        // check user is allowed to upload
        if (($fileUploadError === null) && (UserHelper::getAllowedToUpload() == false)) {
            $fileUploadError = CoreHelper::createUploadError(TranslateHelper::t('unavailable', 'Unavailable.'), TranslateHelper::t('uploading_has_been_disabled', 'Uploading has been disabled.'));
        }

        // check for banned ip
        $bannedIP = BannedIpHelper::getBannedType();
        if (($fileUploadError === null) && (strtolower($bannedIP) == "uploading")) {
            $fileUploadError = CoreHelper::createUploadError(TranslateHelper::t('unavailable', 'Unavailable.'), TranslateHelper::t('uploading_has_been_disabled', 'Uploading has been disabled.'));
        }

        // check that the user has not reached their max permitted uploads
        $fileRemaining = UserHelper::getRemainingFilesToday();
        if (($fileUploadError === null) && ($fileRemaining == 0)) {
            $fileUploadError = CoreHelper::createUploadError(TranslateHelper::t('max_uploads_reached', 'Max uploads reached.'), TranslateHelper::t('reached_maximum_uploads', 'You have reached the maximum permitted uploads for today.'));
        }

        // check the user hasn't reached the maximum storage on their account
        if (($fileUploadError === null) && ((UserHelper::getAvailableFileStorage($Auth->id) !== NULL) && (UserHelper::getAvailableFileStorage($Auth->id) <= 0))) {
            $fileUploadError = CoreHelper::createUploadError(TranslateHelper::t('file_upload_space_full', 'File upload space full.'), TranslateHelper::t('file_upload_space_full_text', 'Upload storage full, please delete some active files and try again.'));
        }

        // on error
        if ($fileUploadError !== null) {
            $fileUploadError = json_decode($fileUploadError, true);
            $fileUploadError = $fileUploadError[0];
            $fileUploadError['rowId'] = $rowId;
            // allow sub-domains for remote file servers
            echo CoreHelper::getDocumentDomainScript();
            $upload_handler->remote_url_event_callback(["done" => $fileUploadError]);
            exit;
        }

        // if background uploading, for logged in users only
        if ((SITE_CONFIG_REMOTE_URL_DOWNLOAD_IN_BACKGROUND == 'yes') && ($Auth->loggedIn())) {
            UploaderHelper::addUrlToBackgroundQueue($url, $Auth->id, $folderId);
            // allow sub-domains for remote file servers
            echo CoreHelper::getDocumentDomainScript();
            $upload_handler->remote_url_event_callback(["done" => 'Done']);
            exit;
        }

        // include plugin code
        $params = PluginHelper::callHook('urlUploadHandler', [
                    'url' => $url,
                    'rowId' => $rowId,
        ]);
        $url = $params['url'];

        // 1KB of initial data, required by Webkit browsers
        echo "<span>" . str_repeat("0", 1000) . "</span>";

        $fileUpload = $upload_handler->handleRemoteUrlUpload($url, $rowId);

        // user action logs
        if ($fileUpload->error === null) {
            UserActionLogHelper::log('File imported from URL', 'FILE', 'UPLOAD', [
                'file_id' => $fileUpload->file_id,
                'data' => [
                    'name' => $fileUpload->name,
                    'size' => CoreHelper::formatSize($fileUpload->size),
                    'source_url' => $url,
                ],
            ]);
        }

        // fallback
        return $this->renderContent('');
    }

    public function ajaxExistingBackgroundUrlDownload() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get the current logged in user
        $Auth = AuthHelper::getAuth();

        // pickup request for later
        $db = Database::getDatabase();

        // get existing url downloads and any recent completed
        $pendingUrlDownloads = $db->getRows('SELECT *, '
                . 'TIMESTAMPDIFF(SECOND, remote_url_download_queue.started, '
                . 'NOW()) AS startedAgo '
                . 'FROM remote_url_download_queue '
                . 'WHERE ((job_status = \'downloading\' '
                . 'OR job_status = \'pending\' '
                . 'OR job_status = \'processing\') '
                . 'AND user_id=:user_id) '
                . 'OR (finished IS NOT NULL AND finished >= DATE_SUB(NOW(), INTERVAL 2 day) '
                . 'AND user_id=:user_id) '
                . 'ORDER BY created ASC', [
            'user_id' => $Auth->id,
        ]);

        // preload other items for view
        if (count($pendingUrlDownloads)) {
            foreach ($pendingUrlDownloads AS $k => $pendingUrlDownload) {
                if ((int) $pendingUrlDownload['new_file_id']) {
                    $file = File::loadOneById($pendingUrlDownload['new_file_id']);
                    if ($file) {
                        $pendingUrlDownloads[$k]['file_short_url'] = $file->getFullShortUrl();
                        $pendingUrlDownloads[$k]['file_original_filename'] = $file->originalFilename;
                    }
                }
            }
        }

        // load template
        return $this->render('account/ajax/existing_background_url_download.html', [
                    'pendingUrlDownloads' => $pendingUrlDownloads,
        ]);
    }

    public function ajaxRemoveBackgroundUrlDownload($gRemoveUrlId) {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get the current logged in user
        $Auth = AuthHelper::getAuth();

        // pickup request for later
        $db = Database::getDatabase();

        // prepare result
        $rs = [];
        $rs['error'] = false;
        $rs['msg'] = '';

        // load url details
        $urlData = $db->getRow('SELECT id '
                . 'FROM remote_url_download_queue '
                . 'WHERE id=:id '
                . 'AND user_id = :user_id '
                . 'LIMIT 1', [
            'id' => (int) $gRemoveUrlId,
            'user_id' => (int) $Auth->id,
        ]);

        if (!$urlData) {
            $rs['error'] = true;
            $rs['msg'] = TranslateHelper::t("could_not_find_url_download", "Could not find url download.");
        }
        else {
            // delete record
            $db->query('DELETE FROM remote_url_download_queue '
                    . 'WHERE id = :id', [
                'id' => $urlData['id'],
                ]
            );
            if ($db->affectedRows() == 1) {
                // user action logs
                UserActionLogHelper::log('URL download cancelled', 'GENERAL', 'DELETE', [
                    'url_id' => $urlData['id'],
                ]);

                $rs['error'] = false;
                $rs['msg'] = 'URL download removed.';
            }
            else {
                $rs['error'] = true;
                $rs['msg'] = 'Could not remove the download task, please try again later.';
            }
        }

        return $this->renderJson($rs);
    }

    /**
     * Saved for future purposes
     * 
     * @return type
     */
    public function filePassword() {
        // get the current logged in user
        $Auth = AuthHelper::getAuth();

        // pickup request for later
        $db = Database::getDatabase();

        // load template
        return $this->render('file_password.html');
    }

    public function fileStats($shortUrl) {
        // pickup request
        $request = $this->getRequest();
        $db = Database::getDatabase();

        // try to load the file
        $file = File::loadOneByShortUrl($shortUrl);
        if (!$file) {
            // if no file found, redirect to home page
            return $this->redirect(CoreHelper::getCoreSitePath());
        }

        // make sure user is permitted to view stats
        if ($file->canViewStats() == false) {
            $errorMsg = TranslateHelper::t("stats_error_file_statistics_are_private", "Statistics for this file are not publicly viewable.");
            return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
        }

        // prepare template variables
        $statsTitle = '';
        $statsTitle .= $file->originalFilename . ' ';
        $statsTitle .= TranslateHelper::t("stats_title", "statistics");

        $statsLeft = '';
        $statsLeft .= ucfirst(TranslateHelper::t("uploaded", "Uploaded")) . ' ';
        $statsLeft .= CoreHelper::formatDate($file->uploadedDate);
        $statsLeft .= ' - ' . TranslateHelper::t("downloads", "Downloads") . ' ';
        $statsLeft .= $file->visits;

        // prepare chart data
        $last24hours = ChartsHelper::createBarChart($file, 'last24hours');
        $last7days = ChartsHelper::createBarChart($file, 'last7days');
        $last30days = ChartsHelper::createBarChart($file, 'last30days');
        $last12months = ChartsHelper::createBarChart($file, 'last12months');
        $countries = ChartsHelper::createPieChart($file, 'countries');
        $referrers = ChartsHelper::createPieChart($file, 'referrers');
        $browsers = ChartsHelper::createPieChart($file, 'browsers');
        $os = ChartsHelper::createPieChart($file, 'os');

        // load template
        return $this->render('account/file_stats.html', [
                    'statsTitle' => $statsTitle,
                    'statsLeft' => $statsLeft,
                    'last24hours' => $last24hours,
                    'last7days' => $last7days,
                    'last30days' => $last30days,
                    'last12months' => $last12months,
                    'countries' => $countries,
                    'referrers' => $referrers,
                    'browsers' => $browsers,
                    'os' => $os,
        ]);
    }

    /**
     * Saved for future purposes
     * 
     * @return type
     */
    public function fileInfo($shortUrl) {
        // get the current logged in user
        $Auth = AuthHelper::getAuth();

        // pickup request for later
        $db = Database::getDatabase();

        // load template
        return $this->render('file_info.html');
    }

    /**
     * Download all files as zip - generates the zip file.
     * 
     * Note: This function doesn't use the normal $response / twig template method
     * that other functions use. At some stage this will be rewritten to use Twig.
     * 
     * @param integer $folderId
     */
    public function ajaxDownloadAllAsZip() {
        // get params for later
        $Auth = $this->getAuth();
        $request = $this->getRequest();

        // optionally limit by folder id
        $limitFolderId = null;
        if ($request->query->has('folderId') && (int) $request->query->get('folderId') > 0) {
            $limitFolderId = $request->query->get('folderId');
        }

        // allow some time to run
        set_time_limit(60 * 60 * 4);

        // set max allowed total filesize, 1GB
        define('MAX_PERMITTED_ZIP_FILE_BYTES', 1024 * 1024 * 1024 * 1);

        // allow 1.2GB of memory to run
        ini_set('memory_limit', '1200M');

        // output styles - @TODO - replace with Twig
        echo "<style>
        body {
            font-family: helvetica neue,Helvetica,noto sans,sans-serif,Arial,sans-serif;
            font-size: 12px;
            line-height: 1.42857143;
            color: #949494;
            background-color: #fff;
        }
        a {
            text-decoration: none;
        }
        .btn {
            display: inline-block;
            margin-bottom: 0;
            font-weight: 400;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            background-image: none;
            border: 1px solid transparent;
            white-space: nowrap;
            padding: 6px 12px;
            font-size: 12px;
            line-height: 1.42857143;
            border-radius: 3px;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            -o-user-select: none;
            user-select: none;
        }
        .btn-info {
            color: #fff;
            background-color: #21a9e1;
            border-color: #21a9e1;
        }
        </style>";

        // get all root files/folders based on the sharing id
        $folderShareId = null;
        if (isset($_SESSION['sharekeyFileFolderShareId']) && ((int) $_SESSION['sharekeyFileFolderShareId'] > 0)) {
            $folderShareId = (int) $_SESSION['sharekeyFileFolderShareId'];
        }

        // exit if error
        if ($folderShareId === null) {
            echo TranslateHelper::t('account_home_ziparchive_failed_loading_shared_files', 'Error: Failed loading sharing files to create zip.');
            exit;
        }

        // check for zip class
        if (!class_exists('ZipArchive')) {
            echo TranslateHelper::t('account_home_ziparchive_class_not_exists', 'Error: The ZipArchive class was not found within PHP. Please enable it within php.ini and try again.');
            exit;
        }

        // setup database
        $db = Database::getDatabase();

        // load root folders
        $foldersClauseReplacements = [];
        $filesClauseReplacements = [];
        $foldersClause = 'SELECT id FROM file_folder '
                . 'WHERE file_folder.status = "active" '
                . 'AND file_folder.id IN ('
                . 'SELECT folder_id '
                . 'FROM file_folder_share '
                . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                . 'LEFT JOIN file_folder ON file_folder_share_item.folder_id = file_folder.id '
                . 'WHERE file_folder_share.id = :file_folder_share_id '
                . 'AND folder_id IS NOT NULL '
                . 'AND shared_with_user_id IS NULL '
                . 'AND ';
        if ($limitFolderId !== null) {
            $foldersClause .= 'file_folder.id = :folder_id';
            $foldersClauseReplacements['folder_id'] = (int) $limitFolderId;
        }
        else {
            $foldersClause .= '(file_folder.parentId IS NULL OR file_folder.parentId NOT IN ('
                    . 'SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE file_folder_share.id = :file_folder_share_id AND folder_id IS NOT NULL '
                    . '))';
        }
        $foldersClause .= ')';
        $foldersClauseReplacements['file_folder_share_id'] = (int) $_SESSION['sharekeyFileFolderShareId'];
        $foldersRows = $db->getRows($foldersClause, $foldersClauseReplacements);

        $filesRows = [];
        if ($limitFolderId === null) {
            // files SQL
            $filesClause = 'SELECT file.* FROM file '
                    . 'WHERE file.status = "active" '
                    . 'AND file.id IN ('
                    . 'SELECT file_id '
                    . 'FROM file_folder_share '
                    . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                    . 'WHERE file_folder_share.id = :file_folder_share_id '
                    . 'AND shared_with_user_id IS NULL '
                    . 'AND file_id IS NOT NULL '
                    . ')';
            $filesClauseReplacements['file_folder_share_id'] = (int) $_SESSION['sharekeyFileFolderShareId'];
            $filesRows = $db->getRows($filesClause, $filesClauseReplacements);
        }

        // compile list of file objects for zip later
        $files = [];
        if (count($filesRows)) {
            foreach ($filesRows AS $filesRow) {
                $files[] = File::hydrateSingleRecord($filesRow);
            }
        }

        // build folder and file tree
        $fileDataRows = [];
        foreach ($foldersRows AS $folderRow) {

            $fileDataRows[] = ZipFile::getFolderStructureAsArray($folderRow['id'], $folderRow['id']);
        }
        $zipFilename = CoreHelper::generateRandomHash();

        // setup output buffering
        ZipFile::outputInitialBuffer();

        // create blank zip file
        $zip = new ZipFile($zipFilename);

        // remove any old zip files
        ZipFile::purgeOldZipFiles();

        // output progress
        ZipFile::outputBufferToScreen('Creating zip archive...');

        // loop all files and download locally
        foreach ($fileDataRows AS $fileData) {
            foreach ($fileData AS $baseFolder => $fileDataItem) {
                // add files in folders
                $zip->addFilesTopZip($fileDataItem, ($limitFolderId === null) ? $baseFolder . '/' : '');

                // do folders
                if (count($fileDataItem['folders'])) {
                    $zip->addFileAndFolders($fileDataItem['folders'], ($limitFolderId === null) ? $baseFolder . '/' : '');
                }
            }
        }

        // add files in the root level
        $zip->addFilesTopZip([
            'files' => $files,
        ]);

        // output progress
        ZipFile::outputBufferToScreen('Saving zip file...', null, ' ');

        // close zip
        $zip->close();

        // get path for later
        $fullZipPathAndFilename = $zip->fullZipPathAndFilename;

        // output progress
        ZipFile::outputBufferToScreen('Done!', 'green');
        echo '<br/>';

        // output link to zip file
        $downloadZipName = "Shared Files";
        $downloadZipName = str_replace(' ', '_', $downloadZipName);
        $downloadZipName = ValidationHelper::removeInvalidCharacters($downloadZipName, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-0');

        echo '<a class="btn btn-info" href="' . WEB_ROOT . '/ajax/non_account_download_all_as_zip_get_file/' . str_replace('.zip', '', $zipFilename) . '/' . urlencode($downloadZipName) . '" target="_parent">' . TranslateHelper::t('account_home_download_zip_file', 'Download Zip File') . '&nbsp;&nbsp;(' . CoreHelper::formatSize(filesize($fullZipPathAndFilename)) . ')</a>';
        ZipFile::scrollIframe();

        echo '<br/><br/>';
        ZipFile::scrollIframe();
        exit;
    }

    public function ajaxDownloadAllAsZipGetFile($fileName, $downloadZipName) {
        // allow some time to run
        set_time_limit(60 * 60 * 4);

        if (strlen($fileName) == 0) {
            return $this->render404();
        }

        // make safe
        $fileName = str_replace(['.', '/', '\\', ','], '', $fileName);
        $fileName = validationHelper::removeInvalidCharacters($fileName, 'abcdefghijklmnopqrstuvwxyz12345678900');
        $downloadZipName = str_replace(['.', '/', '\\', ','], '', $downloadZipName);
        $downloadZipName = validationHelper::removeInvalidCharacters($downloadZipName, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-0');

        // check for existance
        $zipFilePath = CACHE_DIRECTORY_ROOT . '/zip/' . $fileName . '.zip';
        if (!file_exists($zipFilePath)) {
            $errorMsg = TranslateHelper::t("error_zip_file_no_longer_available", "ERROR: Zip file no longer available, please regenerate to download again.");

            return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
        }

        return $this->renderDownloadFileFromPath($zipFilePath, $downloadZipName . '.zip');
    }

}
