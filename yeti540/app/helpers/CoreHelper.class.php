<?php

namespace App\Helpers;

use App\Core\Framework;
use App\Core\Database;
use App\Models\File;
use App\Models\FileFolder;
use App\Models\User;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class CoreHelper
{

    static function getCoreSitePath() {
        if (!defined("_CONFIG_CORE_SITE_HOST_URL")) {
            return WEB_ROOT;
        }

        return _CONFIG_SITE_PROTOCOL . "://" . _CONFIG_CORE_SITE_FULL_URL;
    }

    static function mainSiteAccessOnly() {
        // make sure this is the main site, only uploads on the main site are permitted
        if (self::currentIsMainSite() == false) {
            self::redirect(_CONFIG_SITE_PROTOCOL . '://' . _CONFIG_CORE_SITE_HOST_URL);
        }
    }

    static function currentIsMainSite() {
        if (_CONFIG_SITE_HOST_URL != _CONFIG_CORE_SITE_HOST_URL) {
            return false;
        }

        return true;
    }

    // converts a date/timestamp into the specified format
    static function formatDate($date = null, $format = null) {
        if (is_null($format)) {
            if (defined('SITE_CONFIG_DATE_TIME_FORMAT')) {
                $format = SITE_CONFIG_DATE_TIME_FORMAT;
            }
            else {
                $format = 'Y-m-d H:i:s';
            }
        }

        if (is_null($date)) {
            return;
        }

        if ($date == '0000-00-00 00:00:00') {
            return;
        }

        // if $date contains only numbers, treat it as a timestamp
        if (ctype_digit($date) === true)
            return date($format, $date);
        else
            return date($format, strtotime($date));
    }

    // redirects user to $url
    static function redirect($url = null) {
        // if no url assume redirect to self
        if (is_null($url)) {
            $url = $_SERVER['PHP_SELF'];
        }

        // if no headers sent
        if (!headers_sent()) {
            header('location: ' . $url);
            exit;
        }

        // fallback to meta/javascript redirect
        echo '<script type="text/javascript">';
        echo 'window.location.href="' . $url . '";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
        echo '</noscript>';
        exit;
    }

    static function convertToBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    static function getPHPMaxUpload() {
        $postMaxSize = self::convertToBytes(ini_get('post_max_size'));
        $uploadMaxFilesize = self::convertToBytes(ini_get('upload_max_filesize'));
        if ($postMaxSize > $uploadMaxFilesize) {
            return $uploadMaxFilesize;
        }

        return $postMaxSize;
    }

    // grabs the contents of a remote URL. Can perform basic authentication if un/pw are provided.
    static function getRemoteUrlContent($url, $saveToFilePath = null, $username = null, $password = null) {
        // if we should save to file, make sure the folder exists
        if ($saveToFilePath !== null) {
            self::checkCreateDirectory(dirname($saveToFilePath));
        }

        // try downloading with curl
        if (function_exists('curl_init')) {
            $ch = curl_init();
            if (!is_null($username) && !is_null($password)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . base64_encode("$username:$password")));
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_HEADER, false);
            if ($saveToFilePath !== null) {
                $fp = fopen($saveToFilePath, 'w+');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_exec($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fp);
                if ($status !== 200) {
                    return false;
                }
                if (file_exists($saveToFilePath)) {
                    return true;
                }
            }
            else {
                $html = curl_exec($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($status === 200 && strlen($html)) {
                    return $html;
                }

                return false;
            }
        }

        if (ini_get('allow_url_fopen') == true) {
            if (!is_null($username) && !is_null($password)) {
                $url = str_replace("://", "://$username:$password@", $url);
            }
            $html = file_get_contents($url);
            if (strlen($html)) {
                if ($saveToFilePath !== null) {
                    return file_put_contents($saveToFilePath, $html);
                }

                return $html;
            }
        }

        // cannot open url, either install curl-php or set allow_url_fopen = true in php.ini
        return false;
    }

    static function checkCreateDirectory($folderPath) {
        // make sure the folder path exists
        if (!file_exists($folderPath)) {
            // create if not
            $rs = mkdir($folderPath, 0777, true);
            if (!$rs) {
                LogHelper::error('Failed creating storage folder, possibly a permissions problem. (' . dirname($folderPath) . ')');
                die('Failed creating cache storage folder, possibly a permissions problem. (' . dirname($folderPath) . ')');
            }
        }

        return true;
    }

    // returns the current date time in sql format
    static function sqlDateTime() {
        return date("Y-m-d H:i:s");
    }

    static function getDirectoryListing($path) {
        if (substr($path, strlen($path) - 1, 1) != '/') {
            $path .= '/';
        }

        $items = [];
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $items[] = $path . $entry;
                }
            }
            closedir($handle);
        }

        return $items;
    }

    static function getDirectoryList($directory, $extFilter = null, $recurr = false) {
        $rs = [];
        if ($handle = opendir($directory)) {
            // iterate over the directory entries
            while (false !== ($entry = readdir($handle))) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                if ($recurr == true) {
                    $rs[] = $directory . '/' . $entry;
                    if (is_dir($directory . '/' . $entry)) {

                        $rs2 = self::getDirectoryList($directory . '/' . $entry, $extFilter, $recurr);
                        if (count($rs2)) {
                            $rs = array_merge($rs, $rs2);
                        }
                    }
                }
                else {
                    if ($extFilter !== null) {
                        if (strlen($entry) < strlen($extFilter)) {
                            continue;
                        }

                        if (substr($entry, strlen($entry) - strlen($extFilter), strlen($extFilter)) != $extFilter) {
                            continue;
                        }
                    }


                    $rs[] = str_replace($directory, '', $entry);
                }
            }

            // close the directory
            closedir($handle);
        }

        return $rs;
    }

    static function convertDateToTimestamp($date, $format = 'Y-m-d H:i:s') {
        if (!ValidationHelper::validDate($date, $format)) {
            return false;
        }

        $d = \DateTime::createFromFormat($format, $date);

        return $d->getTimestamp();
    }

    // get users IP address, cloudflare & proxy nginx friendly
    static function getUsersIPAddress() {
        // cloudflare
        if ((isset($_SERVER['HTTP_CF_CONNECTING_IP'])) && strlen($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Nginx proxy to Apache users
        if ((isset($_SERVER['HTTP_X_REAL_IP'])) && strlen($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        // Apache reverse proxy
        if ((isset($_SERVER['HTTP_X_FORWARDED_FOR'])) && strlen($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // convert to an array as it can sometimes be a comma separated list
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

            // return first element
            return trim($arr[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    static function dateformatPhpToJqueryUi($php_format) {
        $SYMBOLS_MATCHING = array(
            // Day
            'd' => 'dd',
            'D' => 'D',
            'j' => 'd',
            'l' => 'DD',
            'N' => '',
            'S' => '',
            'w' => '',
            'z' => 'o',
            // Week
            'W' => '',
            // Month
            'F' => 'MM',
            'm' => 'mm',
            'M' => 'M',
            'n' => 'm',
            't' => '',
            // Year
            'L' => '',
            'o' => '',
            'Y' => 'yy',
            'y' => 'y',
            // Time
            'a' => '',
            'A' => '',
            'B' => '',
            'g' => '',
            'G' => '',
            'h' => '',
            'H' => '',
            'i' => '',
            's' => '',
            'u' => ''
        );
        $jqueryui_format = "";
        $escaping = false;
        for ($i = 0; $i < strlen($php_format); $i++) {
            $char = $php_format[$i];
            if ($char === '\\') { // PHP date format escaping character
                $i++;
                if ($escaping)
                    $jqueryui_format .= $php_format[$i];
                else
                    $jqueryui_format .= '\'' . $php_format[$i];
                $escaping = true;
            }
            else {
                if ($escaping) {
                    $jqueryui_format .= "'";
                    $escaping = false;
                }
                if (isset($SYMBOLS_MATCHING[$char]))
                    $jqueryui_format .= $SYMBOLS_MATCHING[$char];
                else
                    $jqueryui_format .= $char;
            }
        }
        return $jqueryui_format;
    }

    static function output404() {
        header("HTTP/1.0 404 Not Found");
        exit;
    }

    static function output401() {
        header('HTTP/1.0 401 Unauthorized');
        exit;
    }

    static function outputFailureImage() {
        $localFailureImage = DOC_ROOT . "/themes/" . ThemeHelper::getCurrentThemeKey() . "/images/trans_1x1.gif";
        header('Content-type: image/gif');
        echo file_get_contents($localFailureImage);
        die();
    }

    /**
     * Format bytes into human readable
     * 
     * @param integer $bytes
     * @param string $return
     * @return string
     */
    static function formatSize($bytes, $return = 'both', $topRounding = true, $roundingSize = 2) {
        $size = $bytes / 1024;
        $ext = 'KB';
        if ($size < 1024) {
            if ($topRounding == true) {
                $size = number_format($size, $roundingSize);
            }
            else {
                $size = substr(number_format($size, 3), 0, 4);
            }
            $ext = 'KB';
        }
        else {
            if ($size / 1024 < 1024) {
                if ($topRounding == true) {
                    $size = number_format($size / 1024, $roundingSize);
                }
                else {
                    $size = substr(number_format($size / 1024, 3), 0, 4);
                }
                $ext = 'MB';
            }
            else if ($size / 1024 / 1024 < 1024) {
                if ($topRounding == true) {
                    $size = number_format($size / 1024 / 1024, $roundingSize);
                }
                else {
                    $size = substr(number_format($size / 1024 / 1024, 3), 0, 4);
                }
                $ext = 'GB';
            }
            else if ($size / 1024 / 1024 / 1024 < 1024) {
                if ($topRounding == true) {
                    $size = number_format($size / 1024 / 1024 / 1024, $roundingSize);
                }
                else {
                    $size = substr(number_format($size / 1024 / 1024 / 1024, 3), 0, 4);
                }
                $ext = 'TB';
            }
        }
        // remove unneccessary zeros
        $size = str_replace(".00 ", " ", $size);
        $size = str_replace(",", "", $size);

        switch ($return) {
            case 'size':
                return $size;
            case 'ext':
                return $ext;
            default:
                return $size . ' ' . $ext;
        }
    }

    static function ensureSiteMatchesConfig() {
        // ignore if cli mode
        if ((defined('CLI_MODE')) && (CLI_MODE == true)) {
            return;
        }

        // get requesting host
        $siteHost = self::getHttpHost();

        // remove any ports
        $siteHostExp = explode(':', $siteHost);
        $configHostExp = explode(':', _CONFIG_SITE_HOST_URL);

        // redirect to config file version if not
        if (strtolower($siteHostExp[0]) != strtolower($configHostExp[0])) {
            self::redirect(_CONFIG_SITE_PROTOCOL . '://' . _CONFIG_SITE_FULL_URL);
        }
    }

    static function getHttpHost() {
        return $_SERVER['HTTP_HOST'];
    }

    static function flushOutput() {
        //ob_end_flush();
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        //ob_start();
    }

    static function calculateDownloadSpeedFormatted($filesize, $speed = 0) {
        if ($speed == 0) {
            // assume 5MB as an average
            $speed = 5242880;
        }

        $minutes = ceil($filesize / $speed);

        return self::secsToHumanReadable($minutes);
    }

    static function secsToHumanReadable($secs) {
        $units = array(
            "week" => 7 * 24 * 3600,
            "day" => 24 * 3600,
            "hour" => 3600,
            "minute" => 60,
            "second" => 1,
        );

        // specifically handle zero
        if ($secs == 0)
            return "0 " . TranslateHelper::t('seconds', 'seconds');

        $s = "";

        foreach ($units as $name => $divisor) {
            if ($quot = intval($secs / $divisor)) {
                $label = TranslateHelper::t($name, $name);
                if (abs($quot) > 1) {
                    $label = TranslateHelper::t($name . 's', $name . 's');
                }
                $s .= "$quot $label";
                $s .= " ";
                $secs -= $quot * $divisor;
            }
        }

        return substr($s, 0, -1);
    }

    static function getTmpFolderSize($formatted = false) {
        $bytes = disk_total_space(sys_get_temp_dir());
        if ($bytes == 0) {
            return 0;
        }

        if ($formatted == true) {
            return self::formatSize($bytes);
        }

        return $bytes;
    }

    static function checkMaintenanceMode($requestingPage = null) {
        // check for maintenance mode
        if (SITE_CONFIG_MAINTENANCE_MODE === 'yes') {
            // allow the maintenance page itself, so we don't get stuck in a loop
            $requestingPage = explode('/', $requestingPage)[0];
            if (!in_array($requestingPage, array('down_for_maintenance', ADMIN_FOLDER_NAME)) && !isset($_REQUEST['csaKey1'])) {
                // initialize current user
                $Auth = AuthHelper::getAuth();

                // allow admin users
                if ($Auth->level_id <= 2) {
                    CoreHelper::redirect(WEB_ROOT . '/down_for_maintenance');
                }
            }
        }
    }

    // sends an HTML formatted email
    static function sendHtmlEmail($to, $subject, $msg, $fromEmail = null, $plaintext = '', $debug = false, $fromName = null, $useTemplate = true, $replyToEmail = null) {
        if (!is_array($to)) {
            $to = array($to);
        }

        if ($fromEmail == null) {
            $fromEmail = SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM;
        }

        if ($fromName == null) {
            $fromName = SITE_CONFIG_SITE_NAME;
        }

        $headerStyling = '';
        $footerStyling = '';
        if (($useTemplate == true) && (SITE_CONFIG_EMAIL_TEMPLATE_ENABLED == 'yes')) {
            // email header
            if (strlen(SITE_CONFIG_EMAIL_TEMPLATE_HEADER)) {
                $headerStyling = SITE_CONFIG_EMAIL_TEMPLATE_HEADER;
            }

            // email footer
            if (strlen(SITE_CONFIG_EMAIL_TEMPLATE_FOOTER)) {
                $footerStyling = SITE_CONFIG_EMAIL_TEMPLATE_FOOTER;
            }
        }

        if (strlen($headerStyling) == 0) {
            $headerStyling = '<style type="text/css">';
            $headerStyling .= 'body { font: 11px Verdana,Geneva,Arial,Helvetica,sans-serif; }\n';
            $headerStyling .= '</style>';
        }

        // load current theme for logo replacements
        $themeObj = ThemeHelper::getLoadedInstance();

        // do template replacements
        $replacements = [];
        $replacements['SITE_NAME'] = SITE_CONFIG_SITE_NAME;
        $replacements['WEB_ROOT'] = WEB_ROOT;
        $replacements['DATE_NOW'] = date(SITE_CONFIG_DATE_FORMAT);
        $replacements['DATE_TIME_NOW'] = date(SITE_CONFIG_DATE_TIME_FORMAT);
        $replacements['SITE_IMAGE_PATH'] = SITE_IMAGE_PATH;
        $replacements['EMAIL_LOGO_URL'] = $themeObj->getEmailLogoUrl();
        if (strlen($headerStyling)) {
            foreach ($replacements AS $k => $v) {
                $headerStyling = str_replace('[[[' . $k . ']]]', $v, $headerStyling);
                $footerStyling = str_replace('[[[' . $k . ']]]', $v, $footerStyling);
            }
        }

        $msg = $headerStyling . $msg . $footerStyling;

        // send using smtp
        if ((SITE_CONFIG_EMAIL_METHOD == 'smtp') && (strlen(SITE_CONFIG_EMAIL_SMTP_HOST))) {
            $error = '';
            $mail = new PHPMailer();
            $body = $msg;
            $body = preg_replace("/[\\\]/", '', $body);

            $mail->IsSMTP();
            try {
                $mail->Host = SITE_CONFIG_EMAIL_SMTP_HOST;
                $mail->Timeout = 20;
                $mail->CharSet = PHPMailer::CHARSET_UTF8;
                $mail->SMTPSecure = ((SITE_CONFIG_EMAIL_SECURE_METHOD == 'none') ? '' : SITE_CONFIG_EMAIL_SECURE_METHOD);
                $mail->SMTPDebug = (int) $debug;
                $mail->SMTPAuth = (SITE_CONFIG_EMAIL_SMTP_REQUIRES_AUTH == 'yes') ? true : false;
                $mail->Host = SITE_CONFIG_EMAIL_SMTP_HOST;
                $mail->Port = SITE_CONFIG_EMAIL_SMTP_PORT;
                if (SITE_CONFIG_EMAIL_SMTP_REQUIRES_AUTH == 'yes') {
                    $mail->Username = SITE_CONFIG_EMAIL_SMTP_AUTH_USERNAME;
                    $mail->Password = SITE_CONFIG_EMAIL_SMTP_AUTH_PASSWORD;
                }

                $mail->AddReplyTo($replyToEmail ? $replyToEmail : $fromEmail, $fromName);
                $mail->SetFrom($fromEmail, $fromName);
                $mail->Subject = $subject;

                if (strlen($plaintext)) {
                    $mail->AltBody = $plaintext; // optional
                }

                $mail->MsgHTML($body);
                foreach ($to as $address) {
                    $mail->AddAddress($address);
                }
                $mail->Send();
            }
            catch (phpmailerException $e) {
                $error = $e->errorMessage();
            }
            catch (Exception $e) {
                $error = $e->getMessage();
            }

            if (strlen($error)) {
                if ($debug == true) {
                    echo $error;
                }
                return false;
            }

            return true;
        }

        // send using php mail
        foreach ($to as $address) {
            $boundary = uniqid(rand(), true);

            /* OLD EMAIL HANDLING
              $headers = "From: $fromEmail\n";
              $headers .= "MIME-Version: 1.0\n";
              $headers .= "Content-Type: multipart/alternative; boundary = $boundary\n";
              $headers .= "This is a MIME encoded message.\n\n";
              $headers .= "--$boundary\n" .
              "Content-Type: text/plain; charset=UTF-8\n" .
              "Content-Transfer-Encoding: base64\n\n";
              $headers .= chunk_split(base64_encode($plaintext));
              $headers .= "--$boundary\n" .
              "Content-Type: text/html; charset=UTF-8\n" .
              "Content-Transfer-Encoding: base64\n\n";
              $headers .= chunk_split(base64_encode($msg));
              $headers .= "--$boundary--\n";
              mail($address, $subject, '', $headers);
             */

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "From: " . $fromEmail . " \r\n";
            if ($replyToEmail) {
                $headers .= "Reply-To: " . $replyToEmail . "\r\n";
            }
            $headers .= "Content-Type: multipart/alternative;boundary=" . $boundary . "\r\n";

            // here is the content body
            $message = "This is a MIME encoded message.";
            $message .= "\r\n\r\n--" . $boundary . "\r\n";
            $message .= "Content-type: text/plain;charset=utf-8\r\n\r\n";

            // plain text body
            $message .= $plaintext;
            $message .= "\r\n\r\n--" . $boundary . "\r\n";
            $message .= "Content-type: text/html;charset=utf-8\r\n\r\n";

            // html body
            $message .= $msg;
            $message .= "\r\n\r\n--" . $boundary . "--";

            mail($address, $subject, $message, $headers);
        }
    }

    static function getReffererDomainOnly() {
        // the referer isn't always available
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        $fullRefererUrl = strtolower(trim($_SERVER['HTTP_REFERER']));
        $urlData = parse_url($fullRefererUrl);
        $host = $urlData['host'];
        if (isset($urlData['port'])) {
            if (($urlData['port'] != 80) && ((int) $urlData['port'] != 0)) {
                $host .= ':' . $urlData['port'];
            }
        }

        return $host;
    }

    static function allowCrossSiteAjax() {
        // for cross domain uploads
        $refDomain = self::getReffererDomainOnly();
        if (!$refDomain) {
            $refDomain = _CONFIG_SITE_PROTOCOL . '://' . _CONFIG_CORE_SITE_HOST_URL;
        }
        else {
            $refDomain = _CONFIG_SITE_PROTOCOL . "://" . str_replace(array("http://", "https://"), "", $refDomain);
        }

        header('Access-Control-Allow-Origin: ' . (($refDomain === false) ? WEB_ROOT : ($refDomain)));
        header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description');
        header('Access-Control-Allow-Credentials: true');
    }

    static function outputCaptcha() {
        if (SITE_CONFIG_CAPTCHA_TYPE == 'solvemedia') {
            include_once(CORE_FRAMEWORK_LIBRARIES_ROOT . '/solvemedia/solvemedialib.php');
            return solvemedia_get_html(SITE_CONFIG_CAPTCHA_SOLVEMEDIA_CHALLENGE_KEY, null, _CONFIG_SITE_PROTOCOL == 'https') . '<input type="hidden" value="1" name="g-recaptcha-response"/>';
        }
        elseif (SITE_CONFIG_CAPTCHA_TYPE == 'cryptoloot') {
            return '<script src="https://verifypow.com/lib/captcha.min.js" async></script><div class="CRLT-captcha" data-hashes="1024" data-key="' . SITE_CONFIG_CAPTCHA_CRYPTOLOOT_PUBLIC_KEY . '"	data-whitelabel="false"	data-disable-elements="input[type=submit]" data-callback="myCaptchaCallback"><em>Loading Captcha...<br/>If it doesn\'t load, please disable Adblock!</em></div>';
        }

        return '<div class="g-recaptcha" data-sitekey="' . SITE_CONFIG_CAPTCHA_PUBLIC_KEY . '"></div>
		<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl=en"></script>';
    }

    static function captchaCheck() {
        if (SITE_CONFIG_CAPTCHA_TYPE == 'solvemedia') {
            include_once(CORE_FRAMEWORK_LIBRARIES_ROOT . '/solvemedia/solvemedialib.php');
            $solvemedia_response = solvemedia_check_answer(SITE_CONFIG_CAPTCHA_SOLVEMEDIA_VER_KEY, $_SERVER["REMOTE_ADDR"], $_POST["adcopy_challenge"], $_POST["adcopy_response"], SITE_CONFIG_CAPTCHA_SOLVEMEDIA_HASH_KEY);
            if ($solvemedia_response->is_valid) {
                return true;
            }

            return false;
        }
        elseif (SITE_CONFIG_CAPTCHA_TYPE == 'cryptoloot') {
            if (!isset($_POST['CRLT-captcha-token']) || strlen($_POST['CRLT-captcha-token']) === 0) {
                return false;
            }

            $postData = [
                'secret' => SITE_CONFIG_CAPTCHA_CRYPTOLOOT_PRIVATE_KEY,
                'token' => $_POST['CRLT-captcha-token'],
                'hashes' => 1024
            ];

            $postContext = stream_context_create([
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($postData)
                ]
            ]);

            $url = 'https://api.crypto-loot.com/token/verify';
            $response = json_decode(file_get_contents($url, false, $postContext));

            if ($response && $response->success) {
                return true;
            }

            return false;
        }

        $url = "https://www.google.com/recaptcha/api/siteverify?secret=" . SITE_CONFIG_CAPTCHA_SECRET_KEY . "&response=" . $_REQUEST["g-recaptcha-response"];
        $data = self::getRemoteUrlContent($url);
        $res = json_decode($data, true);
        if ($res['success'] == 'true') {
            return true;
        }

        return false;
    }

    static function createUploadError($name, $msg) {
        // setup object for errors
        $fileUpload = new \stdClass();
        $fileUpload->size = 0;
        $fileUpload->type = '';
        $fileUpload->name = $name;
        $fileUpload->error = $msg;
        $fileUpload->error_result_html = UploaderHelper::generateErrorHtml($fileUpload);

        return json_encode(array($fileUpload));
    }

    static function timeToRelativeString($ts) {
        if (!ctype_digit($ts)) {
            $ts = strtotime($ts);
        }

        $diff = time() - $ts;
        if ($diff == 0) {
            return 'now';
        }
        elseif ($diff > 0) {
            $day_diff = floor($diff / 86400);
            if ($day_diff == 0) {
                if ($diff < 60)
                    return 'just now';
                if ($diff < 120)
                    return '1 minute ago';
                if ($diff < 3600)
                    return floor($diff / 60) . ' minutes ago';
                if ($diff < 7200)
                    return '1 hour ago';
                if ($diff < 86400)
                    return floor($diff / 3600) . ' hours ago';
            }
            if ($day_diff == 1)
                return 'yesterday';
            if ($day_diff < 7)
                return $day_diff . ' days ago';
            if ($day_diff < 31)
                return ceil($day_diff / 7) . ' weeks ago';
            if ($day_diff < 60)
                return 'last month';
            return date('F Y', $ts);
        }
    }

    // no longer used, in favour of cron tasks
    static function executeBatchTasks() {
        
    }

    // used for remote upload progress cross site
    static function removeSubDomain($domain) {
        // if IP just return it
        if (filter_var($domain, FILTER_VALIDATE_IP) === true) {
            return $domain;
        }

        $original = $domain = strtolower($domain);
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return $domain;
        }

        $arr = array_slice(array_filter(explode('.', $domain, 4), function ($value) {
                    return $value !== 'www';
                }), 0); //rebuild array indexes
        if (count($arr) == 0) {
            return $domain;
        }

        if (count($arr) > 2) {
            $count = count($arr);
            $_sub = explode('.', $count === 4 ? $arr[3] : $arr[2]);

            if (count($_sub) === 2) { // two level TLD
                $removed = array_shift($arr);
                if ($count === 4) // got a subdomain acting as a domain
                    $removed = array_shift($arr);
            }
            elseif (count($_sub) === 1) { // one level TLD
                $removed = array_shift($arr); //remove the subdomain             
                if (strlen($_sub[0]) === 2 && $count === 3) // TLD domain must be 2 letters
                    array_unshift($arr, $removed);
                else {
                    // non country TLD according to IANA
                    $tlds = array('aero', 'arpa', 'asia', 'biz', 'cat', 'com', 'coop', 'edu', 'gov', 'info', 'jobs', 'mil', 'mobi', 'museum', 'name', 'net', 'org', 'post', 'pro', 'tel', 'travel', 'xxx',);
                    if (count($arr) > 2 && in_array($_sub[0], $tlds) !== false) {//special TLD don't have a country
                        array_shift($arr);
                    }
                }
            }
            else { // more than 3 levels, something is wrong
                for ($i = count($_sub); $i > 1; $i--)
                    $removed = array_shift($arr);
            }
        }
        elseif (count($arr) === 2) {
            $arr0 = array_shift($arr);
            if (strpos(join('.', $arr), '.') === false && in_array($arr[0], array('localhost', 'test', 'invalid')) === false) { // not a reserved domain
                // seems invalid domain, restore it
                array_unshift($arr, $arr0);
            }
        }

        return join('.', $arr);
    }

    static function ifBrowserAllowsMultipleUploads() {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') || strpos($_SERVER['HTTP_USER_AGENT'], 'iPod') !== false) {
            if ((strpos($_SERVER['HTTP_USER_AGENT'], 'OS 8_0') !== false) || (strpos($_SERVER['HTTP_USER_AGENT'], 'OS 7_0') !== false)) {
                return true;
            }
        }
        return false;
    }

    static function detect32or64bit() {
        if (function_exists('decbin')) {
            return strlen(decbin(~0));
        }
        else {
            return 8 * PHP_INT_SIZE;
        }
    }

    static function convertStringDatePeriodToDays($periodStr = '1M') {
        // handle lifetime
        if($periodStr === 'LIFE') {
            return -1;
        }
        
        // calculate days
        $days = 0;
        $periodType = substr($periodStr, strlen($periodStr) - 1, 1);
        $periodValue = substr($periodStr, 0, strlen($periodStr) - 1);
        $daysVolume = 1;
        if ($periodType == 'M') {
            $daysVolume = 31;
        }
        elseif ($periodType == 'Y') {
            $daysVolume = 365;
        }
        $days = $periodValue * $daysVolume;

        return (int) $days;
    }

    static function getUsersAccountLockStatus($userId) {
        // safe function as built in caching
        $user = User::loadOneById($userId);
        if ((int) $user->accountLockStatus === 1) {
            return true;
        }

        return false;
    }

    static function generateSecurityLockHash() {
        return substr(md5(md5(uniqid(rand(1, 100000)) . microtime())), 0, 16);
    }

    static function generateRandomHash() {
        if (function_exists('random_bytes')) {
            return md5(bin2hex(random_bytes(32)));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return md5(bin2hex(openssl_random_pseudo_bytes(32)));
        }

        return md5(md5(microtime() . uniqid(rand(1, 99999999)) . microtime()));
    }

    // Gets the overall setting from the admin area 
    static function getOverallSitePrivacyStatus() {
        if (SITE_CONFIG_FORCE_FILES_PRIVATE == 'no') {
            return true;
        }
        return false;
    }

    // Gets the users file/folder status
    static function getUserPublicStatus($userId) {
        $user = User::loadOneById($userId);

        return $user->isPublic >= 1;
    }

    // Gets the users folder status, and all parent folders, 
    // Takes the permissions from the parent folder.
    static function getUserFoldersPublicStatus($folderId = null) {
        if (!$folderId) {
            return true;
        }
        $folderData = FileFolder::loadOneById($folderId);
        if ($folderData->parentId == NULL && $folderData->isPublic == 1) {
            return true;
        }
        if ($folderData->isPublic == 0) {
            return false;
        }
        $folders = FileFolderHelper::loadAllActiveByAccount($folderData->userId);

        // loop through each parent ID until we hit either a private folder or null
        $lookupArr = [];
        foreach ($folders AS $folder) {
            $id = $folder['id'];
            $lookupArr[$id] = array('p' => $folder['parentId'], 'pub' => $folder['isPublic']);
        }
        $failSafe = 0;
        $parentId = $folderData->parentId;

        while (($parentId != NULL) && ($failSafe < 30)) {
            $failSafe++;
            if (isset($lookupArr[$parentId])) {
                // allow for overrides on folder permissions using 'sharekey' in the session
                if (!isset($_SESSION['sharekeyFolder' . $parentId]) || ($_SESSION['sharekeyFolder' . $parentId] == false)) {
                    if ($lookupArr[$parentId]['pub'] == 0) {
                        return false;
                    }
                }
                $parentId = $lookupArr[$parentId]['p'];
            }
        }
        return true;
    }

    // Gets the individual file status depending on the parent folder(s) privacy status
    static function getUserFilesPublicStatus($fileId) {
        $file = File::loadOneById($fileId);
        if (self::getUserFoldersPublicStatus($file->folderId) == true) {
            if ($file->isPublic >= 1) {
                return true;
            }
        }
        return false;
    }

    // combines the 4 functions above into one manageable function.
    public static function getOverallPublicStatus($userId = null, $folderId = null, $fileId = null) {
        // first check whether we should overide the privacy settings if we have a 'sharekeyFolder' in the session
        if (isset($_SESSION['sharekeyFolder' . $folderId]) && ($_SESSION['sharekeyFolder' . $folderId] == true)) {
            return true;
        }

        if ($fileId !== null) {
            if (isset($_SESSION['sharekeyFile' . $fileId]) && ($_SESSION['sharekeyFile' . $fileId] == true)) {
                return true;
            }
        }

        if (!self::getOverallSitePrivacyStatus()) {
            return false;
        }

        if ($userId) {
            if (self::getUserPublicStatus($userId) == false) {
                return false;
            }
        }

        if ($folderId) {
            if (self::getUserFoldersPublicStatus($folderId) == false) {
                return false;
            }
        }

        if ($fileId) {
            if (self::getUserFilesPublicStatus($fileId) == false) {
                return false;
            }
        }

        return true;
    }

    // Limits the amount of emails that can be sent per hour from the file -> 'send via email' tab.
    public static function limitEmailsSentPerHour($sendNew = '') {
        $currentCount = 0;
        if (isset($_SESSION['emailCount'])) {
            $currentCount = $_SESSION['emailCount'];
        }

        if (!isset($_SESSION['emailTimeLimit'])) {
            $_SESSION['emailTimeLimit'] = time();
        }
        else {
            $diff = time() - $_SESSION['emailTimeLimit'];
        }
        // Check the sessions are not over 1 hour old, if they are reset/recreate.
        if ($diff > 3600) {
            // unset the sessions
            unset($_SESSION['emailCount']);
            unset($_SESSION['emailTimeLimit']);

            // Recreate the session with current unixtime & set count to zero
            $_SESSION['emailTimeLimit'] = time();
            $_SESSION['emailCount'] = 0;
        }

        // Have they sent x emails which is equal or more than the max limit?
        if (($currentCount >= SITE_CONFIG_LIMIT_SEND_VIA_EMAIL_PER_HOUR) && ($diff < 3600) && ($sendNew == 1)) {
            return false;
        }

        // Sending a new email updates the count.
        if (($currentCount < SITE_CONFIG_LIMIT_SEND_VIA_EMAIL_PER_HOUR) && ($diff < 3600) && ($sendNew == 1)) {
            $_SESSION['emailCount'] = $currentCount + 1;

            return true;
        }

        // Perform a check for the emails per hour against the max limit
        if (($currentCount >= SITE_CONFIG_LIMIT_SEND_VIA_EMAIL_PER_HOUR) && ($diff < 3600)) {
            return false;
        }

        return false;
    }

    // used for compatibility with old 
    static function setupOldPaymentConstants() {
        // get database
        $db = Database::getDatabase();

        // load all paid packages
        $package_level_id = $db->getValue('SELECT id FROM user_level WHERE level_type = \'paid\' LIMIT 1');
        if ($package_level_id) {
            // load associated pricing
            $prices = $db->getRows('SELECT id, pricing_label, period, price FROM user_level_pricing WHERE user_level_id = ' . (int) $package_level_id . ' ORDER BY price ASC');
            if (count($prices) > 0) {
                foreach ($prices AS $k => $price) {
                    $priceStr = $price['price'];
                    $days = (int) self::convertStringDatePeriodToDays($price['period']);
                    if(defined('SITE_CONFIG_COST_FOR_' . $days . '_DAYS_PREMIUM')) {
                        define('SITE_CONFIG_COST_FOR_' . $days . '_DAYS_PREMIUM', $priceStr);
                    }
                }
            }
        }
    }

    static function inDemoMode() {
        return _CONFIG_DEMO_MODE === true;
    }

    static function generateRandomString($length = 10, $characters = null) {
        if ($characters == null) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyz-_';
        }
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    static function getEncryptionKey() {
        // check if we have an encryption key, revert to the database password if not
        if ((defined("_CONFIG_UNIQUE_ENCRYPTION_KEY")) && (strlen(_CONFIG_UNIQUE_ENCRYPTION_KEY))) {
            return _CONFIG_UNIQUE_ENCRYPTION_KEY;
        }

        return MD5(_CONFIG_DB_PASS);
    }

    // encrypt any value using the ecryption key
    static function encryptValue($rawValue) {
        // first check that we have support, otherwise don't try to encrypt
        if (!function_exists("openssl_encrypt")) {
            return $rawValue;
        }

        // get encryption key
        $encryptionKey = self::getEncryptionKey();

        // generate an initialization vector
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);

        // encrypt $rawValue using aes-256-cbc cipher with the given encryption key and 
        // our initialization vector. The 0 gives us the default options, but can
        // be changed to OPENSSL_RAW_DATA or OPENSSL_ZERO_PADDING
        $encrypted = openssl_encrypt($rawValue, 'aes-256-cbc', $encryptionKey, 0, $iv);
        if (!$encrypted) {
            return false;
        }

        // return encrypted value and initialization vector (needed for decrypting)
        return base64_encode($iv . $encrypted);
    }

    // decrypt any value using the ecryption key
    static function decryptValue($encryptedValue) {
        // prepare iv length
        $decoded = base64_decode($encryptedValue);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');

        // legacy method - replaced as it can sometimes fail to encrypt the data
        $parts = explode(':', $decoded);
        if (isset($parts[1]) && (strlen($parts[1]) === $ivLength)) {
            return self::decryptValueLegacy($encryptedValue);
        }

        // split apart the value to ensure we have the value and iv
        $iv = substr($decoded, 0, $ivLength);
        $ciphertextRaw = substr($decoded, $ivLength);

        // get encryption key
        $encryptionKey = self::getEncryptionKey();

        // decrypt
        $decrypted = openssl_decrypt($ciphertextRaw, 'aes-256-cbc', $encryptionKey, 0, $iv);

        return $decrypted;
    }

    // legacy method - replaced as it can sometimes fail to encrypt the data
    // @TODO remove on a later release
    // decrypt any value using the ecryption key
    static function decryptValueLegacy($encryptedValue) {
        // first check that we have support, otherwise don't try to decrypt
        if (!function_exists("openssl_decrypt")) {
            return $encryptedValue;
        }

        // split apart the value to ensure we have the value and iv
        $parts = explode(':', base64_decode($encryptedValue));

        // get encryption key
        $encryptionKey = self::getEncryptionKey();

        // decrypt
        $decrypted = openssl_decrypt($parts[0], 'aes-256-cbc', $encryptionKey, 0, $parts[1]);

        return $decrypted;
    }

    /**
     * Encrypt the passed file and saves the result in a new file with ".enc" as suffix.
     *
     * @param string $source Path to file that should be encrypted
     * @param string $dest   File name where the encrytped file should be written to.
     * @return string|false  Returns the file name that has been created or FALSE if an error occurred
     */
    function encryptFile($source, $dest) {
        $fileEncryptionBlocks = 10000;
        $key = substr(sha1($key, true), 0, 16);
        $iv = openssl_random_pseudo_bytes(16);

        // get encryption key
        $encryptionKey = self::getEncryptionKey();

        $error = false;
        if ($fpOut = fopen($dest, 'w')) {
            // Put the initialzation vector to the beginning of the file
            fwrite($fpOut, $iv);
            if ($fpIn = fopen($source, 'rb')) {
                while (!feof($fpIn)) {
                    $plaintext = fread($fpIn, 16 * $fileEncryptionBlocks);
                    $ciphertext = openssl_encrypt($plaintext, 'AES-128-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);
                    // Use the first 16 bytes of the ciphertext as the next initialization vector
                    $iv = substr($ciphertext, 0, 16);
                    fwrite($fpOut, $ciphertext);
                }
                fclose($fpIn);
            }
            else {
                $error = true;
            }
            fclose($fpOut);
        }
        else {
            $error = true;
        }

        return $error ? false : $dest;
    }

    /**
     * Decrypt the passed file and saves the result in a new file, removing the
     * last 4 characters from file name.
     *
     * @param string $source Path to file that should be decrypted
     * @param string $dest   File name where the decrypted file should be written to.
     * @return string|false  Returns the file name that has been created or FALSE if an error occurred
     */
    function decryptFile($source, $dest) {
        $fileEncryptionBlocks = 10000;
        $key = substr(sha1($key, true), 0, 16);

        // get encryption key
        $encryptionKey = self::getEncryptionKey();

        $error = false;
        if ($fpOut = fopen($dest, 'w')) {
            if ($fpIn = fopen($source, 'rb')) {
                // Get the initialzation vector from the beginning of the file
                $iv = fread($fpIn, 16);
                while (!feof($fpIn)) {
                    // we have to read one block more for decrypting than for encrypting
                    $ciphertext = fread($fpIn, 16 * ($fileEncryptionBlocks + 1));
                    $plaintext = openssl_decrypt($ciphertext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
                    // Use the first 16 bytes of the ciphertext as the next initialization vector
                    $iv = substr($ciphertext, 0, 16);
                    fwrite($fpOut, $plaintext);
                }
                fclose($fpIn);
            }
            else {
                $error = true;
            }
            fclose($fpOut);
        }
        else {
            $error = true;
        }

        return $error ? false : $dest;
    }

    static function getDocumentDomain() {
        return self::removeSubDomain(_CONFIG_CORE_SITE_HOST_URL);
    }

    static function getDocumentDomainScript() {
        return "<script>document.domain = '" . self::getDocumentDomain() . "';</script>";
    }

    static function cleanTextareaInput($str) {
        // make new lines consistant
        $str = trim($str);
        $str = str_replace(array("\n\n", "\r"), "\n", $str);

        // replace new lines
        $str = str_replace("\n", " ", $str);

        // final formatting
        $str = str_replace("  ", " ", $str);
        $str = strip_tags($str);

        // limit to 255 characters
        $str = substr($str, 0, 255);

        return $str;
    }

    static function emptyTrashByUserId($userId) {
        // get database
        $db = Database::getDatabase();
        $rs = [
            'total_files' => 0,
            'total_folders' => 0,
        ];

        // first remove files
        $fileArr = $db->getRows('SELECT * '
                . 'FROM file '
                . 'WHERE (userId = :user_id OR uploadedUserId = :user_id) '
                . 'AND status = "trash"', array(
            'user_id' => (int) $userId,
        ));
        if ($fileArr) {
            foreach ($fileArr AS $fileArrItem) {
                // load our file object so we can schedule the delete
                $file = File::hydrateSingleRecord($fileArrItem);

                // schedule the delete
                $file->removeByUser();
                $rs['total_files']++;
            }
        }

        // now clear any folders
        $folderArr = $db->getRows('SELECT * '
                . 'FROM file_folder '
                . 'WHERE userId = :user_id '
                . 'AND status = "trash"', array(
            'user_id' => (int) $userId,
        ));
        if ($folderArr) {
            foreach ($folderArr AS $folderArrItem) {
                // load our folder object so we can delete it
                $folder = FileFolder::hydrateSingleRecord($folderArrItem);

                // delete
                $folder->removeByUser(false);
                $rs['total_folders']++;
            }
        }

        return $rs;
    }

    static function getScriptInstalledVersion() {
        return Framework::VERSION_NUMBER;
    }
    
    static function getScriptVersionIdentifier() {
        return Framework::VERSION_IDENTIFIER;
    }

    static function getCurrentBrowserUrl() {
        return _CONFIG_SITE_PROTOCOL . "://" . _CONFIG_SITE_HOST_URL . $_SERVER['REQUEST_URI'];
    }

    static function downloadingDisabled() {
        // check for admin user
        $Auth = AuthHelper::getAuth();
        if ($Auth->loggedIn()) {
            if ($Auth->level_id == 20) {
                return false;
            }
        }

        if (defined('SITE_CONFIG_DOWNLOADS_BLOCK_ALL') && (SITE_CONFIG_DOWNLOADS_BLOCK_ALL == 'yes')) {
            return true;
        }

        return false;
    }

    static function recurseCopy($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    self::recurseCopy($src . '/' . $file, $dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    static function base32Encode($str) {
        list($t, $b, $r) = array("ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", "", "");

        foreach (str_split($str) as $c) {
            $b = $b . sprintf("%08b", ord($c));
        }

        foreach (str_split($b, 5) as $c) {
            $r = $r . $t[bindec($c)];
        }

        return($r);
    }

}
