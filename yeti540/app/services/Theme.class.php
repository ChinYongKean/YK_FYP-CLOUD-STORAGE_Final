<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\CoreHelper;
use App\Helpers\ThemeHelper;

abstract class Theme
{

    abstract function getThemeDetails();

    public function install() {
        // get theme details
        $themeDetails = $this->getThemeDetails();

        // update reference in database
        $db = Database::getDatabase();
        $db->query('UPDATE theme '
                . 'SET is_installed = 1 '
                . 'WHERE folder_name = :folder_name', array(
            'folder_name' => $themeDetails['folder_name'],
                )
        );

        return true;
    }

    public function uninstall() {
        // get theme details
        $themeDetails = $this->getThemeDetails();

        // update reference in database
        $db = Database::getDatabase();
        $db->query('UPDATE theme '
                . 'SET is_installed = 0 '
                . 'WHERE folder_name = :folder_name', array(
            'folder_name' => $themeDetails['folder_name'],
                )
        );

        return true;
    }
    
    public function getConfigValue($item) {
        return ThemeHelper::getConfigValue($item);
    }
    
    public function getMainLogoUrl() {
        // see if the replaced logo exists
        $localCachePath = CACHE_DIRECTORY_ROOT . '/themes/' . $this->config['folder_name'] . '/logo.png';
        if (file_exists($localCachePath)) {
            return CACHE_WEB_ROOT . '/themes/' . $this->config['folder_name'] . '/logo.png';
        }

        return $this->getFallbackMainLogoUrl();
    }
    
    public function getInverseLogoUrl() {
        // see if the replaced logo exists
        $localCachePath = CACHE_DIRECTORY_ROOT . '/themes/' . $this->config['folder_name'] . '/logo_inverse.png';
        if (file_exists($localCachePath)) {
            return CACHE_WEB_ROOT . '/themes/' . $this->config['folder_name'] . '/logo_inverse.png';
        }

        return $this->getInverseFallbackLogoUrl();
    }
    
    public function getEmailLogoUrl() {
        return $this->getInverseLogoUrl();
    }

    public function getFallbackMainLogoUrl() {
        return CoreHelper::getCoreSitePath() . '/themes/' . $this->config['folder_name'] . '/assets/images/logo/logo.png';
    }

    public function getInverseFallbackLogoUrl() {
        return CoreHelper::getCoreSitePath() . '/themes/' . $this->config['folder_name'] . '/assets/images/logo/logo-whitebg.png';
    }

}
