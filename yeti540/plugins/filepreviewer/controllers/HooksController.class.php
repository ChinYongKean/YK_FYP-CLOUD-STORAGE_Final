<?php

namespace Plugins\Filepreviewer\Controllers;

use App\Core\BaseController;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Models\File;
use App\Controllers\AccountController;

class HooksController extends BaseController
{

    public function adminPluginNav($params = null) {
        // output within the admin left-hand navigation
        $navigation = array(
            array('link_url' => '#', 'link_text' => 'File Previewer', 'link_key' => 'filepreviewer', 'icon_class' => 'fa fa-file', 'children' => array(
                    array('link_url' => 'admin/plugin/filepreviewer/settings', 'link_text' => 'Plugin Settings', 'link_key' => 'filepreviewer_plugin_settings'),
                )),
        );

        // return array
        return $navigation;
    }

    public function fileDownloadBottom($params = null) {
        // check for file object
        if (!isset($params['file'])) {
            // if no file found, redirect to home page
            return $this->redirect(WEB_ROOT);
        }

        // check whether we should actually show the download page or not
        $pluginObj = PluginHelper::getInstance('filepreviewer');
        $pluginDetails = PluginHelper::pluginSpecificConfiguration('filepreviewer');
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);
        if (isset($pluginSettings['show_file_details_outside_account']) && (int) $pluginSettings['show_file_details_outside_account'] === 0) {
            // only show for account owners
            $Auth = $this->getAuth();
            if ($Auth->loggedIn() === false || (int) $params['file']->userId !== (int) $Auth->id) {
                return false;
            }
        }

        // handle download request so preview page shows instead
        // call AccountController which should handle selected file
        $accountController = new AccountController();

        return $accountController->index((int) $params['file']->id);
    }

    public function fileDownloadTop($params = null) {
        $request = $this->getRequest();
        if ($request->query->has('idt')) {
            return $this->fileDownloadBottom($params);
        }
    }

    public function fileRemoveFile($params = null) {
        $ext = FileHelper::getImageExtensionsArr();

        $file = $params['file'];
        if (in_array(strtolower($params['file']->extension), $ext)) {
            // load plugin details
            $pluginObj = PluginHelper::getInstance('filepreviewer');

            // queue cache for delete
            $pluginObj->deleteImageCache((int) $params['file']->id);
        }
    }

    public function uploaderSuccessResultHtml($params = null) {
        $fileUpload = $params['fileUpload'];
        $previewImageUrlLarge = '';

        // load file
        $file = File::loadOneByShortUrl($fileUpload->short_url);
        if (is_object($file) && $file->isImage()) {
            // plugin settings
            $pluginDetails = PluginHelper::pluginSpecificConfiguration('filepreviewer');
            $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);
            if ((int) $pluginSettings['preview_image_show_thumb'] === 1) {
                // layout settings
                $thumbnailType = ThemeHelper::getConfigValue('thumbnail_type');

                $sizingMethod = 'middle';
                if ($thumbnailType == 'full') {
                    $sizingMethod = 'cropped';
                }
                $previewImageUrlLarge = FileHelper::getIconPreviewImageUrl($file, false, 48, false, 160, 134, $sizingMethod);
            }
        }

        $params['success_result_html'] = $previewImageUrlLarge;

        return $params;
    }

    public function fileIconPreviewImageUrl($params = null) {
        // ensure we have the file object
        $file = $params['file'];
        if(!is_object($file)) {
            return false;
        }

        $pluginObj = PluginHelper::getInstance('filepreviewer');
        $pluginDetails = PluginHelper::pluginSpecificConfiguration('filepreviewer');
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);

        // check this is an image
        if ($file->isImage()) {
            // only for active files
            if ($file->status == 'active') {
                $w = 99;
                if ((int) $params['width']) {
                    $w = (int) $params['width'];
                }

                $h = 60;
                if ((int) $params['height']) {
                    $h = (int) $params['height'];
                }

                // control for thumbnails
                $continue = true;
                if (($pluginSettings['preview_image_show_thumb'] == 0) && ($h <= 300)) {
                    $continue = false;
                }

                if ($continue) {
                    $m = 'middle';
                    if (trim($params['type'])) {
                        $m = trim($params['type']);
                    }

                    $o = 'jpg';
                    if (in_array($file->extension, $pluginObj->getAnimatedFileExtensions())) {
                        $o = 'gif';
                    }

                    $params['iconUrl'] = $pluginObj->createImageCacheUrl($file, $w, $h, $m, $o);

                    return $params;
                }
            }
        }
        // pdf
        elseif (in_array(strtolower($file->extension), array('pdf'))) {
            // only for active files
            if (isset($file->status) && ($file->status == 'active')) {
                // check for imagemagick
                if (($pluginSettings['preview_document_pdf_thumbs'] == 1) && (class_exists("imagick"))) {
                    $w = 99;
                    if ((int) $params['width']) {
                        $w = (int) $params['width'];
                    }

                    $h = 60;
                    if ((int) $params['height']) {
                        $h = (int) $params['height'];
                    }

                    $m = 'middle';

                    // url
                    $params['iconUrl'] = _CONFIG_SITE_PROTOCOL . '://' . FileHelper::getFileDomainAndPath($file->id, $file->getPrimaryServerId(), true, true) . '/cache/plugins/filepreviewer/' . $file->id . '/pdf/' . $w . 'x' . $h . '_' . $m . '_' . md5(json_encode($pluginSettings)) . '.jpg';

                    return $params;
                }
            }
        }

        return false;
    }

    /**
     * Used to schedule removal of cache files on 'direct' file servers. All other servers will have cache stored on the
     * local server.
     *
     * @param array|null $params
     * @return bool
     */
    public function processFileRemoval(array $params = null): bool {
        /*
         * available params
         * 
         * $params['actioned'];
         * $params['uploadServerDetails'];
         * $params['queueRow'];
         * $params['storageType'];
         * $params['filePath'];
         * */
        $storageType = $params['storageType'];
        $queueRow = $params['queueRow'];
        if ($storageType === 'direct' && (int) $queueRow['is_uploaded_file'] === 1) {
            // make sure we've scheduled the cache files for removal
            $pluginObj = PluginHelper::getInstance('filepreviewer');
            $pluginObj->deleteImageCache((int) $queueRow['file_id']);
        }

        return true;
    }

}
