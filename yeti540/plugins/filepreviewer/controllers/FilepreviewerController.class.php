<?php

namespace Plugins\Filepreviewer\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Models\DownloadToken;
use App\Models\File;
use App\Models\User;
use App\Helpers\AdvertisingHelper;
use App\Helpers\AuthHelper;
use App\Helpers\CacheHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\LogHelper;
use App\Helpers\PluginHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UploaderHelper;
use \Highlight\Highlighter;

class FilepreviewerController extends BaseController
{

    public function directFileLegacy() {
        // get request
        $request = $this->getRequest();

        // forward to embedDocument
        return $this->directFile($request->query->get('s'));
    }

    public function directFile($shortUrl, $fileName = null) {
        // make sure direct file links are permitted
        $pluginObj = PluginHelper::getInstance('filepreviewer');
        if ($pluginObj->areDirectLinksAllowed() === false) {
            // not allowed
            return $this->render404();
        }

        // try to load the file object
        $file = null;
        if ($shortUrl) {
            $file = File::loadOneByShortUrl($shortUrl);
        }

        // load file details
        if (!$file) {
            // no file found
            return $this->render404();
        }

        // file must be active
        if ($file->status != 'active') {
            return $this->render404();
        }

        // check if file needs a password
        $folder = null;
        $Auth = AuthHelper::getAuth();
        if ($Auth->id != $file->userId) {
            if ($file->folderId !== null) {
                $folder = $file->getFolderData();
            }

            if (($folder) && (strlen($folder->accessPassword) > 0)) {
                // see if we have it in the session already
                $askPassword = true;
                if (!isset($_SESSION['folderPassword'])) {
                    $_SESSION['folderPassword'] = [];
                }
                elseif (isset($_SESSION['folderPassword'][$folder->id])) {
                    if ($_SESSION['folderPassword'][$folder->id] == $folder->accessPassword) {
                        $askPassword = false;
                    }
                }

                if ($askPassword) {
                    // redirect to main page which requests for a password
                    return $this->redirect(FileHelper::getFileUrl($file->id));
                }
            }
        }

        // check file permissions, allow owners, non user uploads and admin/mods
        if ($file->userId != null) {
            if ((($file->userId != $Auth->id) && ($Auth->level_id < 10))) {
                // if this is a private file
                if (CoreHelper::getOverallPublicStatus($file->userId, $file->folderId, $file->id) == false) {
                    $errorMsg = TranslateHelper::t("error_file_is_not_publicly_shared", "File is not publicly available.");
                    return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
                }
            }
        }

        // get download token and force download
        return $this->redirect($file->generateDirectDownloadUrlForMedia());
    }

    public function resizeImageLegacy() {
        // get request
        $request = $this->getRequest();

        // load file
        $file = FileHelper::loadByShortUrl($request->query->get('s'));
        if (!$file) {
            return $this->render404();
        }

        // get plugin instance
        $pluginObj = PluginHelper::getInstance('filepreviewer');

        // redirect to cache location
        return $this->redirect($pluginObj->createEmbedImageThumbUrl($file));
    }

