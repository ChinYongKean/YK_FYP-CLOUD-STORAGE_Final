<?php

namespace App\Helpers;

use App\Models\Language;

class LanguageHelper
{
    static function getActiveLanguages() {
        return Language::loadByClause('isActive = 1', array(), 'languageName ASC');
    }
    
    static function loadFlagFromLanguageName($languageName) {
        $language = Language::loadOneByClause('languageName = :languageName', array(
            'languageName' => $languageName,
        ));
        
        if($language) {
            return $language->flag;
        }
        
        return 'en';
    }
    
    static function getActiveLabel() {
        return (isset($_SESSION['_t']) && strlen($_SESSION['_t']))?$_SESSION['_t']:SITE_CONFIG_SITE_LANGUAGE;
    }
    
    static function getActiveFlag() {
        return (isset($_SESSION['_tFlag']) && strlen($_SESSION['_tFlag']))?$_SESSION['_tFlag']:'en';
    }
}
