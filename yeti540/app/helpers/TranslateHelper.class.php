<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\Language;
use App\Models\LanguageKey;
use App\Models\LanguageContent;

/**
 * main translate class
 */
class TranslateHelper
{
    private static $setupTranslations = false;
    public static $translations = [];

    // setup the initial translation constants
    static function setUpTranslationConstants() {
        if (self::$setupTranslations == true) {
            return true;
        }

        if (!defined("SITE_CONFIG_SITE_LANGUAGE")) {
            define("SITE_CONFIG_SITE_LANGUAGE", "English (en)");
        }

        $db = Database::getDatabase();
        if (isset($_SESSION['_t'])) {
            $languageId = (int) self::getCurrentLanguageId();
        }
        else {
            // load language based on site default
            $Language = Language::loadOne('languageName', SITE_CONFIG_SITE_LANGUAGE);
            $languageId = $Language->id;
        }

        if (!(int) $languageId) {
            return false;
        }

        self::updateAllLanguageContent($languageId);

        /* load in the content */
        $rows = $db->getRows("SELECT language_key.languageKey, language_content.content "
                . "FROM language_content "
                . "LEFT JOIN language_key ON language_content.languageKeyId = language_key.id "
                . "WHERE language_content.languageId = " . (int) $languageId . ' '
                . 'AND language_key.languageKey IS NOT NULL');
        if (count($rows)) {
            foreach ($rows AS $row) {
                $constantName = strtoupper(trim($row['languageKey']));
                if (!isset(self::$translations[$constantName])) {
                    self::$translations[$constantName] = $row['content'];
                }
            }
        }

        self::$setupTranslations = true;
    }

    /*
     * translation function for JS
     */

    static function generateJSLanguageCode() {
        self::setUpTranslationConstants();

        /* setup js */
        $js = [];
        $js[] = "/* translation function */";
        $js[] = "function t(key,fallback){ ";
        $js[] = "fallback = typeof(fallback)=='undefined'?key:fallback; ";
        $js[] = "l = {";

        /* load in the content */
        $ljs = [];
        foreach (self::$translations AS $k => $row) {
            $itemKey = strtolower(str_replace(array("\r", "\n"), "", $k));
            if (strlen($itemKey) == 0) {
                continue;
            }
            $ljs[] = "\"" . addslashes($itemKey) . "\":\"" . addslashes(str_replace(array("\r", "\n"), "", $row)) . "\"";
        }
        $js[] = implode(", ", $ljs);
        $js[] = "};";

        $js[] = "return typeof(l[key.toLowerCase()])!=='undefined'?l[key.toLowerCase()]:fallback;";
        $js[] = "}";
        return implode("\n", $js);
    }

    static function updateAllLanguageContent($languageId) {
        $db = Database::getDatabase();
        /* make sure we have all content records populated */
        $getMissingRows = $db->getRows("SELECT id, languageKey, defaultContent "
                . "FROM language_key "
                . "WHERE NOT EXISTS (SELECT 1 FROM language_content WHERE languageId = " . (int) $languageId . ")");
        if (is_array($getMissingRows) && count($getMissingRows)) {
            foreach ($getMissingRows AS $getMissingRow) {
                $languageContent = new LanguageContent();
                $languageContent->languageKeyId = $getMissingRow['id'];
                $languageContent->languageId = (int) $languageId;
                $languageContent->content = $getMissingRow['defaultContent'];
                $languageContent->save();
            }
        }
    }

