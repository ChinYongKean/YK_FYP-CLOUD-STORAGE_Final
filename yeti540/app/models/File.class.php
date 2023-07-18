<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Helpers\AuthHelper;
use App\Helpers\CacheHelper;
use App\Helpers\CoreHelper;
use App\Helpers\DownloadTrackerHelper;
use App\Helpers\FileHelper;
use App\Helpers\FileActionHelper;
use App\Helpers\FileServerHelper;
use App\Helpers\FileServerContainerHelper;
use App\Helpers\LogHelper;
use App\Helpers\PluginHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\SessionHelper;
use App\Helpers\StatsHelper;
use App\Helpers\UserHelper;
use App\Helpers\ValidationHelper;

class File extends Model
{
    const DOWNLOAD_TOKEN_VAR = 'download_token';
    const IMAGE_EXTENSIONS = 'gif|jpeg|jpg|png';
    const DOCUMENT_EXTENSIONS = 'doc|docx|xls|xlsx|ppt|pptx|pdf';
    const VIDEO_EXTENSIONS = 'mp4|flv|ogg';
    const AUDIO_EXTENSIONS = 'mp3';

    protected $errorMsg = null;

    public function getKeywordArray() {
        $rs = [];
        $keywords = str_replace(' ', ',', $this->keywords);
        if (strlen($keywords)) {
            $keywordsArr = explode(',', $keywords);
            foreach ($keywordsArr AS $keywordsArrItem) {
                if (strlen($keywordsArrItem)) {
                    $rs[] = $keywordsArrItem;
                }
            }
        }

        return $rs;
    }

    public function getErrorMsg() {
        return $this->errorMsg;
    }

    public function getFileKeywords() {
        return implode(',', $this->getKeywordArray());
    }

    public function getFileDescription() {
        return $this->description;
    }

