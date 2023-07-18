<?php

namespace App\Helpers;

class AdvertisingHelper
{
    static function getVASTUrlForFile($file) {
        $vastUrl = '';
        if(defined('SITE_CONFIG_ADVERT_VIDEO_AD_TYPE') && SITE_CONFIG_ADVERT_VIDEO_AD_TYPE === 'vast') {
            // check for adult adverts first
            if((int)SITE_CONFIG_DIFFERENT_ADS_FOR_ADULT_CONTENT === 1 && $file->isAdult()) {
                if(strlen(SITE_CONFIG_ADULT_ADVERT_VIDEO_AD_VAST_URLS)) {
                    $adStr = str_replace(array("\n\r", "\r\n", "\r", "\n"), '|||', SITE_CONFIG_ADULT_ADVERT_VIDEO_AD_VAST_URLS);
                    $lines = explode('|||', trim($adStr));
                    if(count($lines)) {
                        $vastUrl = $lines[array_rand($lines)];
                    }
                }
            }

            // fall back to adult adverts
            if(strlen($vastUrl) === 0) {
                if(strlen(SITE_CONFIG_ADVERT_VIDEO_AD_VAST_URLS)) {
                    $adStr = str_replace(array("\n\r", "\r\n", "\r", "\n"), '|||', SITE_CONFIG_ADVERT_VIDEO_AD_VAST_URLS);
                    $lines = explode('|||', trim($adStr));
                    if(count($lines)) {
                        $vastUrl = $lines[array_rand($lines)];
                    }
                }
            }
        }
        
        return $vastUrl;
    }

}
