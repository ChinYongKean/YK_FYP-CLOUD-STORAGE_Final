<?php

namespace App\Services;

class GoogleTranslate
{
    private $apiKey = null;
    private $url = 'https://www.googleapis.com/language/translate/v2?key=[[[API_KEY]]]&q=[[[EN_TEXT]]]&source=en&target=[[[TO_LANGUAGE]]]';
    private $error = null;
    private $response = [];

    function __construct($toLanguageCode) {
        $this->apiKey = trim(SITE_CONFIG_GOOGLE_TRANSLATE_API_KEY);
        $this->url = str_replace("[[[API_KEY]]]", $this->apiKey, $this->url);
        $this->url = str_replace("[[[TO_LANGUAGE]]]", $toLanguageCode, $this->url);
    }

    function translate($enText) {
        if (strlen($this->apiKey) == 0) {
            $this->error = 'No Google Translate API key found within the admin, site settings. Please add this and try again.';

            return false;
        }

        // prepare url
        $url = str_replace("[[[EN_TEXT]]]", urlencode($enText), $this->url);

        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($handle);
        $responseDecoded = json_decode($response, true);
        $responseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        
        // store the response for later
        if(is_array($responseDecoded)) {
            $this->response = $responseDecoded;
        }

        if ($responseCode != 200) {
            $this->error = 'Fetching translation failed! Server response code:' . $responseCode . '<br/><br/>Error description: ' . $responseDecoded['error']['errors'][0]['message'];

            return false;
        }

        if (!isset($responseDecoded['data']['translations'][0]['translatedText'])) {
            $this->error = 'Failed getting translation. Debug:<br/><br/>' . print_r($responseDecoded, true);

            return false;
        }

        // success
        return $responseDecoded['data']['translations'][0]['translatedText'];
    }

    function getError() {
        return $this->error.'. Response: '.json_encode($this->response, true);
    }

    static function getAvailableLanguages() {
        $languages = array('af' => 'Afrikaans', 'sq' => 'Albanian', 'ar' => 'Arabic', 'hy' => 'Armenian', 'az' => 'Azerbaijani', 'eu' => 'Basque', 'be' => 'Belarusian', 'bn' => 'Bengali', 'bs' => 'Bosnian', 'bg' => 'Bulgarian', 'ca' => 'Catalan', 'ceb' => 'Cebuano', 'ny' => 'Chichewa', 'zh-CN' => 'Chinese Simplified', 'zh-TW' => 'Chinese Traditional', 'hr' => 'Croatian', 'cs' => 'Czech', 'da' => 'Danish', 'nl' => 'Dutch', 'en' => 'English', 'eo' => 'Esperanto', 'et' => 'Estonian', 'tl' => 'Filipino', 'fi' => 'Finnish', 'fr' => 'French', 'gl' => 'Galician', 'ka' => 'Georgian', 'de' => 'German', 'el' => 'Greek', 'gu' => 'Gujarati', 'ht' => 'Haitian Creole', 'ha' => 'Hausa', 'iw' => 'Hebrew', 'hi' => 'Hindi', 'hmn' => 'Hmong', 'hu' => 'Hungarian', 'is' => 'Icelandic', 'ig' => 'Igbo', 'id' => 'Indonesian', 'ga' => 'Irish', 'it' => 'Italian', 'ja' => 'Japanese', 'jw' => 'Javanese', 'kn' => 'Kannada', 'kk' => 'Kazakh', 'km' => 'Khmer', 'ko' => 'Korean', 'lo' => 'Lao', 'la' => 'Latin', 'lv' => 'Latvian', 'lt' => 'Lithuanian', 'mk' => 'Macedonian', 'mg' => 'Malagasy', 'ms' => 'Malay', 'ml' => 'Malayalam', 'mt' => 'Maltese', 'mi' => 'Maori', 'mr' => 'Marathi', 'mn' => 'Mongolian', 'my' => 'Myanmar (Burmese)', 'ne' => 'Nepali', 'no' => 'Norwegian', 'fa' => 'Persian', 'pl' => 'Polish', 'pt' => 'Portuguese', 'ma' => 'Punjabi', 'ro' => 'Romanian', 'ru' => 'Russian', 'sr' => 'Serbian', 'st' => 'Sesotho', 'si' => 'Sinhala', 'sk' => 'Slovak', 'sl' => 'Slovenian', 'so' => 'Somali', 'es' => 'Spanish', 'su' => 'Sudanese', 'sw' => 'Swahili', 'sv' => 'Swedish', 'tg' => 'Tajik', 'ta' => 'Tamil', 'te' => 'Telugu', 'th' => 'Thai', 'tr' => 'Turkish', 'uk' => 'Ukrainian', 'ur' => 'Urdu', 'uz' => 'Uzbek', 'vi' => 'Vietnamese', 'cy' => 'Welsh', 'yi' => 'Yiddish', 'yo' => 'Yoruba', 'zu' => 'Zulu');

        return $languages;
    }

}