    public function download($forceDownload = true, $doPluginIncludes = true, $downloadToken = null, $fileTransfer = true) {
        // remove session
        if (isset($_SESSION['showDownload'])) {
            $clearSession = true;

            // fixes android snag which requests files twice
            if (StatsHelper::currentDeviceIsAndroid()) {
                if (!isset($_SESSION['showDownloadFirstRun'])) {
                    $_SESSION['showDownloadFirstRun'] = true;
                    $clearSession = false;
                }
                else {
                    $_SESSION['showDownloadFirstRun'] = null;
                    unset($_SESSION['showDownloadFirstRun']);
                }
            }

            if ($clearSession == true) {
                // reset session variable for next time
                $_SESSION['showDownload'] = null;
                unset($_SESSION['showDownload']);
                SessionHelper::releaseSession();
            }
        }

        // setup mode
        $mode = 'SESSION';
        if ($downloadToken !== null) {
            $mode = 'TOKEN';
        }

        // for session downloads
        $userPackageId = 0;
        $fileOwnerUserId = 0;
        $speed = null;
        $maxDownloadThreads = null;
        $internalDownload = false;
        if ($mode == 'SESSION') {
            // get user
            $Auth = AuthHelper::getAuth();

            // setup user level
            $userPackageId = $Auth->package_id;

            // file owner id
            $fileOwnerUserId = $Auth->id;
        }

        // for token downloads
        else {
            // get database
            $db = Database::getDatabase(true);

            // check download token
            $replacements = [
                'file_id' => $this->id,
                'token' => $downloadToken,
            ];
            $sQL = 'SELECT id, user_id, ip_address, file_id, '
                    . 'download_speed, max_threads, file_transfer, '
                    . 'limit_by_ip, internal_download '
                    . 'FROM download_token '
                    . 'WHERE file_id = :file_id '
                    . 'AND token = :token '
                    . 'LIMIT 1';
            $tokenData = $db->getRow($sQL, $replacements);
            if (!$tokenData) {
                return false;
            }

            // check for matching IP
            if ((int) $tokenData['limit_by_ip'] === 1) {
                // restrict by IP
                if ($tokenData['ip_address'] !== CoreHelper::getUsersIPAddress()) {
                    return false;
                }
            }

            // get user level
            if ((int) $tokenData['user_id'] > 0) {
                $fileOwnerUserId = (int) $tokenData['user_id'];
                $userPackageId = (int) $db->getValue('SELECT level_id '
                                . 'FROM users '
                                . 'WHERE id = :id '
                                . 'LIMIT 1', [
                            'id' => $fileOwnerUserId,
                ]);
            }

            $speed = (int) $tokenData['download_speed'];
            $maxDownloadThreads = (int) $tokenData['max_threads'];
            $fileTransfer = (bool) $tokenData['file_transfer'];
            $internalDownload = (bool) $tokenData['internal_download'];
        }

        // clear any expired download trackers
        DownloadTrackerHelper::clearTimedOutDownloads();
        DownloadTrackerHelper::purgeDownloadData();

        // check for concurrent downloads for paid users
        if ($maxDownloadThreads === null) {
            $maxDownloadThreads = UserHelper::getMaxDownloadThreads($userPackageId);
        }
        if ((int) $maxDownloadThreads > 0) {
            // get database
            $db = Database::getDatabase(true);

            // allow for looping a number of times to allow older data to clear
            $loopCount = 0;
            $totalThreads = 0;
            while ($loopCount <= 3) {
                // get all active download data
                $sQL = "SELECT COUNT(download_tracker.id) AS total_threads ";
                $sQL .= "FROM download_tracker ";
                $sQL .= "WHERE download_tracker.status='downloading' AND download_tracker.ip_address = " . $db->quote(CoreHelper::getUsersIPAddress()) . " ";
                $sQL .= "AND date_updated >= DATE_SUB(NOW(), INTERVAL 20 SECOND)";
                $sQL .= "GROUP BY download_tracker.ip_address ";
                $totalThreads = (int) $db->getValue($sQL);
                if ($totalThreads < (int) $maxDownloadThreads) {
                    $loopCount = 4;
                }
                else {
                    $loopCount++;
                    usleep(5000000);
                }
            }

            // exit if too many threads
            if ($totalThreads >= (int) $maxDownloadThreads) {
                // fail
                $db->close();
                header("HTTP/1.0 429 Too Many Requests");
                echo 'Error: Too many concurrent download requests.';
                exit;
            }
        }

        // php script timeout for long downloads (2 days!)
        if (false === strpos(ini_get('disable_functions'), 'set_time_limit')) {
            // suppress the warnings
            @set_time_limit(60 * 60 * 24 * 2);
        }

        // load the server the file is on
        $uploadServerDetails = $this->loadFileServer();
        $storageType = $uploadServerDetails['serverType'];

        // get the full file path
        $fullPath = $this->getFullFilePath();

        // open file - via ftp
        if ($storageType == 'ftp') {
            // connect via ftp
            $conn_id = ftp_connect($uploadServerDetails['ipAddress'], $uploadServerDetails['ftpPort'], 30);
            if ($conn_id === false) {
                $this->errorMsg = 'Could not connect to ' . $uploadServerDetails['ipAddress'] . ' to upload file.';
                return false;
            }

            // authenticate
            $login_result = ftp_login($conn_id, $uploadServerDetails['ftpUsername'], $uploadServerDetails['ftpPassword']);
            if ($login_result === false) {
                $this->errorMsg = 'Could not login to ' . $uploadServerDetails['ipAddress'] . ' with supplied credentials.';
                return false;
            }

            // turn passive mode on
            if ((isset($uploadServerDetails['serverConfig']['ftp_passive_mode'])) && ($uploadServerDetails['serverConfig']['ftp_passive_mode'] == 'yes')) {
                // enable passive mode
                ftp_pasv($conn_id, true);
            }

            // setup ftp protocol
            $protocolFamily = STREAM_PF_UNIX; // linux / unix
            if ((isset($uploadServerDetails['serverConfig']['ftp_server_type'])) && ($uploadServerDetails['serverConfig']['ftp_server_type'] == 'windows' || $uploadServerDetails['serverConfig']['ftp_server_type'] == 'windows_alt')) {
                $protocolFamily = STREAM_PF_INET; // windows
            }

            // prepare the stream of data, unix
            $pipes = stream_socket_pair($protocolFamily, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($pipes === false) {
                $this->errorMsg = 'Could not create stream to download file on ' . $uploadServerDetails['ipAddress'];
                return false;
            }

            stream_set_write_buffer($pipes[0], 10000);
            stream_set_timeout($pipes[1], 10);
            stream_set_blocking($pipes[1], 0);

            $fail = false;
        }
        // check for file via Flysystem
        if (substr($storageType, 0, 10) == 'flysystem_') {
            $filesystem = FileServerContainerHelper::init($uploadServerDetails['id']);
            if (!$filesystem) {
                $this->errorMsg = TranslateHelper::t('classuploader_could_not_setup_adapter_to_download', 'Could not setup adapter to download file.');
                return false;
            }
            else {
                // check the file exists
                try {
                    // ceck for the file
                    $rs = $filesystem->has($fullPath);
                    if (!$rs) {
                        $this->errorMsg = 'Could not locate the file. Please contact support or try again.';
                        return false;
                    }
                }
                catch (Exception $e) {
                    $this->errorMsg = $e->getMessage();
                    return false;
                }
            }

            $fail = false;
        }

        // get download speed
        if ($speed === null) {
            $speed = (int) UserHelper::getMaxDownloadSpeed($userPackageId);
            if ($forceDownload) {
                // include any plugin includes
                $params = PluginHelper::includeAppends('class_file_download.php', ['speed' => $speed]);
                $speed = $params['speed'];
            }
        }

        // handle where to start in the download, support for resumed downloads
        $seekStart = 0;
        $seekEnd = $this->getPrimaryFileSize() - 1;
        if (isset($_SERVER['HTTP_RANGE'])) {
            $seekRange = substr($_SERVER['HTTP_RANGE'], strlen('bytes='));

            $range = explode('-', $seekRange);
            if ((int) $range[0] > 0) {
                $seekStart = intval($range[0]);
            }

            if ((int) $range[1] > 0) {
                $seekEnd = intval($range[1]);
            }
        }

        // should we use xSendFile
        $useXsendFile = false;
        $useXLitespeedLocation = false;
        if (($speed == 0) && $forceDownload) {
            // check whether xSendFile is enabled
            if (FileServerHelper::apacheXSendFileEnabled($this->getPrimaryServerId())) {
                $useXsendFile = true;
            }
            elseif (FileServerHelper::litespeedXLitespeedLocationEnabled($this->getPrimaryServerId())) {
                $useXLitespeedLocation = true;
            }
        }

        if ($forceDownload) {
            // output some headers
            header("Expires: 0");

            // skip for xsendfile or xAccelRedirect
            if (!$useXsendFile && !$useXLitespeedLocation && FileServerHelper::nginxXAccelRedirectEnabled($this->getPrimaryServerId()) === false) {
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Content-type: " . $this->getPrimaryArtifact()->file_type);
                header("Pragma: public");
                if ($fileTransfer) {
                    header("Content-Disposition: attachment; filename=\"" . str_replace("\"", "", $this->originalFilename) . "\"");
                    header("Content-Description: File Transfer");
                }
            }

            header('Accept-Ranges: bytes');

            // allow plugins to request files cross domain
            header('Access-Control-Allow-Origin: ' . _CONFIG_SITE_PROTOCOL . '://' . _CONFIG_CORE_SITE_HOST_URL);
            header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description');
            header('Access-Control-Allow-Credentials: true');

            // skip for xsendfile or xAccelRedirect
            if (!$useXsendFile && !$useXLitespeedLocation && FileServerHelper::nginxXAccelRedirectEnabled($this->getPrimaryServerId()) === false) {
                // allow for requests from IOS devices for the first byte
                if (($seekStart > 0) || ($seekEnd == 1)) {
                    header('HTTP/1.1 206 Partial Content');
                    header("Status: 206 Partial Content");
                    header("Content-Range: bytes " . $seekStart . '-' . $seekEnd . "/" . $this->getPrimaryFileSize());

                    // exit if IOS OPTIONS request
                    if ($seekEnd === 1) {
                        header("Content-Length: 1");
                        echo '1';
                        exit;
                    }
                    else {
                        header("Content-Length: " . ($seekEnd - $seekStart + 1));
                    }
                }
                else {
                    header("Content-Length: " . $this->getPrimaryFileSize());
                    // the below doesn't seem to be needed, retained just encase
                    //header("Content-Range: bytes " . $seekStart . "-" . $seekEnd . "/" . $this->getPrimaryFileSize());
                }
            }
        }

        if (SITE_CONFIG_DOWNLOADS_TRACK_CURRENT_DOWNLOADS == 'yes') {
            // track downloads
            $downloadTracker = new DownloadTrackerHelper($this);
            $downloadTracker->create($seekStart, $seekEnd);
        }

        // for returning the file contents
        $fileContent = '';

        if (function_exists('apache_setenv')) {
            // disable gzip HTTP compression so it would not alter the transfer rate
            apache_setenv('no-gzip', '1');
        }

        // clear old tokens
        FileHelper::purgeDownloadTokens();

        // reduce the bandwidth amount on the account, for non-owned files
        if ($fileOwnerUserId != $this->userId) {
            $db = Database::getDatabase();
            $remainingBWDownload = (int) $db->getValue('SELECT remainingBWDownload '
                            . 'FROM users '
                            . 'WHERE id = :id '
                            . 'LIMIT 1', [
                        'id' => $fileOwnerUserId,
            ]);
            if ($remainingBWDownload != 0) {
                $totalDownloadSize = $this->getPrimaryFileSize();
                if (($seekStart > 0) || ($seekEnd == 1)) {
                    $totalDownloadSize = ($seekEnd - $seekStart + 1);
                }

                // security
                if ($totalDownloadSize < 0) {
                    $totalDownloadSize = 0;
                }

                $remainingBWDownload = $remainingBWDownload - $totalDownloadSize;
                if ($remainingBWDownload <= 0) {
                    $remainingBWDownload = null;
                }

                $db->query('UPDATE users '
                        . 'SET remainingBWDownload = ' . $db->escape($remainingBWDownload) . ' '
                        . 'WHERE id = ' . (int) $fileOwnerUserId . ' '
                        . 'LIMIT 1');

                // ensure the account is downgraded if they are non admin and reach 0
                if ((UserHelper::getLevelIdFromPackageId($userPackageId) != 20) && ($remainingBWDownload == null)) {
                    $freeAccountTypeId = UserHelper::getDefaultFreeAccountTypeId();
                    $db->query('UPDATE users '
                            . 'SET level_id = ' . (int) $freeAccountTypeId . ', '
                            . 'paidExpiryDate=NOW(), '
                            . 'remainingBWDownload=null '
                            . 'WHERE id = ' . (int) $fileOwnerUserId . ' '
                            . 'LIMIT 1');
                }
            }
        }

        // release from session to avoid locking other tabs on download
        SessionHelper::releaseSession();

        // include any plugins for other storage methods
        if (is_object($params = PluginHelper::callHook('fileDownloadGetFileContent', [
                            'actioned' => false,
                            'seekStart' => $seekStart,
                            'seekEnd' => $seekEnd,
                            'storageType' => $storageType,
                            'fileContent' => $fileContent,
                            'downloadTracker' => $downloadTracker,
                            'forceDownload' => $forceDownload,
                            'file' => $this,
                            'doPluginIncludes' => $doPluginIncludes,
                            'speed' => $speed,
                            'fileOwnerUserId' => $fileOwnerUserId,
                            'userPackageId' => $userPackageId,
                            'downloadToken' => $downloadToken,
                            'fileTransfer' => $fileTransfer,
        ]))) {
            // received a Response object
            $params->send();
            exit;
        }

        if ($params['actioned'] == true) {
            $fileContent = $params['fileContent'];
        }
        else {
            // output file - via ftp
            $timeTracker = time();
            $length = 0;
            if ($storageType == 'ftp') {
                // no need for database
                $db = Database::getDatabase();
                $db->close();

                if ((isset($uploadServerDetails['serverConfig']['ftp_server_type'])) && ($uploadServerDetails['serverConfig']['ftp_server_type'] == 'windows_alt')) {
                    // for some windows servers, not recommend as limited streaming support
                    $local_file = "php://output";
                    ob_start();
                    ftp_get($conn_id, $local_file, $fullPath, FTP_BINARY);
                    $fileContent = ob_get_contents();
                    ob_end_clean();

                    if ($forceDownload == true) {
                        echo $fileContent;
                    }
                }
                else {
                    // stream via ftp
                    $ret = ftp_nb_fget($conn_id, $pipes[0], $fullPath, FTP_BINARY, $seekStart);
                    while ($ret == FTP_MOREDATA) {
                        // use fread as better supported
                        $contents = fread($pipes[1], $seekEnd + 1);
                        //$contents = stream_get_contents($pipes[1], $seekEnd + 1);
                        if ($contents !== false) {
                            if ($forceDownload == true) {
                                echo $contents;
                                CoreHelper::flushOutput();
                                if ($speed > 0) {
                                    $usleep = strlen($contents) / $speed;
                                    if ($usleep > 0) {
                                        usleep($usleep * 1000000);
                                    }
                                }
                            }
                            else {
                                $fileContent .= $contents;
                            }
                            $length = $length + strlen($contents);
                        }

                        $ret = ftp_nb_continue($conn_id);

                        // update download status every DOWNLOAD_TRACKER_UPDATE_FREQUENCY seconds
                        if (($timeTracker + DOWNLOAD_TRACKER_UPDATE_FREQUENCY) < time()) {
                            $timeTracker = time();
                            if (SITE_CONFIG_DOWNLOADS_TRACK_CURRENT_DOWNLOADS == 'yes') {
                                $downloadTracker->update();
                            }
                        }
                    }
                }

                fclose($pipes[0]);
                fclose($pipes[1]);
            }
            // handle download using flysystem
            elseif (substr($storageType, 0, 10) == 'flysystem_') {
                // no need for database
                $db = Database::getDatabase();
                $db->close();

                $filesystem = FileServerContainerHelper::init($uploadServerDetails['id']);
                if (!$filesystem) {
                    $this->errorMsg = TranslateHelper::t('classuploader_could_not_setup_adapter_to_download_file', 'Could not setup adapter to download file.');

                    return false;
                }

                if (!$fileUpload->error) {
                    // download the file
                    try {
                        // create stream to handle larger files
                        $handle = $filesystem->readStream($fullPath);

                        // move to starting position
                        fseek($handle, $seekStart);
                        while (($buffer = fgets($handle, 4096)) !== false) {
                            if ($forceDownload == true) {
                                echo $buffer;
                                CoreHelper::flushOutput();
                                if ($speed > 0) {
                                    $usleep = strlen($buffer) / $speed;
                                    if ($usleep > 0) {
                                        usleep($usleep * 1000000);
                                    }
                                }
                            }
                            else {
                                $fileContent .= $buffer;
                            }

                            $length = $length + strlen($buffer);

                            // update download status every DOWNLOAD_TRACKER_UPDATE_FREQUENCY seconds
                            if (($timeTracker + DOWNLOAD_TRACKER_UPDATE_FREQUENCY) < time()) {
                                $timeTracker = time();
                                if (SITE_CONFIG_DOWNLOADS_TRACK_CURRENT_DOWNLOADS == 'yes') {
                                    $downloadTracker->update();
                                }
                            }
                        }
                        fclose($handle);
                    }
                    catch (Exception $e) {
                        $this->errorMsg = $e->getMessage();

                        return false;
                    }
                }
            }
            // output file - local
            else {
                // no need for database
                $db = Database::getDatabase();
                $db->close();

                // attempt to send via X-Accel-Redirect to reduce load on the webserver
                // note that this hands off away from PHP so the download tracking
                // no longer works.
                if ($forceDownload) {
                    // check whether XAccelRedirect is enabled
                    if (FileServerHelper::nginxXAccelRedirectEnabled($this->getPrimaryServerId())) {
                        // update stats
                        if($internalDownload === false) {
                            $this->postDownloadStats($fileOwnerUserId, $userPackageId);
                        }

                        // reconnect database
                        $db = Database::getDatabase(true);

                        // finish off any plugins
                        $this->postDownloadComplete($forceDownload, $fileOwnerUserId, $userPackageId, $doPluginIncludes, $downloadToken, $downloadTracker);

                        // log
                        LogHelper::setContext('nginx_x_accel_redirect');
                        LogHelper::info('Using Nginx XAccelRedirect to send the file to the user.');

                        // use XAccelRedirect
                        header("Content-type: " . $this->getPrimaryArtifact()->file_type);
                        if ($fileTransfer === true) {
                            header("Content-Disposition: attachment; filename=\"" . str_replace("\"", "", $this->originalFilename) . "\"");
                        }

                        if ($speed > 0) {
                            LogHelper::info('X-Accel-Limit-Rate: ' . $speed);
                            header("X-Accel-Limit-Rate: " . $speed);
                        }

                        // accel redirect only works with the relative storage root, remove the base path
                        $xAccelPath = $fullPath;
                        if (($uploadServerDetails['scriptRootPath'] != '/') && (strlen($uploadServerDetails['scriptRootPath']))) {
                            $xAccelPath = substr($xAccelPath, strlen($uploadServerDetails['scriptRootPath']));
                        }

                        // logs
                        LogHelper::info("Sending file: " . $xAccelPath);

                        // send the file
                        header('X-Accel-Redirect: ' . $xAccelPath);
                        exit;
                    }
                }

                // attempt to send via XSendFile or X-LiteSpeed-Location to reduce load on the webserver
                // note that this hands off away from PHP so the download tracking
                // no longer works. It will also only work when there's no speed
                // restrictions for the download.
                if ($useXsendFile || $useXLitespeedLocation) {
                    // update stats
                    if($internalDownload === false) {
                        $this->postDownloadStats($fileOwnerUserId, $userPackageId);
                    }

                    // reconnect database
                    $db = Database::getDatabase(true);

                    // finish off any plugins
                    $this->postDownloadComplete($forceDownload, $fileOwnerUserId, $userPackageId, $doPluginIncludes, $downloadToken, $downloadTracker);

                    // use XSendFile
                    if($useXsendFile) {
                        // log
                        LogHelper::info('Using Apache X-Sendfile to send the file to the user.');

                        // send the file
                        header("Using-X-Sendfile: true");
                        header('X-Sendfile: ' . $fullPath);
                    }
                    elseif($useXLitespeedLocation) {
                        // log
                        LogHelper::info('Using X-LiteSpeed-Location to send the file to the user.');

                        // send the file
                        header('X-LiteSpeed-Location: ' . $fullPath);
                    }

                    // other headers
                    header("Content-type: " . $this->getPrimaryArtifact()->file_type);
                    if ($fileTransfer === true) {
                        header("Content-Disposition: attachment; filename=\"" . str_replace("\"", "", $this->originalFilename) . "\"");
                    }

                    // set etag for caching
                    $etag = md5(microtime().CoreHelper::generateRandomHash());
                    header("Etag: \"" . $etag . "\"");
                    exit;
                }

                // open file - locally
                $handle = @fopen($fullPath, "r");
                if (!$handle) {
                    // log
                    LogHelper::error('Could not open local file for reading in File.class.php: ' . $fullPath);

                    $this->errorMsg = 'Could not open file for reading.';
                    return false;
                }

                // move to starting position
                fseek($handle, $seekStart);
                while (($buffer = fgets($handle, 4096)) !== false) {
                    if ($forceDownload == true) {
                        echo $buffer;
                        CoreHelper::flushOutput();
                        if ($speed > 0) {
                            $usleep = strlen($buffer) / $speed;
                            if ($usleep > 0) {
                                usleep($usleep * 1000000);
                            }
                        }
                    }
                    else {
                        $fileContent .= $buffer;
                    }

                    $length = $length + strlen($buffer);

                    // update download status every DOWNLOAD_TRACKER_UPDATE_FREQUENCY seconds
                    if (($timeTracker + DOWNLOAD_TRACKER_UPDATE_FREQUENCY) < time()) {
                        $timeTracker = time();
                        if (SITE_CONFIG_DOWNLOADS_TRACK_CURRENT_DOWNLOADS == 'yes') {
                            $downloadTracker->update();
                        }
                    }
                }
                fclose($handle);
            }
        }

        // reconnect database
        $db = Database::getDatabase(true);

        // update stats
        if ($forceDownload === true && $internalDownload === false) {
            // update stats
            $this->postDownloadStats($fileOwnerUserId, $userPackageId);
        }

        // finish off any plugins
        $this->postDownloadComplete($forceDownload, $fileOwnerUserId, $userPackageId, $doPluginIncludes, $downloadToken, $downloadTracker);

        // return file content
        if ($forceDownload === false) {
            return $fileContent;
        }

        exit();
    }

    public function postDownloadStats($fileOwnerUserId, $userPackageId) {
        // update stats
        if (((int) $this->userId > 0) && ($fileOwnerUserId == $this->userId) || (UserHelper::getLevelIdFromPackageId($userPackageId) == 20)) {
            // dont update stats, this was triggered by an admin user or file owner
        }
        else {
            // update stats
            $rs = StatsHelper::track($this);
            if ($rs) {
                $this->updateLastAccessed();
            }
        }
    }

    public function postDownloadComplete($forceDownload, $fileOwnerUserId, $userPackageId, $doPluginIncludes, $downloadToken, $downloadTracker) {
        // finish off any plugins
        PluginHelper::callHook('fileDownloadComplete', [
            'origin' => 'File.class.php',
            'forceDownload' => $forceDownload,
            'fileOwnerUserId' => $fileOwnerUserId,
            'userLevelId' => UserHelper::getLevelIdFromPackageId($userPackageId),
            'file' => $this,
            'doPluginIncludes' => $doPluginIncludes,
            'downloadToken' => $downloadToken,
        ]);

        if (SITE_CONFIG_DOWNLOADS_TRACK_CURRENT_DOWNLOADS == 'yes') {
            // close download
            $downloadTracker->finish();
        }
    }

    public function loadFileServer($serverId = null) {
        // handle defaults
        if($serverId === null) {
            $serverId = $this->getPrimaryServerId();
        }

        // load file servers
        $fileServers = FileHelper::getFileServerData();
        if (!isset($fileServers[(int) $serverId])) {
            // fallback to current server
            $serverId = FileHelper::getCurrentServerId();
        }
        $uploadServerDetails = $fileServers[(int) $serverId];

        // append the server config
        $serverConfigArr = '';
        if (strlen($uploadServerDetails['serverConfig'])) {
            $serverConfig = json_decode($uploadServerDetails['serverConfig'], true);
            if (is_array($serverConfig)) {
                $serverConfigArr = $serverConfig;
            }
        }
        $uploadServerDetails['serverConfig'] = $serverConfigArr;

        return $uploadServerDetails;
    }

    public function loadAllFileServers() {
        // load file servers
        $fileServers = FileHelper::getFileServerData();

        $serverIds = $this->getPrimaryFileAllServerIds();
        $servers = [];
        foreach($serverIds as $serverId) {
            $server = $this->loadFileServer($serverId);
            if($server) {
                $servers[] = $server;
            }
        }

        return $servers;
    }

    public function getFullFilePath() {
        // load the server details of the file server
        $fileServer = $this->loadFileServer();

        // make sure the path is set, this simply stores it within the database
        if (in_array($fileServer['serverType'], ['local', 'direct'])) {
            if (strlen($fileServer['scriptRootPath']) === 0) {
                // lookup and store the path
                FileServerHelper::getCurrentServerFileStoragePath();

                // clear server cache
                CacheHelper::clearCache('FILE_SERVER_DATA');

                // reload server details
                $fileServer = $this->loadFileServer();
            }
        }

        // prep the base path
        $basePath = '';
        if (in_array($fileServer['serverType'], ['local', 'direct'])) {
            $basePath .= $fileServer['scriptRootPath'] . '/';
        }

        // append the storage path
        if (strlen($fileServer['storagePath'])) {
            $basePath .= $fileServer['storagePath'] . '/';
        }

        // remove any trailing forward slash
        if (strlen($basePath) && (substr($basePath, strlen($basePath) - 1, 1) === '/')) {
            $basePath = substr($basePath, 0, strlen($basePath) - 1);
        }

        // return the server root path with the file
        return $basePath . $this->getPrimaryLocalFilePath();
    }

    public function getLocalFilePath() {
        // ensure we're using cache
        $cacheKey = __METHOD__.'_'.$this->id;
        if (CacheHelper::cacheExists($cacheKey)) {
            return CacheHelper::getCache($cacheKey);
        }

        CacheHelper::setCache($cacheKey, $this->getPrimaryLocalFilePath());

        return CacheHelper::getCache($cacheKey);
    }

    public function getPrimaryLocalFilePath() {
        // ensure we're using cache
        $cacheKey = __METHOD__.'_'.$this->id;
        if (CacheHelper::cacheExists($cacheKey)) {
            return CacheHelper::getCache($cacheKey);
        }

        CacheHelper::setCache($cacheKey, $this->getPrimaryArtifact()->local_file_path);

        return CacheHelper::getCache($cacheKey);
    }

    public function getPrimaryFileType() {
        // ensure we're using cache
        $cacheKey = __METHOD__.'_'.$this->id;
        if (CacheHelper::cacheExists($cacheKey)) {
            return CacheHelper::getCache($cacheKey);
        }

        CacheHelper::setCache($cacheKey, $this->getPrimaryArtifact()->file_type);

        return CacheHelper::getCache($cacheKey);
    }

    public function getPrimaryFileSize() {
        // ensure we're using cache
        $cacheKey = __METHOD__.'_'.$this->id;
        if (CacheHelper::cacheExists($cacheKey)) {
            return CacheHelper::getCache($cacheKey);
        }

        CacheHelper::setCache($cacheKey, $this->getPrimaryArtifact()->file_size);

        return CacheHelper::getCache($cacheKey);
    }

    /**
     * Gets the primary server id of the primary file artifact
     *
     * @return false|mixed
     */
    public function getPrimaryServerId() {
        // ensure we're using cache
        $cacheKey = __METHOD__.'_'.$this->id;
        if (CacheHelper::cacheExists($cacheKey)) {
            return CacheHelper::getCache($cacheKey);
        }

        // first get primary artifact
        $primaryArtifact = $this->getPrimaryArtifact();

        // lookup primary server
        $fileArtifactStorage = FileArtifactStorage::loadOneByClause('file_artifact_id = :file_artifact_id ' .
            'AND is_primary = 1', [
                'file_artifact_id' => $primaryArtifact->id,
            ]
        );
        if(!$fileArtifactStorage) {
            return false;
        }

        CacheHelper::setCache($cacheKey, $fileArtifactStorage->file_server_id);

        return CacheHelper::getCache($cacheKey);
    }

    /**
     * Gets all the server ids of the primary file artifact
     *
     * @return false|mixed
     */
    public function getPrimaryFileAllServerIds() {
        // ensure we're using cache
        $cacheKey = __METHOD__.'_'.$this->id;
        if (CacheHelper::cacheExists($cacheKey)) {
            return CacheHelper::getCache($cacheKey);
        }

        // first get primary artifact
        $primaryArtifact = $this->getPrimaryArtifact();

        // lookup primary server
        $fileArtifactStorages = FileArtifactStorage::loadByClause('file_artifact_id = :file_artifact_id', [
                'file_artifact_id' => $primaryArtifact->id,
            ]
        );
        $serverIds = [];
        foreach($fileArtifactStorages AS $fileArtifactStorage) {
            $serverIds[] = $fileArtifactStorage->file_server_id;
        }

        CacheHelper::setCache($cacheKey, $serverIds);

        return CacheHelper::getCache($cacheKey);
    }

    public function getPrimaryFileHash() {
        // ensure we're using cache
        $cacheKey = __METHOD__.'_'.$this->id;
        if (CacheHelper::cacheExists($cacheKey)) {
            return CacheHelper::getCache($cacheKey);
        }

        CacheHelper::setCache($cacheKey, $this->getPrimaryArtifact()->file_hash);

        return CacheHelper::getCache($cacheKey);
    }

    public function getPrimaryArtifact()
    {
        // ensure we're using cache
        $cacheKey = __METHOD__.'_'.$this->id;
        if (CacheHelper::cacheExists($cacheKey)) {
            return CacheHelper::getCache($cacheKey);
        }

        $rs = FileArtifact::loadOneByClause('file_id = :file_id ' .
            'AND file_artifact_type = "primary"', [
                'file_id' => $this->id,
            ]
        );
        if(!$rs) {
            return null;
        }

        CacheHelper::setCache($cacheKey, $rs);

        return CacheHelper::getCache($cacheKey);
    }

    /**
     * Get full short url path
     *
     * @return string
     */
    public function getFullShortUrl($finalDownloadBasePath = false) {
        if (SITE_CONFIG_FILE_URL_SHOW_FILENAME == 'yes') {
            return $this->getFullLongUrl($finalDownloadBasePath);
        }

        return $this->getShortUrlPath($finalDownloadBasePath);
    }

    public function getShortUrlPath($finalDownloadBasePath = false, $downloadUrlSlug = '') {
        return $this->getFileServerPath($finalDownloadBasePath) . (strlen($downloadUrlSlug) ? ('/token/download/' . $downloadUrlSlug) : '') . '/' . $this->shortUrl;
    }

    public function getFileServerPath($finalDownloadBasePath = true) {
        $fileServerPath = FileHelper::getFileDomainAndPath($this->id, $this->getPrimaryServerId(), $finalDownloadBasePath);

        // check our protocol override for the file server
        $fileServers = FileHelper::getFileServerData();
        $proto = _CONFIG_SITE_PROTOCOL;
        if ($fileServers[$this->getPrimaryServerId()]) {
            $serverConfig = json_decode($fileServers[$this->getPrimaryServerId()]['serverConfig'], true);
            if ((isset($serverConfig['file_server_download_proto'])) && (strlen($serverConfig['file_server_download_proto']))) {
                $proto = $serverConfig['file_server_download_proto'];
            }
        }

        return $proto . '://' . $fileServerPath;
    }

    public function getStatisticsUrl($returnAccount = false) {
        return $this->getShortUrlPath() . '~s' . ($returnAccount ? ('&returnAccount=1') : '');
    }

    public function getDeleteUrl($returnAccount = false, $finalDownloadBasePath = false) {
        return $this->getShortUrlPath($finalDownloadBasePath) . '~d?' . $this->deleteHash . ($returnAccount ? ('&returnAccount=1') : '');
    }

    public function getInfoUrl($returnAccount = false) {
        return $this->getShortUrlPath() . '~i?' . $this->deleteHash . ($returnAccount ? ('&returnAccount=1') : '');
    }

    public function getShortInfoUrl($returnAccount = false) {
        return $this->getShortUrlPath() . '~i' . ($returnAccount ? ('&returnAccount=1') : '');
    }

    public function getOwnerUsername() {
        // get database
        $db = Database::getDatabase();

        // if no user id return false, i.e. this was uploaded anon
        if ($this->userId == NULL) {
            return false;
        }

        // lookup username
        $user = User::loadOneById($this->userId);
        if (!$user) {
            return false;
        }

        return $user->username;
    }

    public function getUploaderUsername() {
        // if no user id return the username, i.e. this was uploaded anon
        if ($this->uploadedUserId == NULL) {
            return $this->getOwnerUsername();
        }

        // lookup username
        $user = User::loadOneById($this->uploadedUserId);
        if (!$user) {
            return $this->getOwnerUsername();
        }

        return $user->username;
    }

    /**
     * Get full long url including the original filename
     *
     * @return string
     */
    public function getFullLongUrl($finalDownloadBasePath = false, $downloadUrlSlug = '') {
        return $this->getShortUrlPath($finalDownloadBasePath, $downloadUrlSlug) . '/' . $this->getSafeFilenameForUrl();
    }

    public function getSafeFilenameForUrl() {
        return str_replace([" ", "\"", "'", ";", "#", "%", "&"], "_", strip_tags($this->originalFilename));
    }

    /**
     * Method to increment visitors
     */
    public function updateVisitors() {
        $db = Database::getDatabase(true);

        // sync file stats with the file.visits data. Note that this is called within StatsHelper::track(), which has it moved to AFTER the log into the stats table
        $db->query('UPDATE file SET visits=(SELECT COUNT(id) FROM stats WHERE file_id = :file_id) WHERE id = :id', ['file_id' => $this->id, 'id' => $this->id]);
    }

    /**
     * Method to update last accessed
     */
    public function updateLastAccessed() {
        $db = Database::getDatabase(true);
        $db->query('UPDATE file SET lastAccessed = NOW() WHERE id = :id', ['id' => $this->id]);
    }

    /**
     * Method to set folder
     */
    public function updateFolder($folderId = null) {
        if ((int) $folderId === 0) {
            $folderId = null;
        }
        $this->folderId = $folderId;
        $this->save();
    }

    /**
     * Remove by user
     */
    public function trashByUser() {
        return $this->_trashByStatusId(2);
    }

    /**
     * Remove by system
     */
    public function trashBySystem() {
        return $this->_trashByStatusId(5);
    }

    /**
     * Remove by admin
     */
    public function trashByAdmin() {
        return $this->_trashByStatusId(3);
    }

    /**
     * Trash by status
     */
    // @TODO - Add trash reason to update
    private function _trashByStatusId($newStatusId = 3) {
        // if the file isn't associated with a user account, just remove
        if ((int) $this->userId === 0) {
            return $this->removeByUser();
        }

        // update the file as inactive
        $this->status = 'trash';
        $this->date_updated = CoreHelper::sqlDateTime();
        $this->folderId = null;
        $affectedRows = $this->save();

        if ($affectedRows === 1) {
            return true;
        }

        return false;
    }

    /**
     * Remove by user
     */
    public function removeByUser() {
        LogHelper::info('Request to remove file by USER. ID: ' . $this->id . '. Name: ' . $this->originalFilename);

        return $this->_removeByStatusId(2);
    }

    /**
     * Remove by system
     */
    public function removeBySystem() {
        LogHelper::info('Request to remove file by SYSTEM. ID: ' . $this->id . '. Name: ' . $this->originalFilename);

        return $this->_removeByStatusId(5);
    }

    /**
     * Remove by admin
     */
    public function removeByAdmin() {
        LogHelper::info('Request to remove file by ADMIN. ID: ' . $this->id . '. Name: ' . $this->originalFilename);

        return $this->_removeByStatusId(3);
    }

    /**
     * Restore folder from trash
     */
    public function restoreFromTrash($restoreFolderId = null) {
        // update the file as active
        $this->status = 'active';
        $this->date_updated = CoreHelper::sqlDateTime();
        $this->folderId = $restoreFolderId;
        $affectedRows = $this->save();
        if ($affectedRows == 1) {
            return true;
        }

        return false;
    }

    /**
     * Remove by status
     */
    private function _removeByStatusId($statusReasonId = 3) {
        // get database
        $db = Database::getDatabase();

        // remove the actual file from storage
        $rs = $this->_queueFileForRemoval();
        if ($rs !== false) {
            // remove any other data which isn't required
            $db->query('DELETE '
                    . 'FROM download_tracker '
                    . 'WHERE file_id = :file_id', [
                'file_id' => $this->id,
            ]);

            // cancel any pending file reports
            $db->query('UPDATE file_report '
                    . 'SET report_status = "cancelled" '
                    . 'WHERE file_id = :file_id '
                    . 'AND report_status = "pending"', [
                'file_id' => $this->id,
            ]);

            // remove share entries
            $db->query('DELETE FROM file_folder_share_item '
                . 'WHERE file_id = :file_id', [
                'file_id' => $this->id,
            ]);

            // update the file
            $this->status = 'deleted';
            $this->status_reason_id = $statusReasonId;
            $this->date_updated = CoreHelper::sqlDateTime();
            $this->folderId = null;
            $this->folderId = null;
            $affectedRows = $this->save();
            if ($affectedRows == 1) {
                // update artifact
                $db->query('UPDATE file_artifact '
                    . 'SET file_hash = null '
                    . 'WHERE file_id = :file_id '
                    . 'AND file_artifact_type = "primary"', [
                    'file_id' => $this->id,
                ]);

                // log
                LogHelper::info('File set as deleted. ID: ' . $this->id . '. Name: ' . $this->originalFilename);

                return true;
            }
        }

        return false;
    }

    /**
     * Queue the file for removal, the actual removal is done via the
     * process_file_queue cron scheduled task.
     */
    private function _queueFileForRemoval() {
        // call plugin hooks, this queues cache files on the file previewer
        $params = PluginHelper::callHook('fileRemoveFile', [
            'file' => $this,
            'actioned' => false,
        ]);

        // exit if we're done processing the item
        if ($params['actioned'] === true) {
            return true;
        }

        // if the file is shared don't remove it
        if ($this->_fileIsShared()) {
            return true;
        }

        // ensure the artifact still exists
        $primaryServerIds = $this->getPrimaryFileAllServerIds();
        if(empty($primaryServerIds)) {
            return true;
        }

        // get database
        $db = Database::getDatabase();

        // first get primary artifact
        $primaryArtifact = $this->getPrimaryArtifact();

        // loop stored copied and schedule for removal
        foreach($primaryServerIds as $primaryServerId) {
            // queue the file for removal
            FileActionHelper::queueDeleteFile($primaryServerId, $this->getFullFilePath(), $this->id, null, true, $primaryArtifact->id);

            // remove artifact joins
            $db->query('DELETE FROM file_artifact_storage '
                . 'WHERE file_artifact_id = :file_artifact_id AND file_server_id = :file_server_id', [
                'file_artifact_id' => $primaryArtifact->id,
                'file_server_id' => $primaryServerId,
            ]);
        }

        return true;
    }

    public function _fileIsShared() {
        // get database
        $db = Database::getDatabase(true);

        // get file hash
        if (strlen($this->getPrimaryFileHash())) {
            // check for other active files which share the stored file
            $findFile = $db->getRow("SELECT * "
                    . "FROM file f "
                    . "LEFT JOIN file_artifact fa ON f.id = fa.file_id AND file_artifact_type = 'primary' "
                    . "WHERE fa.file_hash = :file_hash "
                    . "AND f.status != 'deleted' "
                    . "AND f.id != :id "
                    . "LIMIT 1", [
                        'file_hash' => $this->getPrimaryFileHash(),
                        'id' => $this->id,
                    ]
            );
            if ($findFile) {
                return true;
            }
        }

        return false;
    }

    public function getLargeIconPath() {
        $fileTypePath = SITE_IMAGE_DIRECTORY_ROOT . '/file_icons/512px/' . strtolower($this->extension) . '.png';
        if (!file_exists($fileTypePath)) {
            return false;
        }

        return SITE_IMAGE_PATH . '/file_icons/512px/' . strtolower($this->extension) . '.png';
    }

    public function getFilenameExcExtension() {
        $filename = $this->originalFilename;
        $extWithDot = '.' . $this->extension;
        if (substr($filename, (strlen($filename) - strlen($extWithDot)), strlen($extWithDot)) == $extWithDot) {
            $filename = substr($filename, 0, (strlen($filename) - strlen($extWithDot)));
        }

        return $filename;
    }

    /**
     * Method to set password
     */
    public function updatePassword($password = '') {
        $md5Password = '';
        if (strlen($password)) {
            $md5Password = md5($password);
        }

        $this->accessPassword = $md5Password;
        $this->save();
    }

    // not currently used, use album passwords instead
    public function hasPasswordDirectlySet() {
        return false;
    }

    // @TODO - also check folder
    public function isPasswordProtected() {
        return $this->hasPasswordDirectlySet();
    }

    public function getHtmlLinkCode() {
        $text = TranslateHelper::t('class_file_download', 'Download') . ' ' . ValidationHelper::safeOutputToScreen(ValidationHelper::safeOutputToScreen($this->originalFilename)) . ' ' . TranslateHelper::t('class_file_from', 'from') . ' ' . SITE_CONFIG_SITE_NAME;

        // if the file preview plugin is enabled, use thumbnail for images
        if (PluginHelper::pluginEnabled('filepreviewer') && $this->isImage()) {
            $imageIcon = FileHelper::getIconPreviewImageUrl($this, false, 160, false, 280, 280, 'middle');

            $text = htmlentities('<img src="' . $imageIcon . '"/>');
        }

        return '&lt;a href=&quot;' . $this->getFullShortUrl() . '&quot; target=&quot;_blank&quot; title=&quot;' . TranslateHelper::t('download_from', 'Download from') . ' ' . SITE_CONFIG_SITE_NAME . '&quot;&gt;' . $text . '&lt;/a&gt;';
    }

    public function getForumLinkCode() {
        // if the file preview plugin is enabled, output a different bbcode for images
        if (PluginHelper::pluginEnabled('filepreviewer') && $this->isImage()) {
            $imageIcon = FileHelper::getIconPreviewImageUrl($this, false, 160, false, 280, 280, 'middle');

            return '[url=' . ValidationHelper::safeOutputToScreen($this->getFullShortUrl()) . '][img]' . ValidationHelper::safeOutputToScreen($imageIcon) . '[/img][/url]';
        }

        return '[url]' . ValidationHelper::safeOutputToScreen($this->getFullShortUrl()) . '[/url]';
    }

    public function accountDuplicateFile($newFolderId = -1) {
        // create unique filename
        $foundExistingFile = 1;
        $tracker = 2;
        $newFilename = $this->originalFilename;
        $folderId = ((int) $this->folderId ? $this->folderId : null);
        if ($newFolderId !== -1) {
            $folderId = (int) $newFolderId ? $newFolderId : null;
        }
        while ($foundExistingFile >= 1) {
            $foundExistingFile = (int) File::count('originalFilename = :originalFilename '
                            . 'AND status = "active" '
                            . 'AND folderId ' . ((int) $folderId > 0 ? ('=' . $folderId) : 'IS NULL') . ' '
                            . 'AND (userId = :userId OR file.uploadedUserId = :userId)', [
                        'originalFilename' => $newFilename,
                        'userId' => $this->userId,
            ]);
            if ($foundExistingFile >= 1) {
                $newFilename = substr($this->originalFilename, 0, strlen($this->originalFilename) - strlen($this->extension) - 1) . ' (' . $tracker . ').' . $this->extension;
                $tracker++;
            }
        }

        // setup properties
        $copyProperties = [];
        $copyProperties['originalFilename'] = $newFilename;
        $copyProperties['folderId'] = $folderId;
        $copyProperties['userId'] = (int) $this->userId;
        $copyProperties['uploadedUserId'] = $this->uploadedUserId;

        // duplicate
        return $this->duplicateFile($copyProperties);
    }

    /**
     * Create a copy of the file in the database
     */
    public function duplicateFile($copyProperties = []) {
        // prepare the user data
        $userId = null;
        $Auth = AuthHelper::getAuth();
        if ($Auth->loggedIn()) {
            $userId = (int) $Auth->id;
        }

        // insert entry into the DB rather than move etc
        try {
            // store in db
            $file = FileHelper::createFileEntry([
                'original_filename' => $this->originalFilename,
                'extension' => $this->extension,
                'file_type' => $this->getPrimaryFileType(),
                'temp_file_path' => null,
                'file_size' => $this->getPrimaryFileSize(),
                'file_hash' => $this->getPrimaryFileHash(),
                'local_file_path' => $this->getPrimaryLocalFilePath(),
                'user_id' => $userId,
                'uploaded_user_id' => $userId,
                'file_server_id' => $this->getPrimaryServerId(),
                'folder_id' => null,
                'upload_source' => 'duplicate',
            ]);
        } catch (\Exception $e) {
            // log error
            LogHelper::error('Failed duplicating file. '.$e->getMessage());

            return false;
        }

        // overwrite with any properties passed into the method
        if (count($copyProperties)) {
            foreach ($copyProperties AS $k => $v) {
                $file->$k = $v;
            }
        }
        $file->save();

        PluginHelper::includeAppends('class_file_duplicate_file.inc.php', [
            'oldFile' => $this,
            'newFile' => $file,
            ]
        );

        return $file;
    }

    /**
     * checks if there is another file record in the database sharing the same real file
     */
    public function isDuplicate() {
        return $this->_fileIsShared();
    }

    /**
     * Remove file and any database data
     */
    public function deleteFileIncData() {
        // get database
        $db = Database::getDatabase(true);

        // queue the file for removal
        $this->removeByAdmin();

        // stats
        $this->deleteStats();

        // file
        $db->query('DELETE '
                . 'FROM file '
                . 'WHERE id = :id '
                . 'LIMIT 1', [
            'id' => (int) $this->id,
        ]);

        return true;
    }

    public function generateDirectDownloadToken(
        $downloadSpeedOverride = null,
        $maxThreadsOverride = null,
        $fileTransfer = true,
        $processPPD = true,
        $limitByIp = null,
        $internalDownload = false
    ) {
        // get database
        $db = Database::getDatabase(true);

        // get auth
        $Auth = AuthHelper::getAuth();

        // if $limitByIp = null, use system setting
        if ($limitByIp === null) {
            $limitByIp = SITE_CONFIG_LOCK_DOWNLOAD_TOKENS_TO_IP === 'Enabled';
        }

        // make sure one doesn't already exist for the file
        $checkToken = true;
        while ($checkToken != false) {
            // generate unique hash
            $downloadTokenStr = hash('sha256', $this->id . microtime() . rand(1000, 9999));
            $checkToken = DownloadToken::loadOneByClause('file_id = :file_id '
                            . 'AND token = :token', [
                        'file_id' => $this->id,
                        'token' => $downloadTokenStr,
            ]);
        }

        // insert token into database
        $userId = null;
        if ($Auth->loggedIn()) {
            $userId = $Auth->id;
        }

        if ($downloadSpeedOverride === null) {
            $downloadSpeedOverride = UserHelper::getMaxDownloadSpeed($Auth->package_id);
        }
        if ($maxThreadsOverride === null) {
            $maxThreadsOverride = UserHelper::getMaxDownloadThreads($Auth->package_id);
        }

        $downloadToken = new DownloadToken();
        $downloadToken->token = $downloadTokenStr;
        $downloadToken->user_id = $userId;
        $downloadToken->ip_address = CoreHelper::getUsersIPAddress();
        $downloadToken->file_id = $this->id;
        $downloadToken->created = date('Y-m-d H:i:s');
        $downloadToken->expiry = date('Y-m-d H:i:s', time() + (60 * 60 * 24));
        $downloadToken->download_speed = (int) $downloadSpeedOverride;
        $downloadToken->max_threads = (int) $maxThreadsOverride;
        $downloadToken->file_transfer = (int) $fileTransfer;
        $downloadToken->process_ppd = (int) $processPPD;
        $downloadToken->limit_by_ip = (int) $limitByIp;
        $downloadToken->internal_download = (int) $internalDownload;
        $downloadToken->save();

        return $downloadTokenStr;
    }

    /**
     * Generate a link for downloading files directly. Allows for download managers
     * and no reliance on sessions.
     */
    public function generateDirectDownloadUrl() {
        // get database
        $db = Database::getDatabase(true);

        // get download token
        $downloadToken = $this->generateDirectDownloadToken();
        if (!$downloadToken) {
            $errorMsg = 'Failed generating direct download link, please try again later.';
            return CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg);
        }

        // append any download slug
        $downloadUrlSlug = UserHelper::getDownloadUrlSlug();

        // compile full url, always include the filename
        return $this->getFullLongUrl(true, $downloadUrlSlug) . '?' . File::DOWNLOAD_TOKEN_VAR . '=' . $downloadToken;
    }

