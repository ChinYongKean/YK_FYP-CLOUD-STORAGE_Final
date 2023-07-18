<?php

// plugin namespace

namespace Plugins\Filepreviewer;

// core includes
use App\Core\Database;
use App\Models\File;
use App\Helpers\CacheHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileActionHelper;
use App\Helpers\FileHelper;
use App\Helpers\LogHelper;
use App\Helpers\PluginHelper;
use App\Services\Plugin;
use Plugins\Filepreviewer\PluginConfig;

class PluginFilepreviewer extends Plugin
{
    const HOLDING_CACHE_SIZE = 1100;
    const OG_THUMB_SIZE = 280;

    public $config = null;
    public $data = null;
    public $settings = null;
    public $cachePath = null;
    public $permCachePath = null;

    public function __construct() {
        // load plugin config
        $this->config = (new PluginConfig())->getPluginConfig();

        // setup database
        $db = Database::getDatabase();

        // cache result in local memory cache
        if (CacheHelper::cacheExists('PLUGIN_FILEPREVIEWER_CONSTRUCT_DATA') == false) {
            $data = $db->getRow('SELECT * '
                    . 'FROM plugin '
                    . 'WHERE folder_name = ' . $db->quote($this->config['folder_name']) . ' '
                    . 'LIMIT 1');
            CacheHelper::setCache('PLUGIN_FILEPREVIEWER_CONSTRUCT_DATA', $data);
        }

        $this->data = CacheHelper::getCache('PLUGIN_FILEPREVIEWER_CONSTRUCT_DATA');
        if ($this->data) {
            $this->settings = json_decode($this->data['plugin_settings'], true);
        }
        $this->cachePath = CACHE_DIRECTORY_ROOT . '/plugins/filepreviewer/';
        $this->permCachePath = CACHE_DIRECTORY_ROOT . '/perm_cache/filepreviewer/';
    }

    public function registerRoutes(\FastRoute\RouteCollector $r) {
        // register plugin routes
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/plugin/' . $this->config['folder_name'] . '/settings', '\plugins\\' . $this->config['folder_name'] . '\controllers\admin\PluginController/pluginSettings');
        $r->addRoute(['GET'], '/file/{shortUrl}[/{fileName}]', '\plugins\filepreviewer\controllers\FilepreviewerController/directFile');
        $r->addRoute(['GET'], '/cache/plugins/filepreviewer/{fileId:[0-9]+}/pdf/{width:[0-9]+}x{height:[0-9]+}_{method}_{md5PluginSettings}.jpg', '\plugins\filepreviewer\controllers\FilepreviewerController/pdfThumbnail');
        $r->addRoute(['GET'], '/cache/plugins/filepreviewer/{fileId:[0-9]+}/{uniqueHash}/{width:[0-9]+}x{height:[0-9]+}_{method}.{extension}', '\plugins\filepreviewer\controllers\FilepreviewerController/resizeImage');
        $r->addRoute(['GET'], '/document/embed/{shortUrl}/{width}x{height}[/{fileName}]', '\plugins\filepreviewer\controllers\FilepreviewerController/embedDocument');
        $r->addRoute(['GET'], '/video/embed/{shortUrl}/{width}x{height}[/{fileName}]', '\plugins\filepreviewer\controllers\FilepreviewerController/embedVideo');
        $r->addRoute(['GET'], '/video/subtitles/{fileName}', '\plugins\filepreviewer\controllers\FilepreviewerController/subtitleProxy');
        $r->addRoute(['GET'], '/audio/embed/{shortUrl}/{width}x{height}[/{fileName}]', '\plugins\filepreviewer\controllers\FilepreviewerController/embedAudio');
        $r->addRoute(['GET'], '/text/view/{downloadToken}/{shortUrl}', '\plugins\filepreviewer\controllers\FilepreviewerController/textView');

        // routes for legacy purposes
        $r->addRoute(['GET'], '/image/{shortUrl}[/{fileName}]', '\plugins\filepreviewer\controllers\FilepreviewerController/directFile');
        $r->addRoute(['GET'], '/plugins/docviewer/site/_embed.php', '\plugins\filepreviewer\controllers\FilepreviewerController/embedDocumentLegacy');
        $r->addRoute(['GET'], '/plugins/mediaplayer/site/_embed.php', '\plugins\filepreviewer\controllers\FilepreviewerController/embedVideoLegacy');
        $r->addRoute(['GET'], '/plugins/imageviewer/site/direct.php', '\plugins\filepreviewer\controllers\FilepreviewerController/directFileLegacy');
        $r->addRoute(['GET'], '/plugins/mediaplayer/site/direct.php', '\plugins\filepreviewer\controllers\FilepreviewerController/directFileLegacy');
        $r->addRoute(['GET'], '/plugins/imageviewer/site/thumb.php', '\plugins\filepreviewer\controllers\FilepreviewerController/resizeImageLegacy');
    }

