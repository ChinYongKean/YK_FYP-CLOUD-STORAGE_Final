<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\AuthHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileFolderHelper;
use App\Helpers\FileHelper;
use App\Helpers\FileServerContainerHelper;
use App\Helpers\FileServerHelper;
use App\Helpers\LogHelper;
use App\Helpers\PluginHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UploaderHelper;
use App\Helpers\UserHelper;
use App\Models\File;
use App\Models\FileArtifact;
use App\Models\FileArtifactStorage;

class Uploader
{
    public $options;
    public $nextFeedbackTracker;
    public $rowId;
    public $fileUpload = null;
    public $md5FileHash = null;

    function __construct($options = null) {
        // get accepted file types
        $acceptedFileTypes = UserHelper::getAcceptedFileTypes();

        // get blocked file types
        $blockedFileTypes = UserHelper::getBlockedFileTypes();

        // get blocked file keywords
        $blockedFileKeywords = UserHelper::getBlockedFilenameKeywords();

        if (isset($options['max_chunk_size'])) {
            $this->options['max_chunk_size'] = (int) $options['max_chunk_size'];
        }

        // get logged in user details
        $Auth = AuthHelper::getAuth();
        $userId = null;
        if ($Auth->loggedIn()) {
            $userId = $Auth->id;
        }

        // default options
        $this->options = array(
            'script_url' => $_SERVER['PHP_SELF'],
            'upload_dir' => FileServerHelper::getCurrentServerFileStoragePath(),
            'upload_url' => dirname($_SERVER['PHP_SELF']) . '/files/',
            'param_name' => 'files',
            'delete_hash' => '',
            'max_file_size' => $this->getMaxUploadSize(),
            'min_file_size' => 1,
            'accept_file_types' => COUNT($acceptedFileTypes) ? ('/(\.|\/)(' . str_replace(".", "", implode("|", $acceptedFileTypes)) . ')$/i') : '/.+$/i',
            'block_file_types' => COUNT($blockedFileTypes) ? ('/(\.|\/)(' . str_replace(".", "", implode("|", $blockedFileTypes)) . ')$/i') : '',
            'block_file_keywords' => $blockedFileKeywords,
            'max_number_of_files' => null,
            'discard_aborted_uploads' => true,
            'max_chunk_size' => 0,
            'folder_id' => 0,
            'user_id' => $userId,
            'uploaded_user_id' => $userId,
            'fail_zero_bytes' => true,
            'upload_source' => 'direct',
            'background_queue_id' => null,
        );

        if ($options) {
            $this->options = array_replace_recursive($this->options, $options);

            // make sure any the uploaded_user_id is copied, encase the above overrode it
            if ($this->options['uploaded_user_id'] === null && $this->options['user_id'] !== null) {
                $this->options['uploaded_user_id'] = $this->options['user_id'];
            }
        }
    }

    public function getMaxUploadSize() {
        // max allowed upload size
        return UserHelper::getMaxUploadFilesize();
    }

    public function getAvailableStorage() {
        // initialize current user
        $Auth = AuthHelper::getAuth();

        // available storage
        $availableStorage = UserHelper::getAvailableFileStorage($Auth->id);

        return $availableStorage;
    }

    public function getFileObject($fileName) {
        $filePath = $this->options['upload_dir'] . $fileName;
        if (is_file($filePath) && $fileName[0] !== '.') {
            $file = new \stdClass();
            $file->name = $fileName;
            $file->size = filesize($filePath);
            $file->url = $this->options['upload_url'] . rawurlencode($file->name);
            $file->delete_url = '~d?' . $this->options['delete_hash'];
            $file->info_url = '~i?' . $this->options['delete_hash'];
            $file->delete_type = 'DELETE';
            $file->delete_hash = $this->options['delete_hash'];
            $file->error = null;

            return $file;
        }

        return null;
    }

    public function hasError($uploadedFile, $file, $error = null) {
        // make sure uploading hasn't been disabled
        if (UploaderHelper::uploadingDisabled() == true) {
            return TranslateHelper::t('uploader_all_blocked', 'Uploading is currently disabled on the site, please try again later.');
        }

        if ($error) {
            return $error;
        }

        if (!preg_match($this->options['accept_file_types'], $file->name)) {
            return 'acceptFileTypes';
        }

        if ($this->options['block_file_types']) {
            if (preg_match($this->options['block_file_types'], $file->name)) {
                return TranslateHelper::t('uploader_blocked_filetype', 'File could not be uploaded due to that file type being banned by the site admin');
            }
        }

        // check for blocked strings within the filename
        if (count($this->options['block_file_keywords'])) {
            foreach ($this->options['block_file_keywords'] AS $keyword) {
                if (stripos($file->name, $keyword) !== false) {
                    return TranslateHelper::t('uploader_blocked_file_keyword', 'File could not be uploaded as the filename was blocked');
                }
            }
        }

        // check for blocked file hashes
        if ($this->md5FileHash === null) {
            // logs
            LogHelper::info('Uploader.class.php::hasError - Getting md5 file hash...');

            $this->md5FileHash = FileHelper::getFileMd5Hash($uploadedFile);

            // logs
            LogHelper::info('Uploader.class.php::hasError - Calculated md5 hash (' . $this->md5FileHash . ')');
        }
        $isBlocked = FileHelper::checkFileHashBlocked($this->md5FileHash, $file->size);
        if ($isBlocked) {
            return TranslateHelper::t('uploader_blocked_file_hash_content', 'File content has been blocked from being uploaded.');
        }

        if ($uploadedFile && file_exists($uploadedFile)) {
            $fileSize = filesize($uploadedFile);
        }
        else {
            $fileSize = $_SERVER['CONTENT_LENGTH'];
        }
        if ($this->options['max_file_size'] && ($fileSize > $this->options['max_file_size'] || $file->size > $this->options['max_file_size'])) {
            return 'maxFileSize';
        }
        if ($this->options['min_file_size'] && $fileSize < $this->options['min_file_size']) {
            return 'minFileSize';
        }
        if (is_int($this->options['max_number_of_files']) && (count($this->getFileObjects()) >= $this->options['max_number_of_files'])) {
            return 'maxNumberOfFiles';
        }

        return null;
    }