    /**
     * This function gets hit when the cached version of the resized
     * image does not exist. It simply redirects to the PHP script which generates
     * it
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \ImagickException
     */
    public function resizeImage($fileId, $uniqueHash, $width, $height, $method, $extension) {
        // load file based on hash
        $file = File::loadOneByClause('unique_hash = :unique_hash '
                        . 'AND id = :id', array(
                    'unique_hash' => $uniqueHash,
                    'id' => $fileId,
        ));
        if (!$file) {
            return $this->render404();
        }

        // constants
        define('MAX_SIZE_NO_WATERMARK', 201);

        // validation
        $fileId = (int) $fileId;
        $width = (int) $width;
        $height = (int) $height;
        if (($method != 'padded') && ($method != 'middle') && ($method != 'maximum')) {
            $method = 'cropped';
        }

        // support only jpg or gif output
        if ($extension != 'gif') {
            $extension = 'jpg';
        }

        // validate width & height
        if ($width <= 0) {
            $width = 8;
        }
        if ($height <= 0) {
            $height = 8;
        }

        // load plugin details
        $pluginObj = PluginHelper::getInstance('filepreviewer');
        $db = Database::getDatabase();

        // memory saver
        if (($width > 1100) || ($height > 1100)) {
            // fail
            return $this->redirect($pluginObj->getDefaultImageWebPath());
        }

        // try to load the file object
        $file = null;
        if ($fileId) {
            $file = File::loadOneById($fileId);
        }

        // load file details
        if (!$file) {
            // fail
            return $this->redirect($pluginObj->getDefaultImageWebPath());
        }

        // make sure it's an image
        if (!$file->isImage()) {
            // fail
            return $this->redirect($pluginObj->getDefaultImageWebPath());
        }

        // check if file needs a password. Disabled due to the new hashing method
        // in the cache url
        $folder = null;
        if ($file->folderId !== null) {
            $folder = $file->getFolderData();
        }

        // check and show cache before loading environment
        $cacheFilePath = 'plugins/filepreviewer/';
        $cacheFilePath .= $fileId . '/';
        $cacheFilePath .= str_replace(array('.', '/'), '', $uniqueHash) . '/';
        if (!file_exists(CACHE_DIRECTORY_ROOT.'/'.$cacheFilePath)) {
            mkdir(CACHE_DIRECTORY_ROOT.'/'.$cacheFilePath, 0777, true);
        }
        $cacheFileName = (int) $width . 'x' . (int) $height . '_' . $method . '.' . $extension;
        $fullCachePath = $cacheFilePath . $cacheFileName;

        // check for cache
        if (!CacheHelper::checkCacheFileExists($fullCachePath)) {
            // load content from main image
            $contents = '';
            $imageExtension = $file->extension;

            // figure out if we're resizing to animated or static
            $animated = false;
            if ((in_array($file->extension, $pluginObj->getAnimatedFileExtensions())) && ((($width >= $pluginObj->getHoldingCacheSize()) || ($height >= $pluginObj->getHoldingCacheSize()))) && ($extension == 'gif')) {
                $animated = true;
            }

            // figure out if we're resizing to animated or static
            $processAsAnimated = false;
            if ((in_array($file->extension, $pluginObj->getAnimatedFileExtensions())) && ($extension == 'gif')) {
                // should we show all gifs as animated, including thumbnails? (option in plugin settings)
                if ($pluginObj->settings['animate_gif_thumbnails'] == 1) {
                    $processAsAnimated = true;
                }
                // only large images should be shown as animated
                elseif (($width >= $pluginObj->getHoldingCacheSize()) || ($height >= $pluginObj->getHoldingCacheSize())) {
                    $processAsAnimated = true;
                }
            }

            // create holding cache, if it doesn't already exist
            $pluginObj->setupImageMetaAndCache($file);

            // ignore for animated file types, i.e. use the original image
            if (!in_array($file->extension, $pluginObj->getAnimatedFileExtensions()) && ($file->extension != 'png')) {
                if (($width <= $pluginObj->getHoldingCacheSize()) && ($height <= $pluginObj->getHoldingCacheSize())) {
                    $contents = $pluginObj->getHoldingCache($file);
                    if ($contents) {
                        $imageExtension = $extension;
                    }
                }
            }

            if (!strlen($contents)) {
                // use original image
                $contents = $file->downloadInternally();
            }

            if (!strlen($contents)) {
                // fallback
                return $this->render404();
            }

            // if this is an animated gif just output it
            if ($animated == true) {
                return $this->renderFileContent($contents, [
                            'Expires' => '0',
                            'Pragma' => 'public',
                            'Content-Type' => 'image/' . $extension,
                ]);
            }

            // user watermark
            $userWatermarkPath = null;
            if ($folder) {
                if (($width >= MAX_SIZE_NO_WATERMARK) || ($height >= MAX_SIZE_NO_WATERMARK)) {
                    // user watermark
                    $watermarkCachePath = CACHE_DIRECTORY_ROOT . '/user/' . (int) $folder->userId . '/watermark/watermark_original.png';
                    if ((bool)$folder->watermarkPreviews && (file_exists($watermarkCachePath))) {
                        // load user
                        if ((int) $file->userId) {
                            $fileUser = User::loadOneById((int) $file->userId);
                            if ($fileUser) {
                                // set for later
                                $userWatermarkPath = $watermarkCachePath;
                            }
                        }
                    }
                }
            }

            // system wide watermark
            $systemWatermarkPath = null;
            if (($width >= MAX_SIZE_NO_WATERMARK) || ($height >= MAX_SIZE_NO_WATERMARK)) {
                if ((bool) $pluginObj->settings['watermark_enabled'] === true) {
                    // load watermark image
                    $watermark = $db->getRow("SELECT file_name, image_content "
                            . "FROM plugin_filepreviewer_watermark "
                            . "WHERE category = 'images' "
                            . "LIMIT 1");
                    if ($watermark) {
                        if (!empty($watermark['image_content'])) {
                            // save in tmp
                            $systemWatermarkPath = tempnam('/tmp', 'img-');
                            $tmp = fopen($systemWatermarkPath, 'w+');
                            fwrite($tmp, $watermark['image_content']);
                            fclose($tmp);
                        }
                    }
                }
            }

            // GD
            if ($pluginObj->getImageLibrary() == 'gd') {
                // load image 
                require_once(CORE_FRAMEWORK_LIBRARIES_ROOT . '/image_resizer/CustomSimpleImage.php');
                $img = new \CustomSimpleImage();
                $rs = $img->load_from_image_content($contents);
                if (!$rs) {
                    // fail
                    return $this->redirect($pluginObj->getDefaultImageWebPath());
                }

                if ($method == 'middle') {
                    $img->thumbnail($width, $height);
                }
                elseif ($method == 'padded') {
                    $img->padded_image($width, $height);
                }
                elseif ($method == 'cropped') {
                    $img->best_fit($width, $height);
                }
                else {
                    $img->resize($width, $height);
                }

                // user watermark
                if ($userWatermarkPath !== null) {
                    // apply watermark
                    $watermarkPadding = $fileUser->getProfileValue('watermarkPadding') ? $fileUser->getProfileValue('watermarkPadding') : 0;
                    $watermarkPosition = $fileUser->getProfileValue('watermarkPosition') ? $fileUser->getProfileValue('watermarkPosition') : 'bottom right';
                    $img->apply_watermark($userWatermarkPath, $watermarkPosition, $watermarkPadding, '1.0');
                }

                // system wide watermark
                if ($systemWatermarkPath !== null) {
                    // apply watermark
                    $watermarkPadding = $pluginObj->settings['watermark_padding'] ? $pluginObj->settings['watermark_padding'] : 0;
                    $watermarkPosition = $pluginObj->settings['watermark_position'] ? $pluginObj->settings['watermark_position'] : 'bottom right';
                    $img->apply_watermark($systemWatermarkPath, $watermarkPosition, $watermarkPadding, '1.0');

                    // clear wm cache
                    @unlink($systemWatermarkPath);
                }
            }
            // Imagemagick
            else {
                // save image in tmp for Imagick
                $tmpStorage = UploaderHelper::getLocalTempStorePath();
                $tmpImageFile = $tmpStorage . 'tmp-' . MD5(microtime().CoreHelper::generateRandomHash()) . '.' . $imageExtension;
                $tmpImage = fopen($tmpImageFile, 'w+');
                fwrite($tmpImage, $contents);
                fclose($tmpImage);

                // start Imagick
                try {
                    $imagick = new \imagick($tmpImageFile);
                }
                catch (\ImagickException $e) {
                    unlink($tmpImageFile);
                    LogHelper::error(print_r($e, true));

                    // fallback
                    return $this->render404();
                }
                catch (\Exception $e) {
                    unlink($tmpImageFile);
                    LogHelper::error(print_r($e, true));

                    // fallback
                    return $this->render404();
                }

                // set the background to white
                $imagick->setImageBackgroundColor('white');

                // set as jpg/gif
                if (!$processAsAnimated && in_array($file->extension, $pluginObj->getAnimatedFileExtensions())) {
                    // get first frame for static preview
                    $firstFrameImagick = $imagick->coalesceImages();
                    foreach ($firstFrameImagick as $k => $frame) {
                        if ($k == 0) {
                            $imagick = $frame;
                        }
                    }
                }
                elseif ($processAsAnimated == false) {
                    // flatten the image to remove layers and transparency
                    $imagick->mergeImageLayers(\imagick::LAYERMETHOD_FLATTEN);
                }

                // remove any meta data for privacy
                $imagick->stripImage();

                // set as jpg
                $imagick->setImageFormat($extension == 'jpg' ? 'jpeg' : $extension);
                $imagick->setCompressionQuality($pluginObj->getThumbnailImageQuality());

                // resize
                if ($method == 'middle') {
                    if ($processAsAnimated) {
                        $imagick = $imagick->coalesceImages();
                        foreach ($imagick as $frame) {
                            $frame->cropThumbnailImage($width, $height);
                        }
                        $imagick = $imagick->deconstructImages();
                    }
                    else {
                        $imagick->cropThumbnailImage($width, $height);
                    }
                }
                elseif ($method == 'padded') {
                    if ($processAsAnimated) {
                        $imagick = $imagick->coalesceImages();
                        foreach ($imagick as $frame) {
                            $frame->scaleImage($width, $height, true);
                            $frame->setImageBackgroundColor('white');
                            $w = $frame->getImageWidth();
                            $h = $frame->getImageHeight();
                            $frame->extentImage($width, $height, ($w - $width) / 2, ($h - $height) / 2);
                        }
                        $imagick = $imagick->deconstructImages();
                    }
                    else {
                        $imagick->scaleImage($width, $height, true);
                        $imagick->setImageBackgroundColor('white');
                        $w = $imagick->getImageWidth();
                        $h = $imagick->getImageHeight();
                        $imagick->extentImage($width, $height, ($w - $width) / 2, ($h - $height) / 2);
                    }
                }
                elseif ($method == 'cropped') {
                    // get image dimensions
                    $w = $imagick->getImageWidth();
                    $h = $imagick->getImageHeight();

                    if ($processAsAnimated) {
                        $imagick = $imagick->coalesceImages();
                        foreach ($imagick as $frame) {
                            // check width
                            if ($w > $width) {
                                $frame->thumbnailImage($width, null, 0);
                            }

                            // check height
                            if ($h > $height) {
                                $frame->thumbnailImage(null, $height, 0);
                            }
                        }
                        $imagick = $imagick->deconstructImages();
                    }
                    else {
                        // check width
                        if ($w > $width) {
                            $imagick->thumbnailImage($width, null, 0);
                        }

                        // check height
                        if ($h > $height) {
                            $imagick->thumbnailImage(null, $height, 0);
                        }
                    }
                }
                else {
                    if ($processAsAnimated) {
                        $imagick = $imagick->coalesceImages();
                        foreach ($imagick as $frame) {
                            $frame->scaleImage($width, $height, true);
                        }
                        $imagick = $imagick->deconstructImages();
                    }
                    else {
                        $imagick->scaleImage($width, $height, true);
                    }
                }

                // user watermark
                if ($userWatermarkPath !== null && $processAsAnimated == false) {
                    // open the watermark
                    $watermark = new \imagick();
                    $watermark->readImage($userWatermarkPath);

                    // calculate watermark positions
                    $posArr = $pluginObj->calculateWatermarkPosition($fileUser->getProfileValue('watermarkPosition'), $imagick->getImageWidth(), $imagick->getImageHeight(), $watermark->getImageWidth(), $watermark->getImageHeight(), (int) $fileUser->getProfileValue('watermarkPadding'), (int) $fileUser->getProfileValue('watermarkPadding'));

                    // apply watermark
                    $imagick->compositeImage($watermark, \imagick::COMPOSITE_OVER, $posArr['x'], $posArr['y']);
                }

                // add on the watermark after resizing
                if ($systemWatermarkPath !== null && $processAsAnimated == false) {
                    // open the watermark
                    $watermark = new \imagick();
                    $watermark->readImage($systemWatermarkPath);

                    // calculate watermark positions
                    $posArr = $pluginObj->calculateWatermarkPosition($pluginObj->settings['watermark_position'], $imagick->getImageWidth(), $imagick->getImageHeight(), $watermark->getImageWidth(), $watermark->getImageHeight(), (int) $pluginObj->settings['watermark_padding'], (int) $pluginObj->settings['watermark_padding']);

                    // apply watermark
                    $imagick->compositeImage($watermark, \imagick::COMPOSITE_OVER, $posArr['x'], $posArr['y']);

                    // clear wm cache
                    @unlink($systemWatermarkPath);
                }
            }

            $rs = false;

            // save image
            if ($pluginObj->getImageLibrary() == 'gd') {
                ob_start();
                $img->output($extension, $pluginObj->getThumbnailImageQuality());
                $imageContent = ob_get_clean();
                $rs = CacheHelper::saveCacheToFile('plugins/filepreviewer/' . $fileId . '/' . ($uniqueHash != null ? ($uniqueHash . '/') : '') . $cacheFileName, $imageContent);
            }
            else {
                // make sure the folder path exists
                CoreHelper::checkCreateDirectory(dirname(CACHE_DIRECTORY_ROOT . '/' . $fullCachePath));
                if ($processAsAnimated) {
                    $rs = $imagick->writeImages(CACHE_DIRECTORY_ROOT . '/' . $fullCachePath);
                }
                else {
                    $rs = $imagick->writeImage(CACHE_DIRECTORY_ROOT . '/' . $fullCachePath);
                    if (!$rs) {
                        ob_start();
                        echo $imagick;
                        $imageContent = ob_get_clean();
                        $rs = CacheHelper::saveCacheToFile($fullCachePath, $imageContent);
                    }
                }
            }

            if (!$rs) {
                // failed saving cache (or caching disabled), just output
                if ($pluginObj->getImageLibrary() == 'gd') {
                    $img->output($extension, $pluginObj->getThumbnailImageQuality());
                }
                else {
                    header("Content-Type: image/jpg");
                    echo $imagick;

                    // tidy up
                    @unlink($tmpImageFile);
                }
                exit;
            }

            // tidy up
            if ($pluginObj->getImageLibrary() != 'gd') {
                @unlink($tmpImageFile);
            }
        }

        // output some headers
        header("Expires: 0");
        header("Pragma: public");
        header("Content-Type: image/" . $extension);

        return $this->renderFileContent(CacheHelper::getCacheFromFile('plugins/filepreviewer/' . $fileId . '/' . ($uniqueHash != null ? ($uniqueHash . '/') : '') . $cacheFileName), [
                    'Expires' => '0',
                    'Pragma' => 'public',
                    'Content-Type' => 'image/' . $extension,
        ]);
    }