    public function getPluginDetails() {
        return $this->config;
    }

    public function uninstall() {
        // setup database
        $db = Database::getDatabase();

        // remove plugin specific tables
        $sQL = 'DROP TABLE plugin_filepreviewer_background_thumb';
        $db->query($sQL);
        $sQL = 'DROP TABLE plugin_filepreviewer_meta';
        $db->query($sQL);
        $sQL = 'DROP TABLE plugin_filepreviewer_watermark';
        $db->query($sQL);

        return parent::uninstall();
    }

    function getHoldingCacheSize() {
        return self::HOLDING_CACHE_SIZE;
    }

    public function getOGThumbSize() {
        return self::OG_THUMB_SIZE;
    }

    public function deleteImagePreviewCache(int $fileId) {
        $this->deleteImageCache($fileId, true, false);
    }

    public function deleteImageCache(int $fileId, bool $instant = false, bool $doPermCache = true) {
        // get cache path
        $cacheFilePath = $this->cachePath . $fileId;
        if ($instant) {
            CacheHelper::removeCacheSubFolder('plugins/filepreviewer/' . $fileId);
            if ($doPermCache) {
                CacheHelper::removeCacheSubFolder('perm_cache/filepreviewer/' . $fileId);
            }
        }

        // queue cache for delete
        $file = File::loadOneById($fileId);
        $fileServerType = 'local';
        if ($file) {
            $fileServer = $file->loadFileServer();
            $fileServerType = $fileServer['serverType'];
        }

        // for 'direct' servers the cache is stored on them servers, otherwise it's on the main server. 'direct' server
        // cache won't be scheduled for removal on file delete, it's scheduled when the item in the file action queue is
        // processed since it's the only time we can check for existence
        if ($fileServerType === 'direct') {
            $serverId = FileHelper::getCurrentServerId();
        }
        else {
            $serverId = FileHelper::getDefaultLocalServerId();
        }

        if ($serverId) {
            // get all file listing
            if (file_exists($cacheFilePath)) {
                $filePaths = CoreHelper::getDirectoryList($cacheFilePath, null, true);
                if (is_array($filePaths)) {
                    if (count($filePaths) && count($filePaths) < 100) {
                        // reverse array so folders are done last
                        $filePaths = array_reverse($filePaths);
                        foreach ($filePaths AS $filePath) {
                            FileActionHelper::queueDeleteFile($serverId, $filePath, $fileId);
                        }
                    }
                }

                // add folder as well
                FileActionHelper::queueDeleteFile($serverId, $cacheFilePath, $fileId);
            }

            // remove any perm_cache
            if ($doPermCache) {
                if (file_exists($this->permCachePath . $fileId)) {
                    $filePaths = CoreHelper::getDirectoryList($this->permCachePath . $fileId, null, true);
                    if (is_array($filePaths)) {
                        if (count($filePaths) && count($filePaths) < 100) {
                            // reverse array so folders are done last
                            $filePaths = array_reverse($filePaths);
                            foreach ($filePaths AS $filePath) {
                                FileActionHelper::queueDeleteFile($serverId, $filePath, $fileId);
                            }
                        }
                    }

                    // add folder as well
                    FileActionHelper::queueDeleteFile($serverId, $this->permCachePath . $fileId, $fileId);
                }
            }
        }
    }

    public function isAnimatedGif($imageFileContents) {
        $str_loc = 0;
        $count = 0;
        while ($count < 2) { # There is no point in continuing after we find a 2nd frame
            $where1 = strpos($imageFileContents, "\x00\x21\xF9\x04", $str_loc);
            if ($where1 === false) {
                break;
            }
            else {
                $str_loc = $where1 + 1;
                $where2 = strpos($imageFileContents, "\x00\x2C", $str_loc);
                if ($where2 === false) {
                    break;
                }
                else {
                    if ($where1 + 8 == $where2) {
                        $count++;
                    }
                    $str_loc = $where2 + 1;
                }
            }
        }

        if ($count > 1) {
            return true;
        }

        return false;
    }

    public function createEmbedImageThumbUrl($file) {
        $o = 'jpg';
        if (in_array($file->extension, $this->getAnimatedFileExtensions())) {
            $o = 'gif';
        }

        return $this->createImageCacheUrl($file, $this->settings['thumb_size_w'], $this->settings['thumb_size_h'], $this->settings['thumb_resize_method'], $o);
    }

    public function createImageCacheUrl($file, $thumbnailWidth, $thumbnailHeight, $method = 'cropped', $extension = 'jpg') {
        $fileUniqueHash = $file->unique_hash;
        if (strlen($fileUniqueHash) == 0) {
            $fileUniqueHash = FileHelper::createUniqueFileHash($file->id);
        }

        return _CONFIG_SITE_PROTOCOL . '://' . FileHelper::getFileDomainAndPath($file->id, $file->getPrimaryServerId(), true, true) . '/' . CACHE_DIRECTORY_NAME . '/plugins/filepreviewer/' . $file->id . '/' . $fileUniqueHash . '/' . (int) $thumbnailWidth . 'x' . (int) $thumbnailHeight . '_' . $method . '.' . $extension;
    }