    static function getTranslation($key, $defaultContent = null, $isAdminArea = 0, $replacements = array(), $overwriteDefault = false) {
        /* are we in language debug mode */
        if (SITE_CONFIG_LANGUAGE_SHOW_KEY == "key") {
            return $key;
        }

        // setup translations
        self::setUpTranslationConstants();

        /* return the language translation if we can find it */
        $constantName = strtoupper(trim($key));
        if (!isset(self::$translations[$constantName]) || ($overwriteDefault == true)) {
            if ($defaultContent !== null) {
                $db = Database::getDatabase();
                if (isset($_SESSION['_t'])) {
                    $languageId = (int) self::getCurrentLanguageId();
                }
                else {
                    $language = Language::loadOne('languageName', SITE_CONFIG_SITE_LANGUAGE);
                    $languageId = $language->id;
                }

                if (!(int) $languageId) {
                    return false;
                }

                // clear default value
                $db->query('DELETE FROM language_content '
                        . 'WHERE languageKeyId IN (SELECT id FROM language_key WHERE languageKey=' . $db->quote($key) . ')');
                $db->query('DELETE FROM language_key '
                        . 'WHERE languageKey=' . $db->quote($key) . ' '
                        . 'LIMIT 1');

                // insert default key value
                $languageKey = new LanguageKey();
                $languageKey->languageKey = $key;
                $languageKey->defaultContent = $defaultContent;
                $languageKey->isAdminArea = (int) $isAdminArea;
                $languageKey->save();

                $languageContent = new LanguageContent();
                $languageContent->languageKeyId = $languageKey->id;
                $languageContent->languageId = (int) $languageId;
                $languageContent->content = $defaultContent;
                $languageContent->save();

                // set constant
                self::$translations[strtoupper($key)] = $defaultContent;

                // do replacements
                if (count($replacements)) {
                    foreach ($replacements AS $k => $replacement) {
                        $defaultContent = str_replace('[[[' . strtoupper($k) . ']]]', $replacement, $defaultContent);
                    }
                }

                if (SITE_CONFIG_LANGUAGE_SHOW_KEY == "key title text") {
                    $defaultContent = self::addTitleText($defaultContent, strtoupper($key));
                }

                return $defaultContent;
            }

            return "<font style='color:red;'>SITE ERROR: MISSING TRANSLATION *** <strong>" . $key . "</strong> ***</font>";
        }

        // do replacements
        $text = self::$translations[$constantName];
        if (count($replacements)) {
            foreach ($replacements AS $k => $replacement) {
                $text = str_replace('[[[' . strtoupper($k) . ']]]', $replacement, $text);
            }
        }

        if (SITE_CONFIG_LANGUAGE_SHOW_KEY == "key title text") {
            $text = self::addTitleText($text, strtoupper($key));
        }

        return $text;
    }

    static function t($key, $defaultContent = '', $replacements = array(), $overwriteDefault = false) {
        // comment out this line to force default translations to be added to the db
        //$overwriteDefault = true;

        return self::getTranslation($key, $defaultContent, 0, $replacements, $overwriteDefault);
    }

    static function addTitleText($baseText, $titleText) {
        return '<span title="' . validation::safeOutputToScreen($titleText) . '">' . $baseText . '</span>';
    }

    static function extractTranslationsFromText($str) {
        $rs = [];
        $patterns = array('/\.\s*t\s*\(\s*\'(.*?)\'\s*\s*\)/i', "/\.\s*t\s*\(\s*\\\"(.*?)\\\"\s*\s*\)/i", '/\.\s*t\s*\(\s*\'(.*?)\'\s*\s*\,/i', "/\.\s*t\s*\(\s*\\\"(.*?)\\\"\s*\s*\,/i", '/\(\s*t\s*\(\s*\'(.*?)\'\s*\s*\)/i', "/\(\s*t\s*\(\s*\\\"(.*?)\\\"\s*\s*\)/i", '/\(\s*t\s*\(\s*\'(.*?)\'\s*\s*\,/i', "/\(\s*t\s*\(\s*\\\"(.*?)\\\"\s*\s*\,/i", '/ t\s*\(\s*\'(.*?)\'\s*\s*\)/i', "/ t\s*\(\s*\\\"(.*?)\\\"\s*\s*\)/i", '/ t\s*\(\s*\'(.*?)\'\s*\s*\,/i', "/ t\s*\(\s*\\\"(.*?)\\\"\s*\s*\,/i", '/\:\:t\s*\(\s*\'(.*?)\'\s*\s*\)/i', "/\:\:t\s*\(\s*\\\"(.*?)\\\"\s*\s*\)/i", '/\:\:t\s*\(\s*\'(.*?)\'\s*\s*\,/i', "/\:\:t\s*\(\s*\\\"(.*?)\\\"\s*\s*\,/i");
        foreach ($patterns AS $pattern) {
            preg_match_all($pattern, $str, $matches);
            if (count($matches)) {
                $funcCalls = $matches[1];
                if (count($funcCalls)) {
                    foreach ($funcCalls AS $funcCall) {
                        $funcCall = str_replace(array('\', \'', '","', '\',\'', '\',"', '",\'', '\', "', '", \''), '", "', $funcCall);
                        $funcCall = str_replace(array('\', $', '", $', '\',$', '",$'), '", "$', $funcCall);
                        $funcCall = str_replace(array('\', array', '", array'), '", "array', $funcCall);

                        $tmpExp = explode('", "', $funcCall);

                        $defaultContent = $tmpExp[1];
                        if (substr($defaultContent, 0, 1) == '$') {
                            continue;
                        }
                        if (strpos($tmpExp[0], '$') !== false) {
                            continue;
                        }

                        // if not set by previous pattern
                        $tmp = $tmpExp[0];
                        if ((!isset($rs[$tmp])) && (strlen($defaultContent) > 0)) {
                            $rs[$tmp] = (string) $defaultContent;
                        }
                    }
                }
            }
        }

        return $rs;
    }