    public function pdfThumbnail($fileId, $width, $height, $method, $md5PluginSettings) {
        // load reward details
        $pluginConfig = PluginHelper::pluginSpecificConfiguration('filepreviewer');
        $pluginSettings = json_decode($pluginConfig['data']['plugin_settings'], true);

        // validation
        $fileId = (int) $fileId;
        $width = (int) $width;
        $height = (int) $height;
        if (($method != 'padded') && ($method != 'middle')) {
            $method = 'cropped';
        }

        // prep fallback urls
        $fallbackImageLargeUrl = FileHelper::getIconPreviewImageUrlLarge(array('extension' => 'pdf'), true, false);
        $fallbackImageUrl = FileHelper::getIconPreviewImageUrl(array('extension' => 'pdf'), true, 512);

        // validate width & height
        if (($width == 0) || ($height == 0)) {
            return $this->redirect($fallbackImageLargeUrl);
        }

        // memory saver
        if (($width > 5000) || ($height > 5000)) {
            return $this->redirect($fallbackImageLargeUrl);
        }

        // check the pdf option is enabled
        if ((int) $pluginSettings['preview_document_pdf_thumbs'] == 0) {
            // failed reading image
            if (($width > 160) || ($height > 160)) {
                return $this->redirect($fallbackImageUrl);
            }
            else {
                return $this->redirect($fallbackImageLargeUrl);
            }
        }

        // check for imagick
        if (!class_exists("imagick")) {
            // failed reading image
            if (($width > 160) || ($height > 160)) {
                return $this->redirect($fallbackImageUrl);
            }
            else {
                return $this->redirect($fallbackImageLargeUrl);
            }
        }

        // try to load the file object
        $file = null;
        if ($fileId) {
            $file = File::loadOneById($fileId);
        }

        // load file details
        if (!$file) {
            // no file found
            return $this->redirect($fallbackImageLargeUrl);
        }

        // cache paths
        $cacheFilePath = CACHE_DIRECTORY_ROOT . '/plugins/filepreviewer/' . (int) $file->id . '/pdf/';
        $fullCachePath = null;
        if (!is_dir($cacheFilePath)) {
            @mkdir($cacheFilePath, 0777, true);
        }

        // create original image if we need to
        $originalCacheFileName = 'original_image.jpg';
        $originalCachePath = $cacheFilePath . $originalCacheFileName;
        if (!file_exists($originalCachePath)) {
            // get original pdf file
            if ($file->getPrimaryServerId() == FileHelper::getCurrentServerId()) {
                // local so use path
                $filePath = $file->getFullFilePath();
                $filePath .= '[0]';
            }
            else {
                // remote to use url
                $filePath = $file->generateDirectDownloadUrlForMedia();
            }

            // create and save screenshot of first page from pdf
            $im = new \imagick();
            $im->setResolution(200, 200);
            $im->readImage($filePath);
            $im->setimageformat("jpg");
            $im->mergeImageLayers(\imagick::LAYERMETHOD_FLATTEN);
            $im->setImageAlphaChannel(\imagick::VIRTUALPIXELMETHOD_WHITE);
            $im->writeimage($originalCachePath);
            $im->clear();
            $im->destroy();

            // try old method
            if (!file_exists($originalCachePath)) {
                $im = new \imagick();
                $im->setResolution(200, 200);
                $im->readImage($filePath . '[0]');
                $im->setimageformat("jpg");
                $im->mergeImageLayers(\imagick::LAYERMETHOD_FLATTEN);
                $im->setImageAlphaChannel(\imagick::VIRTUALPIXELMETHOD_WHITE);
                $im->writeimage($originalCachePath);
                $im->clear();
                $im->destroy();
            }
        }

        // make sure we have the original screenshot file
        if (!file_exists($originalCachePath)) {
            // failed reading image
            if (($width > 160) || ($height > 160)) {
                return $this->redirect($fallbackImageUrl);
            }
            else {
                return $this->redirect($fallbackImageLargeUrl);
            }
        }

        // create resized version
        $cacheFileName = (int) $width . 'x' . (int) $height . '_' . $method . '_' . md5(json_encode($pluginSettings)) . '.jpg';
        $fullCachePath = $cacheFilePath . $cacheFileName;

        // check for cache
        if (($fullCachePath == null) || (!file_exists($fullCachePath))) {
            header('Content-Type: image/jpeg');

            // load into memory
            $im = imagecreatefromjpeg($originalCachePath);
            if ($im === false) {
                // failed reading image
                if (($width > 160) || ($height > 160)) {
                    return $this->redirect($fallbackImageUrl);
                }
                else {
                    return $this->redirect($fallbackImageLargeUrl);
                }
            }

            // get image size
            $imageWidth = imagesx($im);
            $imageHeight = imagesy($im);

            $newwidth = (int) $width;
            $newheight = ($imageHeight / $imageWidth) * $newwidth;
            if ($newwidth > $imageWidth) {
                $newwidth = $imageWidth;
            }
            if ($newheight > $imageHeight) {
                $newheight = $imageHeight;
            }
            $tmp = imagecreatetruecolor($newwidth, $newheight);
            $tmpH = imagesy($tmp);

            // check height max
            if ($tmpH > (int) $height) {
                $newheight = (int) $height;
                $newwidth = ($imageWidth / $imageHeight) * $newheight;
                $tmp = imagecreatetruecolor($newwidth, $newheight);
            }

            // override method for small images
            if ($method == 'middle') {
                if ($width > $imageWidth) {
                    $method = 'padded';
                }
                elseif ($height > $imageHeight) {
                    $method = 'padded';
                }
            }

            if ($method == 'middle') {
                $tmp = imagecreatetruecolor($width, $height);

                $newwidth = (int) $width;
                $newheight = ($imageHeight / $imageWidth) * $newwidth;
                $destX = 0;
                $destY = 0;
                if ($newwidth > $imageWidth) {
                    $newwidth = $imageWidth;
                }
                if ($newheight > $imageHeight) {
                    $newheight = $imageHeight;
                }

                // calculate new x/y positions
                if ($newwidth > $width) {
                    $destX = floor(($width - $newwidth) / 2);
                }
                if ($newheight > $height) {
                    //$destY = floor(($height-$newheight)/2);
                    $destY = 0;
                }

                imagecopyresampled($tmp, $im, $destX, $destY, 0, 0, $newwidth, $newheight, $imageWidth, $imageHeight);
            }
            else {
                // this line actually does the image resizing, copying from the original
                // image into the $tmp image
                imagecopyresampled($tmp, $im, 0, 0, 0, 0, $newwidth, $newheight, $imageWidth, $imageHeight);
            }

            // add white padding
            if ($method == 'padded') {
                $w = $width;
                if ($w > $imageWidth) {
                    $w = $imageWidth;
                }
                $h = $height;
                if ($h > $imageHeight) {
                    $h = $imageHeight;
                }

                // create base image
                $bgImg = imagecreatetruecolor((int) $w, (int) $h);

                // set background white
                $background = imagecolorallocate($bgImg, 255, 255, 255);  // white
                //$background = imagecolorallocate($bgImg, 0, 0, 0);  // black

                imagefill($bgImg, 0, 0, $background);

                // add on the resized image
                imagecopyresampled($bgImg, $tmp, ((int) $w / 2) - ($newwidth / 2), ((int) $h / 2) - ($newheight / 2), 0, 0, $newwidth, $newheight, $newwidth, $newheight);

                // reassign variable so the image is output below
                imagedestroy($tmp);
                $tmp = $bgImg;
            }

            $rs = false;
            if ($fullCachePath != null) {
                // save image
                $rs = imagejpeg($tmp, $fullCachePath, 90);
            }

            if (!$rs) {
                // failed saving cache (or caching disabled), just output
                header('Content-Type: image/jpeg');
                imagejpeg($tmp, null, 90);
                exit;
            }

            // cleanup memory
            imagedestroy($tmp);
        }

        return $this->renderFileContent($fullCachePath, array(
                    'Content-Type' => 'image/jpeg',
                        )
        );
    }

