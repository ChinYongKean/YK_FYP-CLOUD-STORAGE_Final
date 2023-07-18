<?php

namespace App\Helpers;

use App\Core\Database;

class UploaderHelper
{
    const REMOTE_URL_UPLOAD_FEEDBACK_CHUNKS_BYTES = 200000; // how often to supply feedback on the url uploader

    public static function getLocalTempStorePath() {
        $tmpDir = FileServerHelper::getCurrentServerFileStoragePath() . '_tmp/';
        if (!file_exists($tmpDir)) {
            @mkdir($tmpDir);
        }

        if (!file_exists($tmpDir)) {
            self::exitWithError('Failed creating tmp storage folder for chunked '
                    . 'uploads. Ensure the parent folder has write permissions: ' . $tmpDir);
        }

        if (!is_writable($tmpDir)) {
            self::exitWithError('Temp storage folder for uploads is not writable. '
                    . 'Ensure it has CHMOD 755 or 777 permissions: ' . $tmpDir);
        }

        return $tmpDir;
    }

    static function generateSuccessHtml($fileUpload, $uploadSource = 'direct') {
        // get auth for later
        $Auth = AuthHelper::getAuth();

        // load user folders for later
        $userFolders = FileFolderHelper::loadAllActiveByAccount($Auth->id);

        // generate html response
        $successResultHtml = '';

        // call plugin hooks
        $params = PluginHelper::callHook('uploaderSuccessResultHtml', array(
                'success_result_html' => $successResultHtml,
                'fileUpload' => $fileUpload,
                'userFolders' => $userFolders,
                'uploadSource' => $uploadSource,
        ));

        // reassign success_result_html, note that if the pluginHelper doesn't find
        // any uploaderSuccessResultHtml hooks, the original success_result_html is
        // returned
        $successResultHtml = $params['success_result_html'];

        return $successResultHtml;
    }

    static function generateErrorHtml($fileUpload) {
        // get auth for later
        $Auth = AuthHelper::getAuth();

        // generate html
        $error_result_html = '';

        $error_result_html .= '<td class="error" colspan="2">' . TranslateHelper::t('classuploader_error', 'Error') . ': ';
        $error_result_html .= self::translateError($fileUpload->error);
        $error_result_html .= '</td>';

        // check plugins so the resulting html can be overwritten if set
        $params = PluginHelper::includeAppends('class_uploader_error_result_html.php', array('error_result_html' => $error_result_html, 'fileUpload' => $fileUpload));
        $error_result_html = $params['error_result_html'];

        return $error_result_html;
    }

    static function translateError($error) {
        switch ($error) {
            case 1:
                return TranslateHelper::t('file_exceeds_upload_max_filesize_php_ini_directive', 'File exceeds upload_max_filesize (php.ini directive)');
            case 2:
                return TranslateHelper::t('file_exceeds_max_file_size_html_form_directive', 'File exceeds MAX_FILE_SIZE (HTML form directive)');
            case 3:
                return TranslateHelper::t('file_was_only_partially_uploaded', 'File was only partially uploaded');
            case 4:
                return TranslateHelper::t('no_file_was_uploaded', 'No File was uploaded');
            case 5:
                return TranslateHelper::t('missing_a_temporary_folder', 'Missing a temporary folder');
            case 6:
                return TranslateHelper::t('failed_to_write_file_to_disk', 'Failed to write file to disk');
            case 7:
                return TranslateHelper::t('file_upload_stopped_by_extension', 'File upload stopped by extension');
            case 'maxFileSize':
                return TranslateHelper::t('file_is_too_big', 'File is too big');
            case 'minFileSize':
                return TranslateHelper::t('file_is_too_small', 'File is too small');
            case 'acceptFileTypes':
                return TranslateHelper::t('filetype_is_not_allowed', 'Filetype not allowed');
            case 'maxNumberOfFiles':
                return TranslateHelper::t('max_number_of_files_exceeded', 'Max number of files exceeded');
            case 'uploadedBytes':
                return TranslateHelper::t('uploaded_bytes_exceed_file_size', 'Uploaded bytes exceed file size');
            case 'emptyResult':
                return TranslateHelper::t('empty_file_upload_result', 'Empty file upload result');
            default:
                return $error;
        }
    }

    static function exitWithError($errorStr) {
        // log
        LogHelper::error('UploaderHelper: ' . $errorStr);

        $fileUpload = new \stdClass();
        $fileUpload->error = $errorStr;
        $errorHtml = self::generateErrorHtml($fileUpload);
        $fileUpload->error_result_html = $errorHtml;
        echo json_encode(array($fileUpload), true);
        die();
    }

    static function addUrlToBackgroundQueue($url, $userId, $folderId = null) {
        // make sure we have a user id
        if ($userId == 0) {
            return false;
        }

        // database connection
        $db = Database::getDatabase();

        // current file server if
        $currentFileServerId = FileHelper::getCurrentServerId();

        // make sure it's not already queued for this user
        $found = $db->getValue('SELECT id '
                . 'FROM remote_url_download_queue '
                . 'WHERE user_id=:user_id '
                . 'AND url=:url '
                . 'AND (job_status=\'downloading\' OR job_status=\'pending\' OR job_status=\'processing\') '
                . 'LIMIT 1', array(
            'user_id' => $userId,
            'url' => $url,
        ));
        if ($found) {
            return true;
        }

        // add to backgroud queue
        return $db->query("INSERT INTO remote_url_download_queue (user_id, url, file_server_id, created, folder_id) "
                        . "VALUES (:user_id, :url, :file_server_id, NOW(), :folder_id)", array(
                    'user_id' => (int) $userId,
                    'url' => $url,
                    'file_server_id' => $currentFileServerId,
                    'folder_id' => $folderId,
                        )
        );
    }

    static function uploadingDisabled() {
        // check for admin user
        $Auth = AuthHelper::getAuth();
        if ($Auth->loggedIn()) {
            if ($Auth->level_id == 20) {
                return false;
            }
        }

        if (defined('SITE_CONFIG_UPLOADS_BLOCK_ALL') && (SITE_CONFIG_UPLOADS_BLOCK_ALL == 'yes')) {
            return true;
        }

        return false;
    }

    public static function getUrlParts($url) {
        return parse_url($url);
    }
    
    public static function getChunkedUploadSizeInBytes() {
        if (defined('SITE_CONFIG_CHUNKED_UPLOAD_SIZE_MB') && (is_numeric(SITE_CONFIG_CHUNKED_UPLOAD_SIZE_MB))) {
            return SITE_CONFIG_CHUNKED_UPLOAD_SIZE_MB * 1000000;
        }
        
        // fallback to 100MB
        return 100000000;
    }
}