    static function rebuildTranslationsFromCode() {
        // get database
        $db = Database::getDatabase();

        // prepare total file list
        $filesToCheck = [];

        // get all files with translations, start with the base
        $dirFiles = AdminHelper::getDirectoryList(SITE_TEMPLATES_PATH, null, true);
        if ($dirFiles) {
            foreach ($dirFiles AS $dirFile) {
                if (is_file($dirFile)) {
                    $filesToCheck[] = $dirFile;
                }
            }
        }

        // add sub folders
        $subFolders = array('app/controllers', 'app/helpers', 'app/models', 'app/services', 'app/views', PLUGIN_DIRECTORY_NAME);
        foreach ($subFolders AS $subFolder) {
            $dirFiles = AdminHelper::getDirectoryList(DOC_ROOT . '/' . $subFolder, null, true);
            $filesToCheck = array_merge($filesToCheck, $dirFiles);
        }

        // only keep .php or .twig files
        $total = count($filesToCheck);
        for ($i = 0; $i < $total; $i++) {
            $fileName = str_replace(DOC_ROOT, '', $filesToCheck[$i]);
            $fileName = strtolower($fileName);
            if ((strpos($fileName, '.php') == false) && (strpos($fileName, '.twig') == false)) {
                unset($filesToCheck[$i]);
            }
        }

        // loop files and pick up any translations
        $translations = [];
        foreach ($filesToCheck AS $filePath) {
            // get contents of file
            $fileContents = file_get_contents($filePath);
            $rs = self::extractTranslationsFromText($fileContents);
            if (count($rs)) {
                $shortFileName = str_replace(DOC_ROOT . '/', '', $filePath);
                foreach ($rs AS $k => $item) {
                    if (!isset($translations[$k])) {
                        $translations[$k] = array('default_content' => $item, 'file_source' => array($shortFileName));
                    }
                    else {
                        $translations[$k]['file_source'] = $shortFileName;
                    }
                }
            }
        }

        // loop translations and populate default translation content
        self::setUpTranslationConstants();
        $foundTotal = 0;
        $addedTotal = 0;
        $db->query('UPDATE language_key '
                . 'SET foundOnScan = 0');
        foreach ($translations AS $translationKey => $translation) {
            /* return the language translation if we can find it */
            $constantName = strtoupper($translationKey);
            if (!isset(self::$translations[$constantName])) {
                if (strlen($translation['default_content'])) {
                    // figure out if admin
                    $isAdminArea = 1;
                    foreach ($translation['file_source'] AS $fileSource) {
                        if (strpos($fileSource, ADMIN_FOLDER_NAME) === false) {
                            $isAdminArea = 0;
                        }
                    }

                    // insert default key value
                    $languageKey = new LanguageKey();
                    $languageKey->languageKey = $translationKey;
                    $languageKey->defaultContent = $translation['default_content'];
                    $languageKey->isAdminArea = (int) $isAdminArea;
                    $languageKey->foundOnScan = 1;
                    $languageKey->save();

                    $addedTotal++;

                    // set constant
                    self::$translations[strtoupper($translationKey)] = $translation['default_content'];
                }
            }
            else {
                $db->query('UPDATE language_key '
                        . 'SET foundOnScan = 1 '
                        . 'WHERE languageKey=' . $db->quote($translationKey) . ' '
                        . 'LIMIT 1');
            }

            $foundTotal++;
        }

        return array('foundTotal' => $foundTotal, 'addedTotal' => $addedTotal);
    }

    static function getCurrentLanguageId() {
        if (!defined('SITE_CURRENT_LANGUAGE_ID')) {
            // load language object based on user session
            $Language = Language::loadOne('languageName', $_SESSION['_t']);
            define('SITE_CURRENT_LANGUAGE_ID', $Language->id);
        }

        return (int) SITE_CURRENT_LANGUAGE_ID;
    }

}