    public function createImagePHPUrl($fileId, $thumbnailWidth, $thumbnailHeight, $method = 'cropped', $serverId = null, $fileUniqueHash = null, $fileType = 'jpg') {
        return _CONFIG_SITE_PROTOCOL . '://' . FileHelper::getFileDomainAndPath($fileId, $serverId, true, true) . '/cache/plugins/filepreviewer/' . (int) $fileId . '/' . $fileUniqueHash . '/' . (int) $thumbnailWidth . 'x' . (int) $thumbnailHeight . '_' . $method . '.' . $fileType;
    }

    public function getCacheUrlPHPFromBrowserUrl($browserUrl) {
        // example url - cache/plugins/filepreviewer/2966/006c03e9e4dd38da330032daff94088bc44d1ff541b9573d114b86d755657b6a/190x190_maximum.jpg / .gif
        $w = 0;
        $h = 0;
        $m = 'cropped';
        $fileId = 0;
        $fileType = 'jpg';
        if (substr($browserUrl, strlen($browserUrl) - 3, 3) == 'gif') {
            $fileType = 'gif';
        }

        // break apart url and loop to find data
        $urlParts = explode('/', $browserUrl);
        $useNext = false;
        foreach ($urlParts AS $k => $urlPart) {
            if ($fileId != 0) {
                continue;
            }

            if ($useNext == true) {
                $fileId = $urlParts[$k];
                $fileUniqueHash = $urlParts[$k + 1];
                $fileName = $urlParts[$k + 2];
                $fileNameParts = preg_split("/(_|\.)/", $fileName);
                $sizeParts = explode('x', $fileNameParts[0]);
                $w = (int) $sizeParts[0];
                if (isset($sizeParts[1])) {
                    $h = (int) $sizeParts[1];
                }

                if (isset($fileNameParts[1])) {
                    $m = $fileNameParts[1];
                }
            }

            if ($urlPart == 'filepreviewer') {
                $useNext = true;
            }
        }

        // setup database
        $db = Database::getDatabase();

        // validate fileId
        $fileId = $db->getValue('SELECT id '
                . 'FROM file '
                . 'WHERE unique_hash = :unique_hash '
                . 'AND id = :id '
                . 'LIMIT 1', array(
            'id' => $fileId,
            'unique_hash' => $fileUniqueHash,
        ));

        return self::createImagePHPUrl($fileId, $w, $h, $m, null, $fileUniqueHash, $fileType);
    }