    public function handleFileUpload($uploadedFile, $name, $size, $type, $error, $index = null, $contentRange = null, $chunkTracker = null) {
        $fileUpload = new \stdClass();
        $fileUpload->name = stripslashes($name);
        $fileUpload->size = intval($size);
        $fileUpload->type = $type;
        $fileUpload->error = null;

        // logs
        LogHelper::info('Uploader.class.php::handleFileUpload - Started...');

        // save file locally if chunked upload
        if ($contentRange) {
            $localTempStore = UploaderHelper::getLocalTempStorePath();
            $tmpFilename = md5($fileUpload->name);
            $tmpFilePath = $localTempStore . $tmpFilename;

            // if first chunk
            if ($contentRange[1] == 0) {
                // logs
                LogHelper::info('Uploader.class.php::handleFileUpload - Processing first chunk.');

                // ensure the tmp file does not already exist
                if (file_exists($tmpFilePath)) {
                    unlink($tmpFilePath);
                }

                // first clean up any old chunks
                $this->cleanLeftOverChunks();
            }

            // ensure we have the chunk
            if ($uploadedFile && file_exists($uploadedFile)) {
                // multipart/formdata uploads (POST method uploads)
                $fp = fopen($uploadedFile, 'r');
                file_put_contents($tmpFilePath, $fp, FILE_APPEND);
                fclose($fp);

                // check if this is not the last chunk
                if ($contentRange[3] != filesize($tmpFilePath)) {
                    // logs
                    LogHelper::info('Uploader.class.php::handleFileUpload - Saved chunk, awaiting further chunks...');

                    // exit
                    return $fileUpload;
                }

                // logs
                LogHelper::info('Uploader.class.php::handleFileUpload - Received all chunks, finishing upload.');

                // otherwise assume we have the whole file
                $uploadedFile = $tmpFilePath;
                $fileUpload->size = filesize($tmpFilePath);
            }
            else {
                // exit
                return $fileUpload;
            }
        }

        // logs
        LogHelper::info('Uploader.class.php::handleFileUpload - Running error checks...');

        $fileUpload->error = $this->hasError($uploadedFile, $fileUpload, $error);
        if (!$fileUpload->error) {
            if (strlen(trim($fileUpload->name)) == 0) {
                $fileUpload->error = TranslateHelper::t('classuploader_filename_not_found', 'Filename not found.');
            }
        }
        elseif ((intval($size) == 0) && ($this->options['fail_zero_bytes'] == true)) {
            $fileUpload->error = TranslateHelper::t('classuploader_file_received_has_zero_size', 'File received has zero size. This is likely an issue with the maximum permitted size within PHP');
        }
        elseif (intval($size) > $this->options['max_file_size']) {
            $fileUpload->error = TranslateHelper::t('classuploader_file_received_larger_than_permitted', 'File received is larger than permitted. (max [[[MAX_FILESIZE]]])', array('MAX_FILESIZE' => CoreHelper::formatSize($this->options['max_file_size'])));
        }

        // logs
        LogHelper::info('Uploader.class.php::handleFileUpload - Completed error checks, moving into storage.');

        if (!$fileUpload->error && $fileUpload->name) {
            $fileUpload = $this->moveIntoStorage($fileUpload, $uploadedFile);
        }

        // no error, add success html
        if ($fileUpload->error === null) {
            $fileUpload->url_html = '&lt;a href=&quot;' . $fileUpload->url . '&quot; target=&quot;_blank&quot; title=&quot;' . TranslateHelper::t('view_image_on', 'View image on') . ' ' . SITE_CONFIG_SITE_NAME . '&quot;&gt;' . TranslateHelper::t('view', 'View') . ' ' . $fileUpload->name . ' ' . TranslateHelper::t('on', 'on') . ' ' . SITE_CONFIG_SITE_NAME . '&lt;/a&gt;';
            $fileUpload->url_bbcode = '[url]' . $fileUpload->url . '[/url]';
            $fileUpload->success_result_html = UploaderHelper::generateSuccessHtml($fileUpload, $this->options['upload_source']);
        }
        else {
            $fileUpload->error_result_html = UploaderHelper::generateErrorHtml($fileUpload);
        }

        // logs
        LogHelper::info('Uploader.class.php::handleFileUpload - Finished.');

        return $fileUpload;
    }

