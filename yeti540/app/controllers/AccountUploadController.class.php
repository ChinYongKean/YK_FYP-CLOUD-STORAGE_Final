<?php

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\AuthHelper;
use App\Helpers\CrossSiteActionHelper;
use App\Helpers\FileHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UploaderHelper;
use App\Helpers\UserHelper;

class AccountUploadController extends AccountController
{

    public function ajaxUploader() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get the current logged in user
        $Auth = AuthHelper::getAuth();

        // pickup request for later
        $request = $this->getRequest();
        $db = Database::getDatabase();

        $fid = null;
        if ($request->request->has('fid')) {
            $fid = (int) $request->request->get('fid');
        }
        
        // prepare server upload url
        $serverUploadUrl = FileHelper::getUploadUrl();
        $uploadAction = null;
        if($serverUploadUrl) {
            $uploadUrlSlug = UserHelper::getUploadUrlSlug();
            $uploadAction = CrossSiteActionHelper::appendUrl($serverUploadUrl . (strlen($uploadUrlSlug)?('/'.$uploadUrlSlug):''). '/ajax/file_upload_handler?r=' . htmlspecialchars(_CONFIG_SITE_HOST_URL) . '&p=' . htmlspecialchars(_CONFIG_SITE_PROTOCOL));
        }

        // prep params
        $templateParams = $this->getFileManagerTemplateParams();
        $templateParams = array_merge([
            'fid' => $fid,
            'uploadAction' => $uploadAction,
        ], $templateParams);

        // load template
        return $this->render('account/ajax/uploader.html', $templateParams);
    }

    public function uploaderJs() {
        // get params for later
        $Auth = $this->getAuth();

        // pickup request for later
        $request = $this->getRequest();
        $db = Database::getDatabase();

        // for js translations (doesn't output anything, just ensures they're created)
        TranslateHelper::getTranslation('uploader_hour', 'hour');
        TranslateHelper::getTranslation('uploader_hours', 'hours');
        TranslateHelper::getTranslation('uploader_minute', 'minute');
        TranslateHelper::getTranslation('uploader_minutes', 'minutes');
        TranslateHelper::getTranslation('uploader_second', 'second');
        TranslateHelper::getTranslation('uploader_seconds', 'seconds');
        TranslateHelper::getTranslation('selected', 'selected');
        TranslateHelper::getTranslation('selected_image_clear', 'clear');
        TranslateHelper::getTranslation('account_file_details_clear_selected', 'Clear Selected');

        $fid = null;
        if ($request->request->has('fid')) {
            $fid = (int) $request->request->get('fid');
        }
        
        // prepare server upload url
        $serverUploadUrl = FileHelper::getUploadUrl();
        $uploadAction = null;
        $urlUploadAction = null;
        if($serverUploadUrl) {
            $uploadUrlSlug = UserHelper::getUploadUrlSlug();
            $uploadAction = CrossSiteActionHelper::appendUrl($serverUploadUrl . (strlen($uploadUrlSlug)?('/'.$uploadUrlSlug):'').'/ajax/file_upload_handler?r=' . htmlspecialchars(_CONFIG_SITE_HOST_URL) . '&p=' . htmlspecialchars(_CONFIG_SITE_PROTOCOL));
            $urlUploadAction = CrossSiteActionHelper::appendUrl($serverUploadUrl . '/ajax/url_upload_handler');
        }

        // prep params
        $templateParams = $this->getFileManagerTemplateParams();
        $templateParams = array_merge([
            'chunkedUploadingEnabled' => SITE_CONFIG_CHUNKED_UPLOADING_ENABLED === 'yes',
            'chunkedUploadSize' => UploaderHelper::getChunkedUploadSizeInBytes(),
            'fid' => $fid,
            'maxConcurrentThumbnailRequests' => 5,
            'uploadAction' => $uploadAction,
            'urlUploadAction' => $urlUploadAction,
            'backgroundUrlDownloading' => (SITE_CONFIG_REMOTE_URL_DOWNLOAD_IN_BACKGROUND==='yes' && $Auth->loggedIn() === true),
        ], $templateParams);

        // return rendered javascript
        $response = $this->render('account/partial/_uploader_javascript.html.twig', $templateParams);
        $response->headers->set('Content-Type', 'text/javascript');

        return $response;
    }
}