    // get smaller image to be used for thumbnail image creation, rather than original image, creates if not exists
    public function getHoldingCache($file) {
        $cacheFilePath = CACHE_DIRECTORY_ROOT . '/perm_cache/filepreviewer/';
        $cacheFilePath .= $file->id . '/' . $file->getFileHash() . '/';
        if (!file_exists($cacheFilePath)) {
            mkdir($cacheFilePath, 0777, true);
        }
        $cacheFileName = (int) $this->getHoldingCacheSize() . 'x' . (int) $this->getHoldingCacheSize() . '_cropped.jpg';
        $fullCachePath = $cacheFilePath . $cacheFileName;
        if (!file_exists($fullCachePath)) {
            // create original file
            $contents = $file->downloadInternally();

            // if using GD
            if ($this->getImageLibrary() === 'gd') {
                // load into memory
                $im = imagecreatefromstring($contents);
                if ($im === false) {
                    // failed reading image
                    return false;
                }

                // get image size
                $imageWidth = imagesx($im);
                $imageHeight = imagesy($im);

                $newwidth = (int) $this->getHoldingCacheSize();
                $newheight = ($imageHeight / $imageWidth) * $newwidth;
                if ($newwidth > $imageWidth) {
                    $newwidth = $imageWidth;
                }
                if ($newheight > $imageHeight) {
                    $newheight = $imageHeight;
                }
                $tmp = imagecreatetruecolor($newwidth, $newheight);
                $tmpH = imagesy($tmp);

                // set background to white for transparent images
                $back = imagecolorallocate($tmp, 255, 255, 255);
                imagefilledrectangle($tmp, 0, 0, $newwidth, $newheight, $back);

                // preserve transparency in gifs
                if ($file->extension == 'gif') {
                    imagecolortransparent($tmp, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
                }

                // image into the $tmp image
                imagecopyresampled($tmp, $im, 0, 0, 0, 0, $newwidth, $newheight, $imageWidth, $imageHeight);

                // save image
                ob_start();
                imagejpeg($tmp, null, 100);
                $imageContent = ob_get_clean();
                $rs = CacheHelper::saveCacheToFile('perm_cache/filepreviewer/' . $file->id . '/' . $cacheFileName, $imageContent);

                // cleanup memory
                imagedestroy($tmp);

                if (!$rs) {
                    return false;
                }
            }
            else {
                // @TODO - add support for ImageMagick
                return false;
            }
        }

        return file_get_contents($fullCachePath);
    }

    function _mirrorImage($imgsrc) {
        $width = imagesx($imgsrc);
        $height = imagesy($imgsrc);

        $src_x = $width - 1;
        $src_y = 0;
        $src_width = -$width;
        $src_height = $height;

        $imgdest = imagecreatetruecolor($width, $height);
        if (imagecopyresampled($imgdest, $imgsrc, 0, 0, $src_x, $src_y, $width, $height, $src_width, $src_height)) {
            return $imgdest;
        }

        return $imgsrc;
    }

    function autoRotateImage($file) {
        if (!function_exists('exif_read_data')) {
            return false;
        }

        // get image contents
        $contents = $file->downloadInternally();

        // temp save image in cache for exif function
        $imageFilename = 'plugins/filepreviewer/_tmp/' . md5(microtime() . $file->id) . '.' . $file->extension;
        $cachePath = CacheHelper::saveCacheToFile($imageFilename, $contents);

        // rotate
        $exif = exif_read_data($cachePath);
        if ($exif && isset($exif['Orientation'])) {
            $orientation = (int) $exif['Orientation'];
            if ($orientation != 1) {
                $img = imagecreatefromstring($contents);

                $mirror = false;
                $deg = 0;
                switch ($orientation) {
                    case 2:
                        $mirror = true;
                        break;
                    case 3:
                        $deg = 180;
                        break;
                    case 4:
                        $deg = 180;
                        $mirror = true;
                        break;
                    case 5:
                        $deg = 270;
                        $mirror = true;
                        break;
                    case 6:
                        $deg = 270;
                        break;
                    case 7:
                        $deg = 90;
                        $mirror = true;
                        break;
                    case 8:
                        $deg = 90;
                        break;
                }

                if ($deg) {
                    $img = imagerotate($img, $deg, 0);
                }

                if ($mirror) {
                    $img = $this->_mirrorImage($img);
                }

                // load image info memory
                ob_start();
                imagejpeg($img, null, 100);
                $imageContent = ob_get_clean();

                // update image
                $file->setFileContent($imageContent);

                // cleanup memory
                imagedestroy($img);
            }
        }

        // clear cached file
        CacheHelper::removeCacheFile($imageFilename);

        return true;
    }

    // TODO - test with FTP storage and S3
    function rotateImage($file, $direction = 'r') {
        // only for jpg, png, gif at the moment
        if (!in_array(strtolower($file->extension), array('jpg', 'jpeg', 'png', 'gif'))) {
            return false;
        }

        // block if the file is a duplicate, @TODO
        if ($file->isDuplicate()) {
            return false;
        }

        // setup database
        $db = Database::getDatabase();

        // get image contents
        $contents = $file->downloadInternally();

        // rotate
        $img = imagecreatefromstring($contents);
        if (!$img) {
            return false;
        }

        // figure out how to rotate
        $deg = 0;
        switch ($direction) {
            case 'l':
                $deg = 90;
                break;
            case 'r':
            default:
                $deg = 270;
                break;
        }

        if ($deg) {
            $img = imagerotate($img, $deg, 0);
        }

        // load image info memory
        ob_start();
        imagejpeg($img, null, 100);
        $imageContent = ob_get_clean();

        // update image
        $file->setFileContent($imageContent);

        // update new md5 file hash
        $fileHash = md5($imageContent);
        $db->query('UPDATE file_artifact '
                . 'SET file_hash = :file_hash '
                . 'WHERE file_id = :file_id '
                . 'AND file_artifact_type = "primary" '
                . 'LIMIT 1', [
                    'file_hash' => $fileHash,
                    'file_id' => $file->id,
        ]);

        // unique hash
        FileHelper::createUniqueFileHash($file->id);

        // cleanup memory
        imagedestroy($img);

        return true;
    }

    public function getDateTakenFromExifData($file, $exifData = array()) {
        $dateTime = '';
        if (count($exifData)) {
            if ((isset($exifData['DateTimeOriginal'])) && (strlen($exifData['DateTimeOriginal']))) {
                $dateTime = $exifData['DateTimeOriginal'];
            }
            elseif ((isset($exifData['CreateDate'])) && (strlen($exifData['CreateDate']))) {
                $dateTime = $exifData['CreateDate'];
            }
            elseif ((isset($exifData['DateTime'])) && (strlen($exifData['DateTime']))) {
                $dateTime = $exifData['DateTime'];
            }
            elseif ((isset($exifData['ModifyDate'])) && (strlen($exifData['ModifyDate']))) {
                $dateTime = $exifData['ModifyDate'];
            }

            // format date time
            if (strlen($dateTime)) {
                $datePieces = explode(' ', $dateTime);
                if (count($datePieces) == 2) {
                    return date('Y-m-d H:i:s', strtotime(str_replace(":", "-", $datePieces[0]) . " " . $datePieces[1]));
                }
            }
        }

        // fallback to todays date
        return date('Y-m-d H:i:s');
    }

    public function getThumbnailImageQuality() {
        if ((int) $this->settings['image_quality']) {
            return (int) $this->settings['image_quality'];
        }

        return 90;
    }

    public function formatExifName($str) {
        $str = str_replace('_', '', $str);

        return preg_replace('/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]/', ' $0', $str);
    }

    public function getDefaultImageWebPath() {
        return PLUGIN_WEB_ROOT . '/filepreviewer/assets/img/default-album.png';
    }

    public function getImageColors($fileId) {
        // get datbaase
        $db = Database::getDatabase();

        // get image colors
        $imageColorsArr = [];
        $imageColors = $db->getValue('SELECT image_colors '
                . 'FROM plugin_filepreviewer_meta '
                . 'WHERE file_id = ' . (int) $fileId . ' '
                . 'LIMIT 1');
        if (strlen($imageColors)) {
            $imageColorsArr = explode(',', $imageColors);
        }

        return $imageColorsArr;
    }

    // which PHP image handling library to use
    public function getImageLibrary() {
        if (isset($this->settings['image_library'])) {
            if (in_array($this->settings['image_library'], array('gd', 'imagemagick'))) {
                return $this->settings['image_library'];
            }
        }

        return 'gd';
    }

    public function calculateWatermarkPosition($positionStr, $rsImageW, $rsImageH, $watermarkImageW, $watermarkImageH, $xPadding = 0, $yPadding = 0) {
        // defaults
        $x = 0;
        $y = 0;
        switch ($positionStr) {
            case 'top left':
                $x = $xPadding;
                $y = $yPadding;
                break;
            case 'top':
                $x = (floor($rsImageW / 2) - floor($watermarkImageW / 2));
                $y = $yPadding;
                break;
            case 'top right':
                $x = $rsImageW - $watermarkImageW - $xPadding;
                $y = $yPadding;
                break;
            case 'right':
                $x = $rsImageW - $watermarkImageW - $xPadding;
                $y = floor($rsImageH / 2) - floor($watermarkImageH / 2);
                break;
            case 'bottom right':
                $x = $rsImageW - $watermarkImageW - $xPadding;
                $y = $rsImageH - $watermarkImageH - $yPadding;
                break;
            case 'bottom':
                $x = floor($rsImageW / 2) - floor($watermarkImageW / 2);
                $y = $rsImageH - $watermarkImageH - $yPadding;
                break;
            case 'bottom left':
                $x = $xPadding;
                $y = $rsImageH - $watermarkImageH - $yPadding;
                break;
            case 'left':
                $x = $xPadding;
                $y = floor($rsImageH / 2) - floor($watermarkImageH / 2);
                break;
            case 'center':
                $x = floor($rsImageW / 2) - floor($watermarkImageW / 2);
                $y = floor($rsImageH / 2) - floor($watermarkImageH / 2);
                break;
        }

        return array('x' => $x, 'y' => $y);
    }

    public function getAnimatedFileExtensions() {
        return array('gif', 'mng'); // png added to resolve transparency issues
    }

    public function setupImageMetaAndCache(File $file) {
        $rawData = [];

        // get image size
        $imageWidth = 0;
        $imageHeight = 0;

        // load plugin details
        $pluginObj = PluginHelper::getInstance('filepreviewer');
        $pluginDetails = PluginHelper::pluginSpecificConfiguration('filepreviewer');
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);

        // copy existing colors if file already exists
        $imageColors = [];
        $db = Database::getDatabase();
        $originalFileId = $db->getValue('SELECT f.id '
                . 'FROM file f '
                . 'LEFT JOIN file_artifact fa ON f.id = fa.file_id AND file_artifact_type = "primary" '
                . 'WHERE fa.file_hash = :file_hash '
                . 'AND fa.file_hash IS NOT NULL '
                . 'AND f.id != :id '
                . 'LIMIT 1', array(
            'file_hash' => $file->getPrimaryFileHash(),
            'id' => $file->id,
        ));
        if ($originalFileId) {
            $metaData = $db->getRow('SELECT * '
                    . 'FROM plugin_filepreviewer_meta '
                    . 'WHERE file_id = :file_id '
                    . 'LIMIT 1', array(
                'file_id' => (int) $originalFileId,
            ));
            if ($metaData) {
                $imageColors = explode(',', $metaData['image_colors']);
            }
        }

        // get file contents
        $contents = $file->downloadInternally();
        if (!$contents) {
            return false;
        }

        // which size the cache should be (same as the main image preview)
        $width = $this->getHoldingCacheSize();
        $height = $this->getHoldingCacheSize();
        $holdingCacheExists = false;
        if ((int) $pluginSettings['caching'] == 1) {
            // prepare cache path
            $cacheFilePath = CACHE_DIRECTORY_ROOT . '/perm_cache/filepreviewer/';
            $cacheFilePath .= $file->id . '/' . $file->getFileHash() . '/';
            if (!file_exists($cacheFilePath)) {
                mkdir($cacheFilePath, 0777, true);
            }
            $cacheFileName = $width . 'x' . $height . '_cropped.jpg';
            $fullCachePath = $cacheFilePath . $cacheFileName;
            if (file_exists($fullCachePath)) {
                $holdingCacheExists = true;
                return true;
            }
        }

        // create holding cache
        if ($pluginObj->getImageLibrary() == 'gd') {
            // get exif data
            if (function_exists('exif_read_data')) {
                $imageFilename = 'plugins/filepreviewer/_tmp/' . md5(microtime() . $file->id) . '.' . $file->extension;
                $cachePath = CacheHelper::saveCacheToFile($imageFilename, $contents);
                $exif = exif_read_data($cachePath, 0, true);
                if ($exif) {
                    foreach ($exif as $key => $section) {
                        // only log certain types of data
                        if (!in_array($key, array('IFD0', 'EXIF', 'COMMENT'))) {
                            continue;
                        }

                        foreach ($section as $name => $val) {
                            // stop really long data
                            if (count($rawData) > 200) {
                                continue;
                            }

                            // limit text length just encase someone if trying to feed it invalid data
                            if (is_string($val)) {
                                $rawData[substr($name, 0, 200)] = substr($val, 0, 500);
                            }
                        }
                    }
                }

                // clear cached file
                CacheHelper::removeCacheFile($imageFilename);
            }

            // create holding cache
            // load image 
            require_once(CORE_FRAMEWORK_LIBRARIES_ROOT . '/image_resizer/CustomSimpleImage.php');
            $img = new \CustomSimpleImage();
            $rs = $img->load_from_image_content($contents);

            // get image size
            $imageWidth = $img->get_width();
            $imageHeight = $img->get_height();

            // if holding cache does not exist
            if (($holdingCacheExists == false) && ($file->extension != 'png')) {
                $img->best_fit($width, $height);
                if ((int) $pluginObj->settings['auto_rotate'] == 1) {
                    $img->auto_orient();
                }

                // save image
                ob_start();
                $img->output('jpg', 100);
                $imageContent = ob_get_clean();
                file_put_contents($fullCachePath, $imageContent);
            }
        }
        else {
            // save image in tmp for Imagick
            $tmpImageFile = tempnam('/tmp', 'img-') . '.' . $file->extension;
            $tmpImage = fopen($tmpImageFile, 'w+');
            fwrite($tmpImage, $contents);
            fclose($tmpImage);

            // start Imagick
            try {
                $imagick = new \imagick($tmpImageFile);
            }
            catch (\ImagickException $e) {
                LogHelper::error('ImagickException: ' . $e->getMessage());

                return false;
            }

            // get image size
            $imageWidth = $imagick->getImageWidth();
            $imageHeight = $imagick->getImageHeight();

            // get exif data
            $exif = $imagick->getImageProperties("*");
            foreach ($exif as $name => $val) {
                // stop really long data
                if (count($rawData) > 200) {
                    continue;
                }

                // only log certain types of data
                if ((substr($name, 0, 5) != 'date:') && (substr($name, 0, 5) != 'exif:')) {
                    continue;
                }

                // tidy name
                $name = trim(substr($name, 5));

                // limit text length just encase someone if trying to feed it invalid data
                if (is_string($val)) {
                    $rawData[substr($name, 0, 200)] = substr($val, 0, 500);
                }
            }

            // create cache
            if (($holdingCacheExists == false) && ($file->extension != 'png')) {
                // set as jpg/gif
                if (in_array($file->extension, $pluginObj->getAnimatedFileExtensions())) {
                    // get first frame for static preview
                    $firstFrameImagick = $imagick->coalesceImages();
                    foreach ($firstFrameImagick as $k => $frame) {
                        if ($k == 0) {
                            $imagick = $frame;
                        }
                    }
                }
                else {
                    // set the background to white
                    $imagick->setImageBackgroundColor('white');

                    // flatten the image to remove layers and transparency
                    $imagick->mergeImageLayers(\imagick::LAYERMETHOD_FLATTEN);

                    // set as jpg
                    $imagick->setImageFormat('jpeg');
                }

                // set the background to white
                $imagick->setImageBackgroundColor('white');

                // set as jpg
                $imagick->setImageFormat('jpeg');

                // check width
                $w = $imagick->getImageWidth();
                if ($w > $width) {
                    $imagick->thumbnailImage($width, null, 0);
                }

                // now check height
                $h = $imagick->getImageHeight();
                if ($h > $height) {
                    $imagick->thumbnailImage(null, $height, 0);
                }

                // should we auto rotate the preview image
                if ((int) $pluginObj->settings['auto_rotate'] == 1) {
                    $orientation = $imagick->getImageOrientation();
                    switch ($orientation) {
                        case \imagick::ORIENTATION_BOTTOMRIGHT:
                            $imagick->rotateimage("#000", 180); // rotate 180 degrees 
                            break;

                        case \imagick::ORIENTATION_RIGHTTOP:
                            $imagick->rotateimage("#000", 90); // rotate 90 degrees CW 
                            break;

                        case \imagick::ORIENTATION_LEFTBOTTOM:
                            $imagick->rotateimage("#000", -90); // rotate 90 degrees CCW 
                            break;
                    }
                }

                // remove any meta data for privacy
                $imagick->stripImage();

                // save jpg for later in cache
                $imagick->writeImage($fullCachePath);
            }

            // EXTRACT THE COMMON IMAGE COLORS
            if (count($imageColors) == 0) {
                // reduce the amount of colors to 6
                $imagick->quantizeImage(6, \imagick::COLORSPACE_RGB, 0, true, false);

                // only save one pixel of each color
                $imagick->uniqueImageColors();

                // get ImagickPixelIterator
                $it = $imagick->getPixelIterator();

                // reset the iterator
                $it->resetIterator();

                // loop through rows
                while ($row = $it->getNextIteratorRow()) {
                    // loop through columns
                    foreach ($row as $pixel) {
                        // covert pixel to hex color
                        $color = $pixel->getColor();
                        $imageColors[] = '#' . strtoupper(sprintf('%02x', $color['r']) . sprintf('%02x', $color['g']) . sprintf('%02x', $color['b']));
                    }
                }
            }


            // tidy up
            $imagick->clear();
            @unlink($tmpImageFile);
        }

        // get date taken
        $dateTaken = $pluginObj->getDateTakenFromExifData($file, $rawData);

        // double check we don't have a record already
        $rs = $db->getRow('SELECT id '
                . 'FROM plugin_filepreviewer_meta '
                . 'WHERE file_id = ' . (int) $file->id . ' '
                . 'LIMIT 1');
        if (!$rs) {
            // store meta data
            $sQL = "INSERT INTO plugin_filepreviewer_meta "
                    . "(file_id, width, height, raw_data, date_taken, image_colors, image_bg_color) "
                    . "VALUES (:file_id, :width, :height, :raw_data, :date_taken, :image_colors, :image_bg_color)";
            $stmt = $db->query($sQL, array(
                'file_id' => $file->id,
                'width' => $imageWidth,
                'height' => $imageHeight,
                'raw_data' => json_encode($rawData),
                'date_taken' => $dateTaken,
                'image_colors' => implode(',', $imageColors),
                'image_bg_color' => '',
            ));
        }
    }