    public function embedDocumentLegacy() {
        // get request
        $request = $this->getRequest();

        // forward to embedDocument
        return $this->embedDocument($request->query->get('u'), $request->query->get('w'), $request->query->get('h'));
    }

    public function embedDocument($shortUrl, $width, $height, $fileName = null) {
        // load plugin details
        $pluginObj = PluginHelper::getInstance('filepreviewer');
        $pluginDetails = PluginHelper::pluginSpecificConfiguration('filepreviewer');
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);

        // do not allow if embed options are disabled
        if ((int) $pluginSettings['documents_show_embedding'] == 0) {
            // embedding disabled
            return $this->render404();
        }

        // try to load the file object
        $file = FileHelper::loadByShortUrl($shortUrl);

        /* load file details */
        if (!$file) {
            // if no file found
            return $this->render404();
        }

        if ($file->getPrimaryFileSize() >= $pluginObj->getDocumentMaxDisplaySize()) {
            // file to big
            return $this->render404();
        }

        // embed size
        define("EMBED_WIDTH", $width ? $width : $pluginSettings['documents_embed_document_size_w']);
        define("EMBED_HEIGHT", $height ? $height : $pluginSettings['documents_embed_document_size_h']);

        // load available extensions
        $ext = explode(",", $pluginSettings['preview_document_ext']);