    public function post()
    {
        $upload = $_FILES[$this->options['param_name']] ?? [
                'tmp_name' => null,
                'name' => null,
                'size' => null,
                'type' => null,
                'error' => null,
            ];

        // parse the Content-Range header, which has the following form:
        // Content-Range: bytes 0-524287/2000000
        $contentRange = $this->getServerVar('HTTP_CONTENT_RANGE') ?
            preg_split('/[^0-9]+/', $this->getServerVar('HTTP_CONTENT_RANGE')) : null;

        $info = [];
        if (is_array($upload['tmp_name'])) {
            foreach ($upload['tmp_name'] as $index => $value) {
                $info[] = $this->handleFileUpload(
                    $upload['tmp_name'][$index],
                    $upload['name'][$index],
                    $upload['size'][$index],
                    $upload['type'][$index],
                    $upload['error'][$index],
                    $index,
                    $contentRange,
                    $_REQUEST['cTracker'] ?? null
                );
            }
        } else {
            $info[] = $this->handleFileUpload(
                $upload['tmp_name'],
                $upload['name'],
                $upload['size'],
                $upload['type'],
                $upload['error'],
                null,
                $contentRange,
                $_REQUEST['cTracker'] ?? null
            );
        }
        header('Vary: Accept');
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-type: application/json');
        } else {
            header('Content-type: text/plain');
        }