    public function getGeneralFileType(File $file) {
        $ext = strtolower($file->extension);
        if (($this->settings['enable_preview_image'] == 1) && (in_array($ext, explode(',', $this->settings['supported_image_types'])))) {
            return 'image';
        }

        if (($this->settings['enable_preview_document'] == 1) && (in_array($ext, explode(',', $this->settings['preview_document_ext'])))) {
            return 'document';
        }

        if (($this->settings['enable_preview_video'] == 1) && (in_array($ext, explode(',', $this->settings['preview_video_ext'])))) {
            return 'video';
        }

        if (($this->settings['enable_preview_audio'] == 1) && (in_array($ext, explode(',', $this->settings['preview_audio_ext'])))) {
            return 'audio';
        }

        if (($this->settings['enable_preview_text'] == 1) && (in_array($ext, explode(',', $this->settings['preview_text_ext'])))) {
            return 'text';
        }

        return 'download';
    }

    public function areDirectLinksAllowed() {
        if (isset($this->settings['allow_direct_links'])) {
            return (bool) $this->settings['allow_direct_links'];
        }

        return false;
    }

    public function getDocumentMaxDisplaySize() {
        return 52428800;
    }

    public function getTextMaxDisplaySize() {
        return 1024 * 1024 * 2;
    }