    /**
     * Generate a link for streaming media files. Allows for no limits on speed or concurrent downloads.
     */
    public function generateDirectDownloadUrlForMedia($limitByIp = null) {
        // get download token
        $downloadToken = $this->generateDirectDownloadTokenForMedia($limitByIp);
        if (!$downloadToken) {
            $errorMsg = 'Failed generating direct download link, please try again later.';

            return CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg);
        }

        // compile full url, always include the filename to avoid issues when embedding
        // do not use download slugs as we need full speed downloads for media
        return $this->getFullLongUrl(true) . '?' . File::DOWNLOAD_TOKEN_VAR . '=' . $downloadToken;
    }

    /**
     * Generate a download token with no limitations, used internally or for media
     */
    public function generateDirectDownloadTokenForMedia($limitByIp = null) {
        // get download token
        $downloadToken = $this->generateDirectDownloadToken(0, 10, false, false, $limitByIp);
        if (!$downloadToken) {
            return false;
        }

        // return download token
        return $downloadToken;
    }

    /**
     * Whether stats data is private and can only be viewed by the account owner
     * 
     * @return boolean
     */
    public function canViewStats() {
        // check for admin users, they should be allowed access to all
        $Auth = AuthHelper::getAuth();
        if ($Auth->level_id >= 10) {
            return true;
        }

        // if file doesn't belong to an account, assume public
        if ((int) $this->userId == 0) {
            return true;
        }

        // if logged in user matches owner
        if ($Auth->id == $this->userId) {
            return true;
        }

        // user not logged in or other account, load file owner and see if flagged as private
        $owner = User::loadOneById($this->userId);
        if (!$owner) {
            return true;
        }

        // check if stats are public or private on account, 0 = public
        if ($owner->privateFileStatistics == 0) {
            return true;
        }

        return false;
    }

    /**
     * Schedule server move for stored file.
     * 
     * @param type $newServerId
     */
    public function scheduleServerMove($newServerId) {
        // make sure the new server is different from the existing
        if ($this->getPrimaryServerId() == $newServerId) {
            return false;
        }

        // load the server the file is on
        $storageType = 'local';
        $uploadServerDetails = $this->loadFileServer();
        if ($uploadServerDetails != false) {
            // fallback (shouldn't really be used)
            $storageLocation = DOC_ROOT . '/' . $uploadServerDetails['storagePath'];

            // direct servers
            if (strlen($uploadServerDetails['serverConfig']['server_doc_root'])) {
                $storageLocation = $uploadServerDetails['serverConfig']['server_doc_root'] . '/' . $uploadServerDetails['storagePath'];

                // allow for absolute paths in storagePath
                if (strlen($uploadServerDetails['serverConfig']['server_doc_root']) > 1) {
                    if ($uploadServerDetails['serverConfig']['server_doc_root'] == substr($uploadServerDetails['storagePath'], 0, strlen($uploadServerDetails['serverConfig']['server_doc_root']))) {
                        $storageLocation = $uploadServerDetails['storagePath'];
                    }
                }
            }

            $storageType = $uploadServerDetails['serverType'];
        }

        // file path
        $filePath = $this->getFullFilePath();

        // queue for moving
        return FileActionHelper::queueMoveFile($this->getPrimaryServerId(), $filePath, $this->id, $newServerId);
    }

    public function hasPendingFileAction() {
        // get database
        $db = Database::getDatabase();

        $rs = (int) $db->getValue('SELECT COUNT(id) FROM file_action WHERE (status = \'pending\' OR status = \'processing\') AND file_id = ' . $this->id);
        if ($rs > 0) {
            return true;
        }

        return false;
    }

    public function getFolderData() {
        if ($this->folderId == null) {
            return false;
        }

        $fileFolder = FileFolder::loadOneById((int) $this->folderId);
        if (!$fileFolder) {
            return false;
        }

        return $fileFolder;
    }

    public function getFolderPath() {
        $fileFolder = $this->getFolderData();
        if (!$fileFolder) {
            return false;
        }

        return $fileFolder->getPath();
    }

    public function isImage() {
        return in_array($this->extension, FileHelper::getImageExtensionsArr());
    }

    public function isDocument() {
        return in_array($this->extension, FileHelper::getDocumentExtensionsArr());
    }

    public function isVideo() {
        return in_array($this->extension, FileHelper::getVideoExtensionsArr());
    }

    public function isAudio() {
        return in_array($this->extension, FileHelper::getAudioExtensionsArr());
    }

    public function isPublic() {
        return $this->isPublic > 0;
    }

    public function getFileHash() {
        $uniqueHash = $this->unique_hash;
        if (strlen($uniqueHash) == 0) {
            // create hash
            $uniqueHash = FileHelper::createUniqueFileHash($this->id);
        }

        return $uniqueHash;
    }

    public function blockFutureUploads() {
        if (strlen($this->getPrimaryFileHash()) == 0) {
            return true;
        }

        // check to make sure we don't already have it blocked
        $isBlocked = FileHelper::checkFileHashBlocked($this->getPrimaryFileHash(), $this->getPrimaryFileSize());
        if ($isBlocked) {
            return true;
        }

        // block file hash
        $fileBlockHash = FileBlockHash::create();
        $fileBlockHash->file_hash = $this->getPrimaryFileHash();
        $fileBlockHash->file_size = $this->getPrimaryFileSize();
        $fileBlockHash->date_created = CoreHelper::sqlDateTime();

        return $fileBlockHash->save();
    }

    public function deleteStats() {
        // reset the stats for the file object
        $this->visits = 0;
        $this->save();

        // clear any within the stats table
        $db = Database::getDatabase();
        $db->query("DELETE FROM stats "
                . "WHERE file_id = :id", [
            'id' => $this->id
        ]);
    }

    public function getFormattedUploadedDate() {
        return CoreHelper::formatDate($this->uploadedDate);
    }

    public function getFormattedFilesize(): string {
        return CoreHelper::formatSize($this->getPrimaryFileSize());
    }

    public function getStatusLabel(): string {
        return FileHelper::getStatusLabel($this->status);
    }

    public function getFileActionCountNonAdmin(): int {
        return UserActionLog::count('file_id = :file_id', [
            'file_id' => $this->id,
        ]);
    }

    /**
     * The main function to use on the download pages to track which pages have been viewed,
     * must be used to move user onto the next download page.
     * 
     * @return string
     */
    public function getNextDownloadPageLink() {
        return $this->getShortUrlPath() . '?pt=' . urlencode($this->createNextPageHash());
    }

    public function createNextPageHash() {
        // pickup the next page number from the session
        $pageNumber = (int) $_SESSION['_download_page_next_page_' . $this->id] + 1;

        // encrypt it so it can't be messed with
        $encrypted = CoreHelper::encryptValue($pageNumber);

        // return a base64 encoded version to keep it safe
        return base64_encode($encrypted);
    }

    public function fileIsOnCurrentServer() {
        if (((int) FileHelper::getCurrentServerId() === (int) $this->getPrimaryServerId()) && file_exists($this->getFullFilePath())) {
            return true;
        }

        return false;
    }

    /**
     * Used only internally for preview generation and similar functions. Returns
     * the content of the file whether stored locally or on another external server
     */
    public function downloadInternally($downloadToken = null) {
        // load the server the file is on
        $uploadServerDetails = $this->loadFileServer();
        $storageType = $uploadServerDetails['serverType'];

        // if we're already on the server, load the file. Include 'local' in the check here
        // to support multiple 'local' servers.
        if ($this->fileIsOnCurrentServer() === true || $storageType === 'local') {
            // return the contents of the local file
            $fullFilePath = $this->getFullFilePath();
            if (file_exists($fullFilePath)) {
                return file_get_contents($fullFilePath);
            }
        }

        // load based on download token, create download url
        if ($downloadToken === null) {
            $downloadToken = $this->generateDirectDownloadTokenForMedia();
        }

        // if not "direct" or "local" server, get file using 'download' method
        if (!in_array($storageType, ['local', 'direct'])) {
            $fileContent = $this->download(false, false, $downloadToken);
            if (!empty($fileContent)) {
                return $fileContent;
            }
        }

        // otherwise, fall back onto getting content via download url
        $downloadUrl = $this->getFullShortUrl(true) . '?' . File::DOWNLOAD_TOKEN_VAR . '=' . $downloadToken;

        // get file content
        $fileContent = CoreHelper::getRemoteUrlContent($downloadUrl);

        // check expected MD5, if less than 20MB (large files will take a long time)
        if ($this->getPrimaryFileSize() < (1024 * 1024 * 20) && md5($fileContent) !== $this->getPrimaryFileHash()) {
            return false;
        }

        return $fileContent;
    }

    public function isAdult() {
        // if we have no adult keywords defined, return false
        if (!strlen(SITE_CONFIG_ADULT_CONTENT_KEYWORDS)) {
            return false;
        }

        // prepare array of strings to check
        $fileWords = array_merge(FileHelper::getKeywordArrFromString($this->originalFilename), $this->getKeywordArray());
        if (count($fileWords) === 0) {
            return false;
        }

        // loop over fileWords and check in banned list
        $adultWords = explode('|', strtolower(SITE_CONFIG_ADULT_CONTENT_KEYWORDS));
        foreach ($fileWords AS $fileWord) {
            if (in_array(strtolower($fileWord), $adultWords)) {
                return true;
            }
        }

        return false;
    }

    public function shouldShowAdultAdvert() {
        if ((int) SITE_CONFIG_DIFFERENT_ADS_FOR_ADULT_CONTENT === 0) {
            return false;
        }

        return $this->isAdult();
    }

    public function getFileArtifacts() {
        return FileArtifact::loadByClause('file_id = :file_id', [
            'file_id' => $this->id,
        ]);
    }

    /**
     * Load one model based on the file short url
     * 
     * @param string $shortUrl
     * @return model
     */
    public static function loadOneByShortUrl($shortUrl) {
        // load our row based on the shortUrl column (for case sensitive use "BINARY shortUrl = :shortUrl", 
        // although it will ignore indexes). Search code for "optional BINARY"
        // to find the other instance.
        return File::loadOneByClause('shortUrl = :shortUrl', [
                    'shortUrl' => $shortUrl,
        ]);
    }

    /**
     * Move to a new parent folder
     */
    public function moveToFolder($parentFolderId = null) {
        $this->folderId = $parentFolderId;
        $this->date_updated = CoreHelper::sqlDateTime();

        return $this->save();
    }
}
