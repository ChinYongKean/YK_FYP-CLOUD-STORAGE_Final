<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\Theme;

class ThemeHelper
{
    public static $themeConfigCache = null;

    static function themeEnabled($themeKey = '') {
        if (strlen($themeKey) == 0) {
            return false;
        }

        if (self::$themeConfigCache == null) {
            self::$themeConfigCache = self::loadThemeConfigurationFiles();
        }

        if (!isset(self::$themeConfigCache[$themeKey])) {
            return false;
        }

        $siteTheme = SITE_CONFIG_SITE_THEME;
        if ((isset($_SESSION['_current_theme'])) && (strlen($_SESSION['_current_theme']))) {
            $siteTheme = $_SESSION['_current_theme'];
        }

        if ($siteTheme != $themeKey) {
            return false;
        }

        return true;
    }

    static function clearCachedThemeSettings() {
        self::$themeConfigCache = null;
    }

    static function getCurrentThemeKey() {
        $siteTheme = SITE_CONFIG_SITE_THEME;
        if ((isset($_SESSION['_current_theme'])) && (strlen($_SESSION['_current_theme']))) {
            $siteTheme = $_SESSION['_current_theme'];
        }

        return $siteTheme;
    }

    static function getCurrentThemeName() {
        $theme = self::getLoadedInstance();

        return $theme->config['theme_name'];
    }

    static function themeSpecificConfiguration($themeKey = '') {
        if (self::themeEnabled($themeKey) == false) {
            return false;
        }

        return self::$themeConfigCache[$themeKey];
    }

    static function loadThemeConfigurationFiles($updateCache = false) {
        // try to load from cache
        if ($updateCache === false) {
            if (strlen(SITE_CONFIG_SYSTEM_THEME_CONFIG_CACHE)) {
                self::$themeConfigCache = @json_decode(SITE_CONFIG_SYSTEM_THEME_CONFIG_CACHE, true);
                if(json_last_error() === JSON_ERROR_NONE) {
                    return self::$themeConfigCache;
                }
            }
            else {
                $updateCache = true;
            }
        }

        $rs = [];

        // get active themes from the db
        $db = Database::getDatabase(true);
        $themes = $db->getRows('SELECT * '
                . 'FROM theme');

        // allow for first run
        if (!count($themes)) {
            self::registerThemes();
            $themes = $db->getRows('SELECT * '
                    . 'FROM theme');
        }

        if ($themes) {
            // use output buffering to ensure no random white space is added to the core
            ob_start();
            foreach ($themes AS $theme) {
                // load settings
                $themeConfig = self::getThemeConfigByFolderName($theme['folder_name']);
                if ($themeConfig !== false) {
                    $folderName = $themeConfig->getFolderName();
                    $rs[$folderName] = [];
                    $rs[$folderName]['data'] = $theme;
                    $rs[$folderName]['config'] = $themeConfig->getThemeConfig();
                }
            }
            // delete output buffer
            ob_end_clean();
        }

        // save cache
        if ($updateCache) {
            self::updateThemeConfigCache($rs);
            
            // purge application cache so routes are reloaded
            CacheHelper::removeCoreApplicationCache();
        }

        return $rs;
    }

    static function updateThemeConfigCache($dataArr) {
        // setup database
        $db = Database::getDatabase();

        // update cache
        $db->query('UPDATE site_config '
                . 'SET config_value = :config_key '
                . 'WHERE config_key = \'system_theme_config_cache\' '
                . 'LIMIT 1', array(
                    'config_key' => json_encode($dataArr),
                ));
        self::$themeConfigCache = $dataArr;
    }