    public function getEmbededDocumentIframeCode($file) {
        $pluginObj = PluginHelper::getInstance('filepreviewer');

        return '<iframe src="' . WEB_ROOT . '/document/embed/' . $file->shortUrl . '/' . $pluginObj->settings['documents_embed_document_size_w'] . 'x' . $pluginObj->settings['documents_embed_document_size_h'] . '/' . $file->getSafeFilenameForUrl() . '" frameborder="0" scrolling="no" style="width: ' . $pluginObj->settings['documents_embed_document_size_w'] . 'px; height: ' . $pluginObj->settings['documents_embed_document_size_h'] . 'px; overflow: hidden;"></iframe>';
    }

    public function getEmbededVideoIframeCode($file) {
        $pluginObj = PluginHelper::getInstance('filepreviewer');

        return '<iframe src="' . WEB_ROOT . '/video/embed/' . $file->shortUrl . '/' . $pluginObj->settings['videos_embed_size_w'] . 'x' . $pluginObj->settings['videos_embed_size_h'] . '/' . $file->getSafeFilenameForUrl() . '" frameborder="0" scrolling="no" style="width: ' . $pluginObj->settings['videos_embed_size_w'] . 'px; height: ' . $pluginObj->settings['videos_embed_size_h'] . 'px; overflow: hidden;" allowfullscreen></iframe>';
    }

