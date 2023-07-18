<?php

namespace App\Helpers;

/**
 * Cache class for managing local cache in memory and on disk
 */
class CacheHelper
{
    static public $cacheArr = [];

    static function cacheExists($key) {
        if (isset(self::$cacheArr[$key])) {
            return true;
        }

        return false;
    }

    static function getCache($key) {
        if (self::cacheExists($key)) {
            $value = self::$cacheArr[$key]['value'];
            $type = self::$cacheArr[$key]['type'];
            if ($type == 'object' || $type == 'array') {
                return unserialize($value);
            }

            return $value;
        }

        return false;
    }

    static function setCache($key, $value) {
        self::$cacheArr[$key] = [];
        self::$cacheArr[$key]['type'] = gettype($value);

        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }

        self::$cacheArr[$key]['value'] = $value;

        return true;
    }

    static function clearCache($key) {
        self::$cacheArr[$key] = [];
        unset(self::$cacheArr[$key]);
    }

    static function clearAllCache() {
        self::$cacheArr = [];
    }

    static function saveCacheToFile($newFileName, $fileContentStr) {
        // save to file
        $fullCacheFilePath = CACHE_DIRECTORY_ROOT . DS . $newFileName;

        // make sure the folder path exists
        CoreHelper::checkCreateDirectory(dirname($fullCacheFilePath));

        // save cache
        $rs = file_put_contents($fullCacheFilePath, $fileContentStr);
        if (!$rs) {
            return false;
        }

        return $fullCacheFilePath;
    }

    /**
     * Cache file in the format js/filename.js. i.e. relative to the CACHE_DIRECTORY_ROOT root
     * 
     * @param type $cacheFile
     * @return boolean|string
     */
    static function getCacheFromFile(string $cacheFile, int $ttl = null) {
        // full path to file
        $fullCacheFilePath = CACHE_DIRECTORY_ROOT . DS . $cacheFile;

        // make sure the folder path exists
        if (!file_exists($fullCacheFilePath)) {
            return false;
        }

        // check whether the cache should be expired
        if(!is_null($ttl) && self::getCacheAgeInSeconds() > $ttl) {
            self::removeCacheFile($cacheFile);

            return false;
        }

        return file_get_contents($fullCacheFilePath);
    }

    static function checkCacheFileExists($cacheFile) {
        // full path to file
        $fullCacheFilePath = CACHE_DIRECTORY_ROOT . DS . $cacheFile;

        // make sure the folder path exists
        if (!file_exists($fullCacheFilePath)) {
            return false;
        }

        return true;
    }

    static function removeCacheFile($cacheFile) {
        if (self::checkCacheFileExists($cacheFile)) {
            // full path to file
            $fullCacheFilePath = CACHE_DIRECTORY_ROOT . DS . $cacheFile;

            // remove cache file
            @unlink($fullCacheFilePath);
        }
    }

    static function removeCacheSubFolder($cacheFolder) {
        // failsafe
        if (!strlen($cacheFolder)) {
            return false;
        }

        // full path to folder
        $fullCacheFilePath = CACHE_DIRECTORY_ROOT . DS . $cacheFolder;
        if(!is_dir($fullCacheFilePath)) {
            return false;
        }
        
        $listing = scandir($fullCacheFilePath);
        if (!$listing) {
            return false;
        }

        $files = array_diff($listing, array('.', '..'));
        foreach ($files as $file) {
            // failsafe
            if (!strlen($file)) {
                continue;
            }
            (is_dir($fullCacheFilePath . '/' . $file)) ? self::removeCacheSubFolder($cacheFolder . '/' . $file) : unlink($fullCacheFilePath . '/' . $file);
        }

        return rmdir(CACHE_DIRECTORY_ROOT . '/' . $cacheFolder);
    }

    static function isApplicationCachingEnabled() {
        return SITE_CONFIG_ENABLE_APPLICATION_CACHE === 'yes';
    }

    static function removeCoreApplicationCache() {
        // remove twig cache
        self::removeCacheSubFolder('twig');

        // remove route cache
        self::removeRouteCache();
        
        // logs
        LogHelper::info('Application cache purged.');
    }

    static function removeRouteCache() {
        // remove route cache
        self::removeCacheFile('route.cache');
    }

    static function getCacheModifiedTimestamp($cacheFile) {
        // full path to file
        $fullCacheFilePath = CACHE_DIRECTORY_ROOT . DS . $cacheFile;

        // make sure the path exists
        if (!file_exists($fullCacheFilePath)) {
            return false;
        }

        return filemtime($fullCacheFilePath);
    }

    static function getCacheAgeInSeconds($cacheFile) {
        if(!$modifiedTimestamp = self::getCacheModifiedTimestamp($cacheFile)) {
            return false;
        }

        return time()-$modifiedTimestamp;
    }

}