    static function getInstance($themeKey = null) {
        if ($themeKey == null) {
            $themeKey = self::getCurrentThemeKey();
        }

        if (!isset(self::$themeConfigCache)) {
            self::$themeConfigCache = self::loadThemeConfigurationFiles();
        }

        $theme = self::$themeConfigCache[$themeKey];
        
        // make sure the class file exists
        $classPath = SITE_THEME_DIRECTORY_ROOT . $theme['data']['folder_name'] . '/Theme' . UCFirst(str_replace('_', '', $theme['data']['folder_name'])) . '.class.php';
        if(file_exists($classPath)) {
            // create plugin instance
            $themeClassName = '\themes\\' . $theme['data']['folder_name'] . '\Theme' . UCFirst(str_replace('_', '', $theme['data']['folder_name']));
            
            return new $themeClassName();
        }
        
        return false;
    }

    static function getLoadedInstance() {
        return self::getInstance(self::getCurrentThemeKey());
    }

    static function getThemeAdminNav($v2 = false) {
        // add any theme navigation
        $html = '';
        $currentThemeKey = self::getCurrentThemeKey();
        $themeConfig = self::themeSpecificConfiguration($currentThemeKey);
        $totalItems = 0;
        if ((self::themeEnabled($themeConfig['data']['folder_name']) == 1) && (isset($themeConfig['config']['admin_settings']['top_nav']))) {
            foreach ($themeConfig['config']['admin_settings']['top_nav'] AS $navItem) {
                $html .= '<li';
                if (ADMIN_SELECTED_PAGE == $navItem[0]['link_key'])
                    $html .= ' class="active"';
                $html .= '><a';
                if ($v2 === false) {
                    $html .= ' href="' . ($navItem[0]['link_url'] != '#' ? (SITE_THEME_WEB_ROOT . $themeConfig['config']['folder_name'] . '/' . $navItem[0]['link_url']) : ($navItem[0]['link_url'])) . '"';
                }
                $html .= '>';
                if ($v2 === true) {
                    $iconClass = 'fa fa-pencil-square-o';
                    if (isset($navItem[0]['icon_class'])) {
                        $iconClass = $navItem[0]['icon_class'];
                    }
                    $html .= '<i class="' . $iconClass . '"></i> ';
                }
                if ($v2 === false) {
                    $html .= '<span>';
                }
                $html .= AdminHelper::makeSafe(UCWords(strtolower(AdminHelper::t($navItem[0]['link_text'], $navItem[0]['link_text']))));
                if ($v2 === false) {
                    $html .= '</span>';
                }
                if (($v2 === true) && count($navItem > 1)) {
                    $html .= ' <span class="fa fa-chevron-down"></span>';
                }
                $html .= '</a>';

                if (count($navItem > 1)) {
                    $html .= '<ul';
                    if ($v2 === true) {
                        $html .= ' class="nav child_menu"';
                    }
                    $html .= '>';
                    unset($navItem[0]);
                    foreach ($navItem AS $navSubItem) {
                        $html .= '<li><a href="' . SITE_THEME_WEB_ROOT . $themeConfig['config']['folder_name'] . '/' . $navSubItem['link_url'] . '">';
                        if ($v2 === false) {
                            $html .= '<span>';
                        }
                        $html .= AdminHelper::makeSafe(UCWords(strtolower(AdminHelper::t($navSubItem['link_text'], $navSubItem['link_text']))));
                        if ($v2 === false) {
                            $html .= '</span>';
                        }
                        $html .= '</a>';

                        // add any sub items
                        if (isset($navSubItem['sub_nav'])) {
                            $html .= '<ul';
                            if ($v2 === true) {
                                $html .= ' class="nav child_menu"';
                            }
                            $html .= '>';
                            foreach ($navSubItem['sub_nav'] AS $subNavItem) {
                                $html .= '<li><a href="' . SITE_THEME_WEB_ROOT . $themeConfig['config']['folder_name'] . '/' . $subNavItem['link_url'] . '">';
                                if ($v2 === false) {
                                    $html .= '<span>';
                                }
                                $html .= AdminHelper::makeSafe(UCWords(strtolower(AdminHelper::t($subNavItem['link_text'], $subNavItem['link_text']))));
                                if ($v2 === false) {
                                    $html .= '</span>';
                                }
                                $html .= '</a></li>';
                            }
                            $html .= '</ul>';
                        }

                        $html .= '</li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '</li>';

                $totalItems++;
            }
        }

        if ($totalItems >= 4) {
            $html = '<li><a href="#"><span>More...</span><ul>' . $html . '</ul></li>';
        }

        return $html;
    }

    static function getThemeAdminNavDropdown() {
        // add any theme navigation
        $html = '';
        $currentThemeKey = self::getCurrentThemeKey();
        $themeConfig = self::themeSpecificConfiguration($currentThemeKey);
        if ((self::themeEnabled($themeConfig['data']['folder_name']) == 1) && (isset($themeConfig['config']['admin_settings']['top_nav']))) {
            foreach ($themeConfig['config']['admin_settings']['top_nav'] AS $navItem) {
                $html .= '<optgroup label="' . AdminHelper::makeSafe($navItem[0]['link_text']) . '">';
                if (count($navItem > 1)) {
                    unset($navItem[0]);
                    foreach ($navItem AS $navSubItem) {
                        $html .= '<option';
                        if ((isset($navSubItem['link_key'])) && defined('ADMIN_SELECTED_SUB_PAGE')) {
                            if (ADMIN_SELECTED_SUB_PAGE == $navSubItem['link_key']) {
                                $html .= ' selected';
                            }
                        }
                        $html .= ' value="' . SITE_THEME_WEB_ROOT . $themeConfig['config']['folder_name'] . '/' . $navSubItem['link_url'] . '">' . htmlentities(UCWords(strtolower(AdminHelper::t($navSubItem['link_text'], $navSubItem['link_text'])))) . '</option>';
                    }
                }
                $html .= '</optgroup>';
            }
        }

        return $html;
    }

    static function getConfigValue($configName) {
        $currentTheme = self::getCurrentThemeKey();
        $currentThemeConfig = self::themeSpecificConfiguration($currentTheme);
        if ($currentThemeConfig) {
            if (strlen($currentThemeConfig['data']['theme_settings'])) {
                $themeSettingsArr = json_decode($currentThemeConfig['data']['theme_settings'], true);
                if (is_array($themeSettingsArr)) {
                    return $themeSettingsArr[$configName];
                }
            }
        }

        return false;
    }

    static function setConfigValue($configName, $configValue) {
        $currentTheme = self::getCurrentThemeKey();
        $currentThemeConfig = self::themeSpecificConfiguration($currentTheme);
        if ($currentThemeConfig) {
            if (strlen($currentThemeConfig['data']['theme_settings'])) {
                $themeSettingsArr = json_decode($currentThemeConfig['data']['theme_settings'], true);
                if (is_array($themeSettingsArr)) {
                    $themeSettingsArr[$configName] = $configValue;
                }

                // save
                $db = Database::getDatabase();
                $db->query('UPDATE theme SET theme_settings = ' . $db->quote(json_encode($themeSettingsArr)) . ' WHERE folder_name = ' . $db->quote($currentTheme) . ' LIMIT 1');

                // clear cache
                ThemeHelper::clearCachedThemeSettings();
            }
        }

        return false;
    }

    static function getCurrentProductType() {
        // get produce type based on current theme
        $thisTheme = self::getCurrentThemeKey();
        $themeData = self::themeSpecificConfiguration($thisTheme);
        if ($themeData) {
            if ((isset($themeData['config'])) && (isset($themeData['config']['product']))) {
                if (strlen($themeData['config']['product'])) {
                    return $themeData['config']['product'];
                }
            }
        }

        return 'file_hosting';
    }

    static function getCurrentProductName() {
        // get produce type based on current theme
        $thisTheme = self::getCurrentThemeKey();
        $themeData = self::themeSpecificConfiguration($thisTheme);
        if ($themeData) {
            if ((isset($themeData['config'])) && (isset($themeData['config']['product_name']))) {
                if (strlen($themeData['config']['product_name'])) {
                    return $themeData['config']['product_name'];
                }
            }
        }

        return 'Yetishare';
    }

    static function getCurrentProductUrl() {
        // get produce type based on current theme
        $thisTheme = self::getCurrentThemeKey();
        $themeData = self::themeSpecificConfiguration($thisTheme);
        if ($themeData) {
            if ((isset($themeData['config'])) && (isset($themeData['config']['product_url']))) {
                if (strlen($themeData['config']['product_url'])) {
                    return $themeData['config']['product_url'];
                }
            }
        }

        return 'https://yetishare.com';
    }

    static function getAdminThemeCss() {
        $thisTheme = self::getCurrentThemeKey();
        $cssPath = SITE_THEME_DIRECTORY_ROOT . $thisTheme . '/assets/admin/css/styles.css';
        if (file_exists($cssPath)) {
            return SITE_THEME_WEB_ROOT . $thisTheme . '/assets/admin/css/styles.css';
        }

        return false;
    }

    public static function registerThemes() {
        // get database connection
        $db = Database::getDatabase();

        // scan plugin directory and make sure they are all listed within the database
        $directories = CoreHelper::getDirectoryList(SITE_THEME_DIRECTORY_ROOT);
        if (count($directories)) {
            foreach ($directories AS $directory) {
                // check the database to see if it already exists
                $found = $db->getValue("SELECT id "
                        . "FROM theme "
                        . "WHERE folder_name = :folder_name", array(
                            'folder_name' => $directory,
                        ));
                if ($found) {
                    continue;
                }

                // tidy directory for class
                $directory = strtolower($directory);
                $directory = ValidationHelper::removeInvalidCharacters($directory);

                $themePath = SITE_THEME_DIRECTORY_ROOT . $directory . '/';
                $themeClassFile = $themePath . 'Theme' . UCFirst(strtolower($directory)) . '.class.php';
                $themeClassName = '\\Themes\\'.UCFirst(strtolower($directory)).'\\Theme' . UCFirst(strtolower($directory));

                // make sure we have the main class file
                if (!file_exists($themeClassFile)) {
                    continue;
                }

                try {
                    // try to create an instance of the class
                    include_once($themeClassFile);
                    if (!class_exists($themeClassName)) {
                        continue;
                    }

                    $instance = new $themeClassName();
                    if (!$instance) {
                        continue;
                    }

                    // get plugin details
                    $themeDetails = $instance->getThemeDetails();

                    // insert new plugin into db
                    if ($themeDetails) {
                        // make sure we have the http at the start of the website
                        $website = $themeDetails['author_website'];
                        if (strlen($website)) {
                            if (substr($website, 0, 4) != 'http') {
                                $website = 'https://' . $website;
                            }
                        }

                        // if current theme set as active
                        $isActive = 0;
                        if ($themeDetails['folder_name'] == SITE_CONFIG_SITE_THEME) {
                            $isActive = 1;
                        }

                        // add to the database
                        $theme = Theme::create();
                        $theme->theme_name = $themeDetails['theme_name'];
                        $theme->folder_name = $themeDetails['folder_name'];
                        $theme->theme_description = $themeDetails['theme_description'];
                        $theme->author_name = $themeDetails['author_name'];
                        $theme->author_website = $website;
                        $theme->is_installed = $isActive;
                        $theme->save();
                    }
                }
                catch (Exception $e) {
                    continue;
                }
            }
        }

        return true;
    }

    public static function getThemeAdminNavV2() {
        return self::getThemeAdminNav(true);
    }
    
    public static function registerRoutes(\FastRoute\RouteCollector $r) {
        // instantiate the theme
        $themeInstance = self::getLoadedInstance();

        // register routes
        if(method_exists($themeInstance, 'registerRoutes')) {
            $themeInstance->registerRoutes($r);
        }
    }
    
    static function getThemeConfigByFolderName($themeFolderName) {
        // create plugin config instance
        $themeConfigClassName = '\themes\\' . $themeFolderName . '\ThemeConfig';
        if (!class_exists($themeConfigClassName)) {
            return false;
        }

        return new $themeConfigClassName();
    }

}