    public function getEmbededAudioIframeCode($file) {
        $pluginObj = PluginHelper::getInstance('filepreviewer');

        return '<iframe src="' . WEB_ROOT . '/audio/embed/' . $file->shortUrl . '/' . $pluginObj->settings['audio_embed_size_w'] . 'x' . $pluginObj->settings['audio_embed_size_h'] . '/' . $file->getSafeFilenameForUrl() . '" frameborder="0" scrolling="no" style="width: ' . $pluginObj->settings['audio_embed_size_w'] . 'px; height: ' . $pluginObj->settings['audio_embed_size_h'] . 'px; overflow: hidden;" allowfullscreen></iframe>';
    }

    public function getSubtitles(File $file) {
        // setup database
        $db = Database::getDatabase();

        // lookup for subtitle files
        $subTitleValidExt = array('vtt', 'srt', 'txt');
        $subtitleArr = [];
        $subtitles = $db->getRows('SELECT * '
                . 'FROM file '
                . 'WHERE status = "active" '
                . 'AND folderId ' . ((int) $file->folderId ? ('= ' . (int) $file->folderId) : 'IS NULL') . ' '
                . 'AND originalFilename LIKE "' . $db->escape(str_replace('.' . $file->extension, '', $file->originalFilename)) . '%" '
                . 'AND extension IN ("' . implode('","', $subTitleValidExt) . '") '
                . 'AND userId = ' . (int) $file->userId);
        if ($subtitles) {
            foreach ($subtitles AS $subtitle) {
                $subtitleFile = File::hydrateSingleRecord($subtitle);
                $originalBasePath = str_replace('.' . $file->extension, '', $file->originalFilename);
                $subtitleLabel = str_replace($originalBasePath, '', $subtitleFile->originalFilename);
                $subtitleLabel = trim(str_replace($subTitleValidExt, '', $subtitleLabel));

                // tidy the label
                $reps = array('.', '-', '_');
                foreach ($reps AS $rep) {
                    if (substr($subtitleLabel, strlen($subtitleLabel) - 1, 1) == $rep) {
                        $subtitleLabel = substr($subtitleLabel, 0, strlen($subtitleLabel) - 1);
                    }
                    if (substr($subtitleLabel, 0, 1) == $rep) {
                        $subtitleLabel = substr($subtitleLabel, 1, strlen($subtitleLabel) - 1);
                    }
                }
                if (strlen($subtitleLabel) == 0) {
                    $subtitleLabel = 'Eng';
                }

                // format for javascript
                $subtitleUrl = $subtitleFile->generateDirectDownloadUrlForMedia(false);

                // route subtitles via a proxy script if not on the current server,
                // to resolve CORS issues
                if ((int) $subtitleFile->getPrimaryServerId() !== FileHelper::getCurrentServerId()) {
                    $subtitleUrl = WEB_ROOT . '/video/subtitles/'.$subtitleFile->getSafeFilenameForUrl().'?b=' . CoreHelper::encryptValue($subtitleUrl);
                }

                // fix for "?rand=" code within Ultimate Video Player
                $subtitleUrl .= '&';

                $subtitleArr[] = array('source' => $subtitleUrl, 'label' => $subtitleLabel);
            }
        }

        return $subtitleArr;
    }
    
    public function getVideoPlayer() {
        if (isset($this->settings['preview_video_player'])) {
            return $this->settings['preview_video_player'];
        }

        return 'ultimate';
    }
    
    public function getAudioPlayer() {
        if (isset($this->settings['preview_audio_player'])) {
            return $this->settings['preview_audio_player'];
        }

        return 'ultimate';
    }

    public function getDocumentPreviewURL($file) {
        // default previewer is Google Docs
        $url = 'https://docs.google.com/gview?url=[FILE_URL]&embedded=true';

        // if the file is supported by Office Web Viewer, use that
        if(in_array($file->extension, [
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
        ])) {
            $url = 'https://view.officeapps.live.com/op/embed.aspx?src=[FILE_URL]';
        }

        // replace the [FILE_URL] placeholder and return
        return str_replace('[FILE_URL]', $file->generateDirectDownloadUrlForMedia(false), $url);
    }

}