        return json_encode($info);
    }

    public function handleRemoteUrlUpload($url, $rowId = 0) {
        $this->rowId = $rowId;
        $this->nextFeedbackTracker = UploaderHelper::REMOTE_URL_UPLOAD_FEEDBACK_CHUNKS_BYTES;

        $this->fileUpload = new \stdClass();

        // filename
        $array = explode('/', $url);
        $realFilename = trim(end($array));

        // remove anything before a question mark
        $realFilename = trim(current(explode('?', $realFilename)));
        $realFilename = trim(current(explode(';', $realFilename)));
        if (strlen($realFilename) == 0) {
            $realFilename = 'file.txt';
        }
        // decode filename
        $realFilename = urldecode($realFilename);
        $this->fileUpload->name = $realFilename;

        $this->fileUpload->size = 0;
        $this->fileUpload->type = '';
        $this->fileUpload->error = null;
        $this->fileUpload->rowId = $rowId;
        $this->fileUpload->requestUrl = $url;

        $remoteFileDetails = $this->getRemoteFileDetails($url);
        $remoteFilesize = (int) $remoteFileDetails['bytes'];
        if ($remoteFilesize > $this->options['max_file_size']) {
            $this->fileUpload->error = TranslateHelper::t('classuploader_file_larger_than_permitted', 'File is larger than permitted. (max [[[MAX_FILESIZE]]])', array('MAX_FILESIZE' => CoreHelper::formatSize($this->options['max_file_size'])));
        }
        else {
            // look for real filename if passed in headers
            if (strlen($remoteFileDetails['real_filename'])) {
                $realFilename = trim(current(explode(';', $remoteFileDetails['real_filename'])));
                if (strlen($realFilename)) {
                    $this->fileUpload->name = $realFilename;
                }
            }

            // try to get the file locally
            $localFile = $this->downloadRemoteFile($url, true);

            // reconnect db if it's gone away
            $db = Database::getDatabase(true);
            $db->close();
            $db = Database::getDatabase(true);

            if ($localFile === false) {
                $this->fileUpload->error = TranslateHelper::t('classuploader_could_not_get_remote_file', 'Could not get remote file. [[[FILE_URL]]]', array('FILE_URL' => $url));
            }
            else {
                $size = (int) filesize($localFile);
                $this->fileUpload->error = $this->hasError($localFile, $this->fileUpload);
                if (!$this->fileUpload->error) {
                    if (strlen(trim($this->fileUpload->name)) == 0) {
                        $this->fileUpload->error = TranslateHelper::t('classuploader_filename_not_found', 'Filename not found.');
                    }
                }
                elseif (intval($size) == 0) {
                    $this->fileUpload->error = TranslateHelper::t('classuploader_file_has_zero_size', 'File received has zero size.');
                }
                elseif (intval($size) > $this->options['max_file_size']) {
                    $this->fileUpload->error = TranslateHelper::t('classuploader_file_received_larger_than_permitted', 'File received is larger than permitted. (max [[[MAX_FILESIZE]]])', array('MAX_FILESIZE' => CoreHelper::formatSize($this->options['max_file_size'])));
                }

                if (!$this->fileUpload->error && $this->fileUpload->name) {
                    // filesize
                    $this->fileUpload->size = filesize($localFile);

                    // get mime type
                    $mimeType = FileHelper::estimateMimeTypeFromExtension($this->fileUpload->name, 'application/octet-stream');
                    if (($mimeType == 'application/octet-stream') && (class_exists('finfo', false))) {
                        $finfo = new \finfo;
                        $mimeType = $finfo->file($localFile, FILEINFO_MIME);
                    }
                    $this->fileUpload->type = $mimeType;

                    // save into permanent storage
                    $this->fileUpload = $this->moveIntoStorage($this->fileUpload, $localFile);
                }
                else {
                    @unlink($localFile);
                }
            }
        }

        // no error, add success html
        if ($this->fileUpload->error === null) {
            $this->fileUpload->url_html = '&lt;a href=&quot;' . $this->fileUpload->url . '&quot; target=&quot;_blank&quot; title=&quot;' . TranslateHelper::t('view_image_on', 'View image on') . ' ' . SITE_CONFIG_SITE_NAME . '&quot;&gt;' . TranslateHelper::t('view', 'View') . ' ' . $this->fileUpload->name . ' ' . TranslateHelper::t('on', 'on') . ' ' . SITE_CONFIG_SITE_NAME . '&lt;/a&gt;';
            $this->fileUpload->url_bbcode = '[url]' . $this->fileUpload->url . '[/url]';
            $this->fileUpload->success_result_html = UploaderHelper::generateSuccessHtml($this->fileUpload, $this->options['upload_source']);
        }
        else {
            $this->fileUpload->error_result_html = UploaderHelper::generateErrorHtml($this->fileUpload);
        }

        $this->remote_url_event_callback(array("done" => $this->fileUpload));

        return $this->fileUpload;
    }

    public function getRemoteFileDetails($url) {
        $rs = [];
        $rs['bytes'] = 0;
        $rs['real_filename'] = null;
        if (function_exists('curl_init')) {
            // initialize curl with given url
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 15);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            $execute = curl_exec($ch);

            // check if any error occured
            if (!curl_errno($ch)) {
                $rs['bytes'] = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

                // this catches filenames between quotes
                if (preg_match('/.*filename=[\'\"]([^\'\"]+)/', $execute, $matches)) {
                    $rs['real_filename'] = $matches[1];
                }
                // if filename is not quoted, we take all until the next space
                elseif (preg_match("/.*filename=([^ ]+)/", $execute, $matches)) {
                    $rs['real_filename'] = $matches[1];
                }

                // make sure there are no quotes
                $rs['real_filename'] = str_replace('"', '', $rs['real_filename']);
            }

            curl_close($ch);
        }
        else {
            UploaderHelper::exitWithError(TranslateHelper::t('classuploader_curl_module_not_found', 'Curl module not found. Please enable within PHP to enable remote uploads.'));
        }

        return $rs;
    }

    public function downloadRemoteFile($url, $streamResponse = false) {
        // save locally
        $tmpDir = UploaderHelper::getLocalTempStorePath();
        $tmpName = md5($url . microtime());
        $tmpFullPath = $tmpDir . $tmpName;

        // extract username and password, if available
        $urlParts = UploaderHelper::getUrlParts($url);
        $urlUser = null;
        $urlPass = null;
        if ((isset($urlParts['user'])) && (strlen($urlParts['user']))) {
            $urlUser = $urlParts['user'];
        }
        if ((isset($urlParts['pass'])) && (strlen($urlParts['pass']))) {
            $urlPass = $urlParts['pass'];
        }

        // validation, only allow https or http
        if (!in_array(strtolower($urlParts['scheme']), array('http', 'https'))) {
            return false;
        }

        // use curl
        if (function_exists('curl_init')) {
            // get file via curl
            $fp = fopen($tmpFullPath, 'w+');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 15);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60 * 60 * 24); // 24 hours
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // 15 seconds
            curl_setopt($ch, CURLOPT_HEADER, false);
            // allow for http auth
            if ($urlUser != null) {
                curl_setopt($ch, CURLOPT_USERPWD, $urlUser . ':' . $urlPass);
            }
            if ($streamResponse === true) {
                curl_setopt($ch, CURLOPT_NOPROGRESS, false);
                curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array($this, 'remoteUrlCurlProgressCallback'));
            }
            //curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            if (curl_exec($ch) === false) {
                // log error
                LogHelper::error('Failed getting url. Error: ' . curl_error($ch) . ' (' . $url . ')');
                return false;
            }
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            // remove if no a valid status code
            if (($status === 404) || ($status === 401)) {
                @unlink($tmpFullPath);
            }
        }
        // use file_get_contents
        else {
            if (function_exists('stream_context_create')) {
                $httpArr = array(
                    'timeout' => 15, // 15 seconds
                );

                if ($streamResponse === true) {
                    $httpArr['notification'] = array($this, 'remoteUrlCurlProgressCallback');
                }

                if ($urlUser != null) {
                    $httpArr['header'] = "Authorization: Basic " . base64_encode($urlUser . ':' . $urlPass);
                }

                $ctx = stream_context_create(array('http' =>
                    $httpArr
                ));
            }

            // get file content
            $fileData = @file_get_contents($url);
            @file_put_contents($tmpFullPath, $fileData);
        }

        // test to see if we saved the file
        if ((file_exists($tmpFullPath)) && (filesize($tmpFullPath) > 0)) {
            return $tmpFullPath;
        }

        // clear blank file
        if (file_exists($tmpFullPath)) {
            @unlink($tmpFullPath);
        }

        return false;
    }

    function remote_url_event_callback($message) {
        echo '<script>window.parent.postMessage({
            "func": "updateUrlProgress",
            "message": ' . json_encode($message) . '
        }, "*");</script>';
        ob_flush();
        flush();
    }

    function remote_url_stream_notification_callback($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max) {
        if ($notification_code == STREAM_NOTIFY_PROGRESS) {
            if ($bytes_transferred) {
                if ($bytes_transferred > $this->nextFeedbackTracker) {
                    $this->remote_url_event_callback(array(
                        "progress" => array(
                            "loaded" => $bytes_transferred,
                            "total" => $bytes_max,
                            "rowId" => $this->rowId,
                        )
                    ));
                    $this->nextFeedbackTracker = $this->nextFeedbackTracker + UploaderHelper::REMOTE_URL_UPLOAD_FEEDBACK_CHUNKS_BYTES;
                }
            }
        }
    }

    function remoteUrlCurlProgressCallback($download_size, $downloaded_size, $upload_size, $uploaded_size, $other = null) {
        // allow for the new option added AT THE BEGINNING! in PHP v5.5
        if (is_resource($download_size)) {
            $download_size = $downloaded_size;
            $downloaded_size = $upload_size;
            $upload_size = $uploaded_size;
            $uploaded_size = $other;
        }

        // log in the database or on screen
        if ((int) $this->options['background_queue_id']) {
            $db = Database::getDatabase(true);
            $percent = (int) $download_size > 0 ? ceil(($downloaded_size / $download_size) * 100) : 0;
            $db->query('UPDATE remote_url_download_queue '
                    . 'SET downloaded_size=:downloaded_size, '
                    . 'total_size=:total_size, '
                    . 'download_percent=:download_percent '
                    . 'WHERE id=:id '
                    . 'LIMIT 1', array(
                'downloaded_size' => $downloaded_size,
                'total_size' => $download_size,
                'download_percent' => (int) $percent,
                'id' => (int) $this->options['background_queue_id'],
            ));

            // stop loads of loops
            $next = UploaderHelper::REMOTE_URL_UPLOAD_FEEDBACK_CHUNKS_BYTES;
            if ($download_size > 0) {
                $next = ceil($download_size / 100);
            }
            $this->nextFeedbackTracker = $this->nextFeedbackTracker + $next;
        }
        elseif ($downloaded_size > $this->nextFeedbackTracker) {
            $this->remote_url_event_callback(array(
                "progress" => array(
                    "loaded" => $downloaded_size,
                    "total" => $download_size,
                    "rowId" => $this->rowId,
                )
            ));

            // stop loads of loops
            $this->nextFeedbackTracker = $this->nextFeedbackTracker + UploaderHelper::REMOTE_URL_UPLOAD_FEEDBACK_CHUNKS_BYTES;
        }
    }

    public function moveIntoStorage($fileUpload, $tmpFile, $keepOriginal = false) {
        if ($fileUpload->name[0] === '.') {
            $fileUpload->name = substr($fileUpload->name, 1);
        }
        $fileUpload->name = trim($fileUpload->name);
        if (strlen($fileUpload->name) == 0) {
            $fileUpload->name = date('Ymdhi');
        }
        $parts = explode(".", $fileUpload->name);
        $lastPart = end($parts);
        $extension = strtolower($lastPart);

        // logs
        LogHelper::info('Uploader.class.php::moveIntoStorage - Received request to move into storage (' . $tmpFile . ')');

        // figure out upload type
        $file_size = 0;

        // store the actual file
        $rs = $this->_storeFile($fileUpload, $tmpFile, $keepOriginal);
        $file_size = $rs['file_size'];
        $file_path = $rs['file_path'];
        $uploadServerId = $rs['uploadServerId'];
        $fileUpload = $rs['fileUpload'];
        $newFilename = $rs['newFilename'];
        $fileHash = $rs['fileHash'];

        // reset the connection to the database so mysql doesn't time out
        $db = Database::getDatabase(true);
        $db->close();
        $db = Database::getDatabase(true);

        // check filesize uploaded matches tmp uploaded
        if (($file_size == $fileUpload->size) && (!$fileUpload->error)) {
            $fileUpload->url = $this->options['upload_url'] . rawurlencode($fileUpload->name);

            // insert into the db
            $fileUpload->size = $file_size;
            $fileUpload->delete_url = '~d?' . $this->options['delete_hash'];
            $fileUpload->info_url = '~i?' . $this->options['delete_hash'];
            $fileUpload->delete_type = 'DELETE';
            $fileUpload->delete_hash = $this->options['delete_hash'];

            // create delete hash, make sure it's unique
            $deleteHash = md5($fileUpload->name . CoreHelper::getUsersIPAddress() . microtime());

            // get database connection
            $db = Database::getDatabase(true);

            // setup folder id for file
            $folderId = null;
            if (((int) $this->options['folder_id'] > 0) && ((int) $this->options['user_id'] > 0)) {
                // make sure the current user owns the folder or has been shared it with upload rights
                $validFolder = $db->getRow('SELECT userId '
                        . 'FROM file_folder '
                        . 'WHERE id=' . (int) $this->options['folder_id'] . ' '
                        . 'AND (userId = ' . (int) $this->options['user_id'] . ' '
                        . 'OR id IN (SELECT folder_id FROM file_folder_share LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id WHERE folder_id = ' . (int) $this->options['folder_id'] . ' '
                        . 'AND (shared_with_user_id = ' . (int) $this->options['uploaded_user_id'] . ' OR (shared_with_user_id IS NULL AND is_global = 1)) '
                        . 'AND share_permission_level IN ("upload_download", "all"))) '
                        . 'LIMIT 1');
                if ($validFolder) {
                    $folderId = (int) $this->options['folder_id'];

                    // set user_id to the owner of the folder, this is needed so internal sharing works as expected
                    $this->options['user_id'] = (int) $validFolder['userId'];
                }
            }
            if ((int) $folderId == 0) {
                $folderId = null;
            }

            // make sure the original filename is unique in the selected folder
            $originalFilename = $fileUpload->name;
            if ((int) $this->options['user_id'] > 0) {
                $foundExistingFile = 1;
                $tracker = 2;
                while ($foundExistingFile >= 1) {
                    $foundExistingFile = (int) $db->getValue('SELECT COUNT(id) '
                                    . 'FROM file '
                                    . 'WHERE originalFilename = ' . $db->quote($originalFilename) . ' '
                                    . 'AND status = "active" '
                                    . 'AND userId = ' . (int) $this->options['user_id'] . ' '
                                    . 'AND folderId ' . ($folderId === NULL ? 'IS NULL' : ('= ' . (int) $folderId)));
                    if ($foundExistingFile >= 1) {
                        $originalFilename = substr($fileUpload->name, 0, strlen($fileUpload->name) - strlen($extension) - 1) . ' (' . $tracker . ').' . $extension;
                        $tracker++;
                    }
                }
            }
            $fileUpload->name = FileHelper::makeFilenameSafe($originalFilename);
            if ($this->md5FileHash === null) {
                $this->md5FileHash = FileHelper::getFileMd5Hash($tmpFile);
            }
            $fileUpload->hash = $this->md5FileHash;

            if (FileHelper::checkFileHashBlocked($fileUpload->hash, $fileUpload->size)) {
                $fileUpload->error = TranslateHelper::t('classuploader_file_is_banned', 'File is banned from being uploaded to this website.');
            }

            if (!$fileUpload->error) {
                // insert entry into the DB rather than move etc
                try {
                    // store in db
                    $file = FileHelper::createFileEntry([
                        'original_filename' => $fileUpload->name,
                        'extension' => $extension,
                        'file_type' => $fileUpload->type,
                        'temp_file_path' => $tmpFile,
                        'file_size' => $fileUpload->size,
                        'file_hash' => $fileUpload->hash,
                        'local_file_path' => (substr($file_path, 0, strlen($this->options['upload_dir'])) == $this->options['upload_dir']) ? substr($file_path, strlen($this->options['upload_dir'])) : $file_path,
                        'user_id' => $this->options['user_id'],
                        'uploaded_user_id' => $this->options['uploaded_user_id'],
                        'file_server_id' => $uploadServerId,
                        'folder_id' => $folderId,
                        'upload_source' => $this->options['upload_source'],
                    ]);
                } catch (\Exception $e) {
                    $this->output($e->getMessage());

                    return false;
                }

                // update fileUpload with file location
                $fileUpload->url = $file->getFullShortUrl();
                $fileUpload->delete_url = $file->getDeleteUrl();
                $fileUpload->info_url = $file->getInfoUrl();
                $fileUpload->stats_url = $file->getStatisticsUrl();
                $fileUpload->delete_hash = $file->deleteHash;
                $fileUpload->short_url = $file->shortUrl;
                $fileUpload->file_id = $file->id;
                $fileUpload->unique_hash = $file->unique_hash;

                // call plugin hooks
                PluginHelper::callHook('uploaderSuccess', array(
                    'file' => $file,
                    'tmpFile' => $tmpFile,
                ));
            }
        }
        else if ($this->options['discard_aborted_uploads']) {
            //@TODO - make ftp compatible
            @unlink($file_path);
            @unlink($tmpFile);
            if (!isset($fileUpload->error)) {
                $fileUpload->error = TranslateHelper::t('classuploader_general_upload_error', 'General upload error, please contact support. Expected size: [[[FILE_SIZE]]]. Received size: [[[FILE_UPLOAD_SIZE]]].', array('FILE_SIZE' => $file_size, 'FILE_UPLOAD_SIZE' => $fileUpload->size));
            }
        }

        return $fileUpload;
    }

    public function _storeFile($fileUpload, $tmpFile, $keepOriginal = false) {
        // create file hash
        if ($this->md5FileHash === null) {
            $this->md5FileHash = FileHelper::getFileMd5Hash($tmpFile);
        }

        // logs
        LogHelper::info('Uploader.class.php::_storeFile - Starting...');

        // refresh db connection
        $db = Database::getDatabase();
        $db->close();
        $db = Database::getDatabase(true, true);

        // logs
        LogHelper::info('Uploader.class.php::_storeFile - Reconnected DB.');
        
        // setup new filename
        $dedupeChecks = true;
        $Auth = AuthHelper::getAuth();
        if(SITE_CONFIG_UPLOAD_STORAGE_FILENAME_STRUCTURE === 'User Id/Original Filename' && $Auth->loggedIn()) {
            // "User Id/Original Filename"
            $newFilenameSubfolder = (int)$Auth->id;

            // make sure the filename is unique to avoid overwrites
            $existingFile = true;
            $filenameTracker = 0;
            while($existingFile) {
                // prepare filename
                $newFilename = trim(FileHelper::makeFilenameSafe($fileUpload->name), " .\n\r\t\v\x00");
                $parts = explode(".", $newFilename);
                $extension = end($parts);

                // append filename if $filenameTracker > 0
                if($filenameTracker > 0) {
                    $newFilename = substr($newFilename, 0, strlen($newFilename) - strlen($extension) - 1) . ' (' . $filenameTracker . ').' . $extension;
                }

                $existingFile = $db->getValue('SELECT fa.id '
                    .'FROM file_artifact fa '
                    .'LEFT JOIN file f '
                    .'ON fa.file_id = f.id '
                    .'WHERE fa.local_file_path = :local_file_path '
                    .'AND f.userId = :user_id '
                    .'LIMIT 1', [
                    'local_file_path' => $newFilenameSubfolder.'/'.$newFilename,
                    'user_id' => (int)$Auth->id,
                ]);
                $filenameTracker++;
            }

            // don't run de-dupe checks
            $dedupeChecks = false;
        }
        else {
            // "Secure Hashed"
            $newFilename = md5(microtime().$tmpFile.$this->md5FileHash);
            $newFilenameSubfolder = substr($newFilename, 0, 2);
        }

        // select server from pool
        // if this is a 'direct' server, and it's active, use it
        $uploadServerId = null;
        $uploadServerDetails = FileHelper::getCurrentServerDetails();
        if ($uploadServerDetails['serverType'] == 'direct' && (int)$uploadServerDetails['statusId'] === 2) {
            // direct server
            $uploadServerId = $uploadServerDetails['id'];
        }

        // failed loading a server id so far, try from server pool
        if ($uploadServerId === null) {
            // select server from pool
            $uploadServerId = FileServerHelper::getAvailableServerId();
        }

        // try to load the server details
        $uploadServerDetails = $db->getRow('SELECT * '
                . 'FROM file_server '
                . 'WHERE id = :id', array(
            'id' => (int) $uploadServerId,
        ));
        if (!$uploadServerDetails) {
            // if we failed to load any server, fallback on the current server
            $uploadServerDetails = FileHelper::getCurrentServerDetails();
            $uploadServerId = $uploadServerDetails['id'];
        }

        // override storage path
        if (strlen($uploadServerDetails['storagePath'])) {
            $this->options['upload_dir'] = $uploadServerDetails['storagePath'];
            if (substr($this->options['upload_dir'], strlen($this->options['upload_dir']) - 1, 1) == '/') {
                $this->options['upload_dir'] = substr($this->options['upload_dir'], 0, strlen($this->options['upload_dir']) - 1);
            }
            $this->options['upload_dir'] .= '/';
        }

        // check if the file hash already exists
        $fileExists = false;
        $findFile = false;
        if ($dedupeChecks === true && $fileUpload->size > 0) {
            // logs
            LogHelper::info('Uploader.class.php::_storeFile - Checking if file already exists (dedupe).');

            $findFile = $db->getRow("SELECT file.*, fa.file_hash, fa.file_size, fas.file_server_id, fa.local_file_path  "
                    . "FROM file "
                    . "LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = 'primary' "
                    . "LEFT JOIN file_artifact_storage fas ON fa.id = fas.file_artifact_id "
                    . "WHERE fa.file_hash = :file_hash "
                    . "AND status = 'active' "
                    . "AND fa.file_size = :file_size "
                    . "LIMIT 1", array(
                'file_hash' => $this->md5FileHash,
                'file_size' => (int) $fileUpload->size,
            ));
            if ($findFile !== false) {
                $fileExists = true;
            }
        }

        if (!$fileExists) {
            // logs
            LogHelper::info('Uploader.class.php::_storeFile - File does not exist, storing.');

            // call plugin hooks
            $rsArr = PluginHelper::callHook('storeFile', array(
                        'actioned' => false,
                        'file_path' => '',
                        'uploadServerDetails' => $uploadServerDetails,
                        'fileUpload' => $fileUpload,
                        'newFilename' => $newFilename,
                        'newFilenameSubfolder' => $newFilenameSubfolder,
                        'tmpFile' => $tmpFile,
                        'uploader' => $this,
            ));
            if ($rsArr['actioned']) {
                // handle result
                $fileUpload = $rsArr['fileUpload'];
                $filePath = $rsArr['file_path'];
                $fileSize = $rsArr['file_size'];
            }
            // local, direct or ftp storage methods
            else {
                // logs
                LogHelper::info('Uploader.class.php::_storeFile - Storage type: ' . $uploadServerDetails['serverType'] . ' (Server ID: '.$uploadServerDetails['id'].').');

                // move remotely via ftp
                if ($uploadServerDetails['serverType'] == 'ftp') {
                    // connect ftp
                    $conn_id = ftp_connect($uploadServerDetails['ipAddress'], $uploadServerDetails['ftpPort'], 30);
                    if ($conn_id === false) {
                        $fileUpload->error = TranslateHelper::t('classuploader_could_not_connect_file_server', 'Could not connect to file server [[[IP_ADDRESS]]]', array('IP_ADDRESS' => $uploadServerDetails['ipAddress']));
                    }

                    // authenticate
                    if (!$fileUpload->error) {
                        $login_result = ftp_login($conn_id, $uploadServerDetails['ftpUsername'], $uploadServerDetails['ftpPassword']);
                        if ($login_result === false) {
                            $fileUpload->error = TranslateHelper::t('classuploader_could_not_authenticate_with_file_server', 'Could not authenticate with file server [[[IP_ADDRESS]]]', array('IP_ADDRESS' => $uploadServerDetails['ipAddress']));
                        }
                    }

                    // create the upload folder
                    $uploadPathDir = $this->options['upload_dir'] . $newFilenameSubfolder;
                    if (!$fileUpload->error) {
                        if (!ftp_mkdir($conn_id, $uploadPathDir)) {
                            // Error reporting removed for now as it causes issues with existing folders. Need to add a check in before here
                            // to see if the folder exists, then create if not.
                            // $fileUpload->error = 'There was a problem creating the storage folder on '.$uploadServerDetails['ipAddress'];
                        }
                    }

                    // upload via ftp
                    if (!$fileUpload->error) {
                        $filePath = $uploadPathDir . '/' . $newFilename;
                        clearstatcache();
                        if ($tmpFile) {
                            $serverConfigArr = '';
                            if (strlen($uploadServerDetails['serverConfig'])) {
                                $serverConfig = json_decode($uploadServerDetails['serverConfig'], true);
                                if (is_array($serverConfig)) {
                                    $serverConfigArr = $serverConfig;
                                }
                            }

                            if ((isset($serverConfigArr['ftp_passive_mode'])) && ($serverConfigArr['ftp_passive_mode'] == 'yes')) {
                                // enable passive mode
                                ftp_pasv($conn_id, true);
                            }

                            // initiate ftp
                            $ret = ftp_nb_put($conn_id, $filePath, $tmpFile, FTP_BINARY, FTP_AUTORESUME);
                            while ($ret == FTP_MOREDATA) {
                                // continue uploading
                                $ret = ftp_nb_continue($conn_id);
                            }

                            if ($ret != FTP_FINISHED) {
                                $fileUpload->error = TranslateHelper::t('classuploader_there_was_problem_uploading_file', 'There was a problem uploading the file to [[[IP_ADDRESS]]]', array('IP_ADDRESS' => $uploadServerDetails['ipAddress']));
                            }
                            else {
                                $fileSize = filesize($tmpFile);
                                if ($keepOriginal == false) {
                                    @unlink($tmpFile);
                                }
                            }
                        }
                    }

                    // close ftp connection
                    ftp_close($conn_id);
                }
                elseif (substr($uploadServerDetails['serverType'], 0, 10) == 'flysystem_') {
                    $filesystem = FileServerContainerHelper::init($uploadServerDetails['id']);
                    if (!$filesystem) {
                        $fileUpload->error = TranslateHelper::t('classuploader_could_not_setup_adapter', 'Could not setup adapter to upload file.');
                    }

                    if (!$fileUpload->error) {
                        $uploadPathDir = $newFilenameSubfolder;
                        $filePath = $uploadPathDir . '/' . $newFilename;

                        // upload the file
                        try {
                            // upload file
                            $stream = fopen($tmpFile, 'r+');
                            $rs = $filesystem->writeStream($filePath, $stream);
                            if (!$rs) {
                                $fileUpload->error = 'Could not upload file. Please contact support or try again.';
                            }
                            else {
                                $fileSize = filesize($tmpFile);
                                if ($keepOriginal == false) {
                                    @unlink($tmpFile);
                                }
                            }
                        }
                        catch (Exception $e) {
                            $fileUpload->error = $e->getMessage();
                        }
                    }
                }
                // move into local storage
                else {
                    // logs
                    LogHelper::info('Storing file locally. Using storage base path: ' . $this->options['upload_dir']);

                    // check the upload folder
                    if (($uploadServerDetails['serverType'] == 'direct') || (!file_exists($this->options['upload_dir']))) {
                        $this->options['upload_dir'] = DOC_ROOT . '/' . $this->options['upload_dir'];
                        
                        // logs
                        LogHelper::info('We are on a direct server or additional local server, using new storage path: ' . $this->options['upload_dir']);
                    }

                    // fallback
                    if (!file_exists($this->options['upload_dir'])) {
                        $oldPath = $this->options['upload_dir'];
                        $this->options['upload_dir'] = FileServerHelper::getCurrentServerFileStoragePath();
                        
                        // logs
                        LogHelper::info('Storage path does not exist ('.$oldPath.'). If this is a symlink, ensure it has write permissions and you do not have open_basedir enabled in PHP. Using fallback: ' . $this->options['upload_dir']);
                        unset($oldPath);
                    }

                    // create the upload folder
                    $uploadPathDir = $this->options['upload_dir'] . $newFilenameSubfolder;
                    @mkdir($uploadPathDir);
                    @chmod($uploadPathDir, 0777);

                    $filePath = $uploadPathDir . '/' . $newFilename;

                    // logs
                    LogHelper::info('Storing file locally: ' . $tmpFile . ' => ' . $filePath);
                    clearstatcache();

                    $rs = false;
                    if ($tmpFile) {
                        if ($keepOriginal == true) {
                            $rs = copy($tmpFile, $filePath);
                        }
                        else {
                            $rs = rename($tmpFile, $filePath);
                        }
                        if ($rs) {
                            @chmod($filePath, 0777);
                        }
                    }

                    if ($rs == false) {
                        $fileUpload->error = TranslateHelper::t('classuploader_could_not_move_file_into_storage_on_x', 'Could not move the file into storage on [[[SERVER]]], possibly a permissions issue with the file storage directory.', array('SERVER' => _CONFIG_SITE_HOST_URL)) . ' - ' . $tmpFile . ' - ' . $filePath;
                    }
                    else {
                        // logs
                        LogHelper::info('Stored successfully.');
                    }
                    $fileSize = filesize($filePath);
                }
            }
        }
        else {
            // logs
            LogHelper::info('Uploader.class.php::_storeFile - File is duplicate, linking instead or storing.');

            $fileSize = $findFile['file_size'];
            $filePath = $this->options['upload_dir'] . $findFile['local_file_path'];
            $uploadServerId = $findFile['file_server_id'];
        }

        $rs = [];
        $rs['file_size'] = $fileSize;
        $rs['file_path'] = $filePath;
        $rs['uploadServerId'] = $uploadServerId;
        $rs['fileUpload'] = $fileUpload;
        $rs['newFilename'] = $newFilename;
        $rs['relative_file_path'] = (substr($filePath, 0, strlen($this->options['upload_dir'])) == $this->options['upload_dir']) ? substr($filePath, strlen($this->options['upload_dir'])) : $filePath;
        $rs['fileHash'] = $this->md5FileHash;

        // logs
        LogHelper::info('Uploader.class.php::_storeFile - Finished.');

        return $rs;
    }

    /*
     * Removes any old files left over from failed chunked uploads
     */

    private function cleanLeftOverChunks() {
        // loop local tmp folder and clear any older than 3 days old
        $localTempStore = UploaderHelper::getLocalTempStorePath();
        foreach (glob($localTempStore . "*") as $file) {
            // protect the filename
            if (filemtime($file) < time() - 60 * 60 * 24 * 3) {
                // double check we're in the file store
                if (substr($file, 0, strlen(FileServerHelper::getCurrentServerFileStoragePath())) == FileServerHelper::getCurrentServerFileStoragePath()) {
                    @unlink($file);
                }
            }
        }
    }

    protected function getServerVar($id) {
        return isset($_SERVER[$id]) ? $_SERVER[$id] : '';
    }

}