        // if this is a download request
        if (!in_array(strtolower($file->extension), $ext)) {
            // file not permitted
            return $this->render404();
        }

        // check file permissions, allow owners, non user uploads and admin/mods
        if ($file->userId != null) {
            if ((($file->userId != $Auth->id) && ($Auth->level_id < 10))) {
                // if this is a private file
                if (CoreHelper::getOverallPublicStatus($file->userId, $file->folderId, $file->id) == false) {
                    $errorMsg = TranslateHelper::t("error_file_is_not_publicly_shared", "File is not publicly available.");

                    return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
                }
            }
        }

        // load template
        return $this->render('embed_document.html', array(
                    'file' => $file,
                    'filePreviewerObj' => $pluginObj,
                        ), PLUGIN_DIRECTORY_ROOT . 'filepreviewer/views');
    }

    public function embedVideoLegacy() {
        // get request
        $request = $this->getRequest();

        // forward to embedDocument
        return $this->embedVideo($request->query->get('u'), $request->query->get('w'), $request->query->get('h'));
    }

    public function embedVideo($shortUrl, $width, $height, $fileName = null) {
        // load plugin details
        $pluginObj = PluginHelper::getInstance('filepreviewer');
        $pluginDetails = PluginHelper::pluginSpecificConfiguration('filepreviewer');
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);

        // do not allow if embed options are disabled
        if ((int) $pluginSettings['videos_show_embedding'] == 0) {
            // embedding disabled
            return $this->render404();
        }

        // try to load the file object
        $file = FileHelper::loadByShortUrl($shortUrl);

        /* load file details */
        if (!$file) {
            // if no file found
            return $this->render404();
        }

        // embed size
        $embedWidth = $width ? $width : $pluginSettings['videos_embed_size_w'];
        $embedHeight = $height ? $height : $pluginSettings['videos_embed_size_h'];

        // load available extensions
        $ext = explode(",", $pluginSettings['preview_video_ext']);

        // if this is a download request
        if (!in_array(strtolower($file->extension), $ext)) {
            // file not permitted
            return $this->render404();
        }

        // check file permissions, allow owners, non user uploads and admin/mods
        if ($file->userId != null) {
            if ((($file->userId != $Auth->id) && ($Auth->level_id < 10))) {
                // if this is a private file
                if (CoreHelper::getOverallPublicStatus($file->userId, $file->folderId, $file->id) == false) {
                    $errorMsg = TranslateHelper::t("error_file_is_not_publicly_shared", "File is not publicly available.");

                    return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
                }
            }
        }

        // source video poster
        $videoPosterUrl = '';
        if (PluginHelper::pluginEnabled('mediaconverter')) {
            $videoPosterUrl = FileHelper::getIconPreviewImageUrl($file, false, 160, false, 640, 320);
        }

        // PPD logs via rewards plugin
        $ackPercentage = 0;
        if (PluginHelper::pluginEnabled('rewards')) {
            $rewardsPluginDetails = PluginHelper::pluginSpecificConfiguration('rewards');
            $rewardsPluginSettings = json_decode($rewardsPluginDetails['data']['plugin_settings'], true);
            $ackPercentage = (int) $rewardsPluginSettings['ppd_media_percentage'];
        }
        $downloadUrlForMedia = $file->generateDirectDownloadUrlForMedia();
        
        // prepare video adverts
        $vastUrl = AdvertisingHelper::getVASTUrlForFile($file);

        // load template
        return $this->render('embed_video.html', array(
                    'file' => $file,
                    'downloadUrlForMedia' => $downloadUrlForMedia,
                    'downloadUrlForMediaBase64' => base64_encode($downloadUrlForMedia),
                    'videoThumbnail' => $videoPosterUrl,
                    'videoAutoPlay' => (int) $pluginSettings['videos_autoplay'] === 1 ? true : false,
                    'playerMaxWidth' => (int) $embedWidth,
                    'playerMaxHeight' => (int) $embedHeight,
                    'ackPercentage' => (int) $ackPercentage,
                    'videoPlayer' => $pluginObj->getVideoPlayer(),
                    'vastUrl' => $vastUrl,
                        ), PLUGIN_DIRECTORY_ROOT . 'filepreviewer/views');
    }

    public function embedAudio($shortUrl, $width, $height, $fileName = null) {
        // load plugin details
        $pluginObj = PluginHelper::getInstance('filepreviewer');
        $pluginDetails = PluginHelper::pluginSpecificConfiguration('filepreviewer');
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);

        // do not allow if embed options are disabled
        if ((int) $pluginSettings['audio_show_embedding'] == 0) {
            // embedding disabled
            return $this->render404();
        }

        // try to load the file object
        $file = FileHelper::loadByShortUrl($shortUrl);

        /* load file details */
        if (!$file) {
            // if no file found
            return $this->render404();
        }

        // embed size
        $embedWidth = $width ? $width : $pluginSettings['audio_embed_size_w'];
        $embedHeight = $height ? $height : $pluginSettings['audio_embed_size_h'];

        // load available extensions
        $ext = explode(",", $pluginSettings['preview_audio_ext']);

        // if this is a download request
        if (!in_array(strtolower($file->extension), $ext)) {
            // file not permitted
            return $this->render404();
        }

        // check file permissions, allow owners, non user uploads and admin/mods
        if ($file->userId != null) {
            if ((($file->userId != $Auth->id) && ($Auth->level_id < 10))) {
                // if this is a private file
                if (CoreHelper::getOverallPublicStatus($file->userId, $file->folderId, $file->id) == false) {
                    $errorMsg = TranslateHelper::t("error_file_is_not_publicly_shared", "File is not publicly available.");

                    return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($errorMsg));
                }
            }
        }

        // source video poster
        $videoPosterUrl = '';

        // load template
        return $this->render('embed_audio.html', array(
                    'file' => $file,
                    'downloadUrlForMedia' => $file->generateDirectDownloadUrlForMedia(),
                    'videoThumbnail' => $videoPosterUrl,
                    'videoAutoPlay' => (int) $pluginSettings['audio_autoplay'] === 1 ? true : false,
                    'playerMaxWidth' => (int) $embedWidth,
                    'playerMaxHeight' => (int) $embedHeight,
                    'audioPlayer' => $pluginObj->getAudioPlayer(),
                        ), PLUGIN_DIRECTORY_ROOT . 'filepreviewer/views');
    }

    public function textView($downloadToken, $shortUrl) {
        // load plugin details
        $pluginObj = PluginHelper::getInstance('filepreviewer');
        $pluginDetails = PluginHelper::pluginSpecificConfiguration('filepreviewer');
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);

        // do not allow if preview is disabled
        if ((int) $pluginSettings['enable_preview_text'] == 0) {
            // embedding disabled
            return $this->render404();
        }

        // try to load the file object
        $file = FileHelper::loadByShortUrl($shortUrl);

        /* load file details */
        if (!$file) {
            // if no file found
            return $this->render404();
        }

        // load available extensions
        $ext = explode(",", $pluginSettings['preview_text_ext']);

        // if this is a download request
        if (!in_array(strtolower($file->extension), $ext)) {
            // file not permitted
            return $this->render404();
        }

        // try to load based on token
        $checkToken = DownloadToken::loadOneByClause('file_id = :file_id AND token = :token', array(
                    'file_id' => $file->id,
                    'token' => $downloadToken,
        ));
        if (!$checkToken) {
            // file not permitted
            return $this->render404();
        }

        // get file content if less than 2MB
        $fileContent = '';
        if ($file->getPrimaryFileSize() < $pluginObj->getTextMaxDisplaySize()) {
            $fileContent = $file->downloadInternally($downloadToken);
        }

        // highlight if the option is enabled
        $hightlighted = false;
        if ((int) $pluginSettings['syntax_highlight_text'] === 1 && !empty($fileContent)) {
            $hl = new Highlighter();

            // attempt to highlight the text
            try {
                $highlighter = $hl->highlight($file->extension, $fileContent);
                if (strlen($highlighter->value)) {
                    $fileContent = '<pre><code class="hljs ' . $highlighter->language . '">' . $highlighter->value . '</code></pre>';
                    $hightlighted = true;
                }
            }
            // fallback on no highlighting
            catch (\DomainException $e) {
                // do nothing
            }
        }

        // load template
        return $this->render('view_text.html', array(
                    'file' => $file,
                    'fileContent' => $fileContent,
                    'hightlighted' => $hightlighted,
                        ), PLUGIN_DIRECTORY_ROOT . 'filepreviewer/views');
    }

    public function subtitleProxy($fileName) {
        // get request
        $request = $this->getRequest();
        $base64EncryptedUrl = $request->query->get('b');
        
        // decrypt url
        $sourceUrl = CoreHelper::decryptValue($base64EncryptedUrl);

        // get subtitle contents
        $subtitleContents = CoreHelper::getRemoteUrlContent($sourceUrl);
        if($subtitleContents === false) {
            return $this->render404(false);
        }
        
        // return contents of url
        return $this->renderContent($subtitleContents);
    }
}
