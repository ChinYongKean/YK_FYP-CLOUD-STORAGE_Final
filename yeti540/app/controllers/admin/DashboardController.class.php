<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Models\Plugin;
use App\Helpers\AdminHelper;
use App\Helpers\AuthHelper;
use App\Helpers\BannedIpHelper;
use App\Helpers\CacheHelper;
use App\Helpers\CoreHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;

class DashboardController extends AdminBaseController
{

    public function indexRedirector() {
        return $this->redirect(ADMIN_WEB_ROOT . '/');
    }

    public function index() {
        // admin restrictions
        // allow moderators
        $this->restrictAdminAccess(10);

        // pickup request
        $request = $this->getRequest();

        // make sure the install folder has been removed
        if (file_exists(DOC_ROOT . '/install/')) {
            AdminHelper::setSuccess("Remove the /install/ folder within your webroot asap.");
        }

        // should we show a warning about lack of an encryption key
        if ($request->query->has('shash') && (!defined('_CONFIG_UNIQUE_ENCRYPTION_KEY'))) {
            // check for write permissions
            $configFile = DOC_ROOT . '/_config.inc.php';
            if (!is_writable($configFile)) {
                AdminHelper::setError("The site config file (_config.inc.php) is not writable (CHMOD 777 or 755). Please update and <a href='" . ADMIN_WEB_ROOT . "?shash=1'>try again</a>.");
            }
            else {
                // try to set _config file
                $oldContent = file_get_contents($configFile);
                if (strlen($oldContent)) {
                    $newHash = CoreHelper::generateRandomString(125, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890");
                    if (strlen($newHash)) {
                        $newHashLine = "/* key used for encoding data within the site */\ndefine(\"_CONFIG_UNIQUE_ENCRYPTION_KEY\", \"" . $newHash . "\");\n";
                        $newContent = $oldContent . "\n\n" . $newHashLine;

                        // write new file contents
                        $rs = file_put_contents($configFile, $newContent);
                        if ($rs) {
                            AdminHelper::setSuccess("Security key set, please revert the permissions on your _config.inc.php file. If you run external file servers, please copy the new '_CONFIG_UNIQUE_ENCRYPTION_KEY' line in your _config.inc.php file onto each file server config file. The key should be the same on all servers.");
                        }
                    }
                }
            }
        }
        elseif (!defined('_CONFIG_UNIQUE_ENCRYPTION_KEY')) {
            AdminHelper::setError("<strong>IMPORTANT:</strong> The latest code offers enhanced security by encrypting certain values before storing them within the database. The key for this needs set within your _config.inc.php file. To automatically create this, set write permissions on _config.inc.php (CHMOD 777 or 755) and <a href='" . ADMIN_WEB_ROOT . "?shash=1'>click here</a>.");
        }

        // load stats
        $db = Database::getDatabase();
        $totalActiveFiles = (int) $db->getValue("SELECT COUNT(1) AS total "
                        . "FROM file "
                        . "WHERE status = 'active'");
        $totalDownloads = (int) $db->getValue("SELECT SUM(visits) AS total "
                        . "FROM file");
        $totalHDSpace = $db->getValue("SELECT SUM(file_server.totalSpaceUsed) "
                . "FROM file_server");
        $totalRegisteredUsers = (int) $db->getValue("SELECT COUNT(1) AS total "
                        . "FROM users "
                        . "WHERE status='active' "
                        . "AND level_id IN (SELECT id FROM user_level "
                        . "WHERE level_type != 'nonuser')");
        $totalPaidUsers = (int) $db->getValue("SELECT COUNT(1) AS total "
                        . "FROM users "
                        . "WHERE status='active' "
                        . "AND level_id IN (SELECT id FROM user_level "
                        . "WHERE level_type = 'paid')");
        $totalReports = (int) $db->getValue("SELECT COUNT(1) AS total "
                        . "FROM file_report "
                        . "WHERE report_status='pending'");
        $payments30Days = $db->getRows("SELECT SUM(amount) AS total, currency_code "
                . "FROM payment_log "
                . "WHERE date_created BETWEEN NOW() - INTERVAL 30 DAY AND NOW() "
                . "GROUP BY currency_code");

        $topBoxSize = 2;
        if (ThemeHelper::getCurrentProductType() == 'cloudable') {
            $topBoxSize = 3;
        }

        // load template
        return $this->render('admin/index.html', array(
                    'Auth' => AuthHelper::getAuth(),
                    'totalActiveFiles' => $totalActiveFiles,
                    'totalDownloads' => $totalDownloads,
                    'totalHDSpace' => $totalHDSpace,
                    'totalHDSpaceFormatted' => AdminHelper::formatSize($totalHDSpace, 0),
                    'totalRegisteredUsers' => $totalRegisteredUsers,
                    'totalPaidUsers' => $totalPaidUsers,
                    'totalReports' => $totalReports,
                    'payments30Days' => $payments30Days,
                    'topBoxSize' => $topBoxSize,
                    'currentProduct' => ThemeHelper::getCurrentProductType(),
                                ));
    }

    public function login() {
        // admin restrictions
        $this->restrictAdminAccess(0, true);

        // setup presets
        $username = ($this->inDemoMode()) ? 'admin' : '';
        $password = ($this->inDemoMode()) ? 'Password@Demo' : '';
        $Auth = AuthHelper::getAuth();

        // pickup request
        $request = $this->getRequest();

        // check for openssl, required for login
        if (!extension_loaded('openssl')) {
            AdminHelper::setError(TranslateHelper::t("openssl_not_found", "Openssl functions not found within PHP, please ask support to install and try again."));
        }

        // if the user is already logged in but not an admin, display an error
        if ($Auth->loggedIn()) {
            if ($Auth->hasAccessLevel(20) === false) {
                AdminHelper::setError(TranslateHelper::t("admin_account_required", "Admin only users are permitted to access this area, your login attempt has been recorded."));
            }
        }

        // handle login request
        if ($request->request->has('submitme')) {
            // clear any expired IPs
            BannedIpHelper::clearExpiredBannedIps();

            // do login
            $loginUsername = trim($request->request->get('username'));
            $loginPassword = trim($request->request->get('password'));

            // check user isn't banned from logging in
            $bannedIp = BannedIpHelper::getBannedIPData();
            if ($bannedIp) {
                if ($bannedIp->banType === 'Login') {
                    AdminHelper::setError(TranslateHelper::t("login_ip_banned", "You have been temporarily blocked from logging in due to too many failed login attempts. Please try again [[[EXPIRY_TIME]]].", array('EXPIRY_TIME' => ($bannedIp->banExpiry !== null ? CoreHelper::formatDate($bannedIp->banExpiry) : t('later', 'later')))));
                }
            }

            // initial validation
            if (AdminHelper::isErrors() === false) {
                if (!strlen($loginUsername)) {
                    // log failure
                    AuthHelper::logFailedLoginAttempt(CoreHelper::getUsersIPAddress(), $loginUsername);

                    AdminHelper::setError(TranslateHelper::t("please_enter_your_username", "Please enter your username"));
                }
                elseif (!strlen($loginPassword)) {
                    // log failure
                    AuthHelper::logFailedLoginAttempt(CoreHelper::getUsersIPAddress(), $loginUsername);

                    AdminHelper::setError(TranslateHelper::t("please_enter_your_password", "Please enter your password"));
                }
            }

            // check captcha
            if ((!AdminHelper::isErrors()) && (SITE_CONFIG_CAPTCHA_LOGIN_SCREEN_ADMIN == 'yes')) {
                $resp = CoreHelper::captchaCheck();
                if ($resp == false) {
                    AdminHelper::setError(TranslateHelper::t("invalid_captcha", "Captcha confirmation text is invalid."));
                }
            }

            $redirectUrl = '';
            if (AdminHelper::isErrors() === false) {
                $rs = $Auth->login($loginUsername, $loginPassword, true);
                if ($rs) {
                    // successful login
                    return $this->redirect(ADMIN_WEB_ROOT);
                }
                else {
                    // login failed, check reason
                    switch ($Auth->lastLoginError) {
                        case '2fa_check_needed':
                            $redirectUrl = ADMIN_WEB_ROOT . '/login_2fa';
                            break;
                        default:
                            AdminHelper::setError(TranslateHelper::t("username_and_password_is_invalid", "Your username and password are invalid"));
                    }
                }
            }
            
            if(strlen($redirectUrl)) {
                return $this->redirect($redirectUrl);
            }
        }

        // load template
        return $this->render('admin/login.html', array(
                    'username' => $username,
                    'password' => $password,
                    'scriptVersion' => CoreHelper::getScriptInstalledVersion(),
                    'userIpAddress' => CoreHelper::getUsersIPAddress(),
        ));
    }
    
    public function login2FA() {
        // admin restrictions
        $this->restrictAdminAccess(0, true);

        // setup presets
        $Auth = AuthHelper::getAuth();

        // pickup request
        $request = $this->getRequest();

        // require session un/pw
        if (!isset($_SESSION['_2faUser']['username'])) {
            return $this->redirect(WEB_ROOT . '/account/login');
        }

        if ($request->request->has('submitme')) {
            // attempt login
            $accessCode2fa = $request->request->has('access_code_2fa')?$request->request->get('access_code_2fa'):null;
            $loginUsername = $_SESSION['_2faUser']['username'];
            $loginPassword = $_SESSION['_2faUser']['rawPassword'];
            $rs = $Auth->login($loginUsername, $loginPassword, true, $accessCode2fa);
            if ($rs) {
                // clear temp session variables
                unset($_SESSION['_2faUser']);
                
                // successful login
                return $this->redirect(ADMIN_WEB_ROOT);
            }
            else {
                // login failed, check reason
                switch ($Auth->lastLoginError) {
                    case '2fa_check_needed':
                        $redirectUrl = ADMIN_WEB_ROOT . '/login_2fa';
                        break;
                    case 'invalid_2fa_code':
                        $redirectUrl = ADMIN_WEB_ROOT . '/login_2fa?error='.urlencode(TranslateHelper::t("2fa_code_is_invalid", "2FA code is invalid"));
                        AdminHelper::setError(TranslateHelper::t("2fa_code_is_invalid", "2FA code is invalid"));
                        break;
                    default:
                        AdminHelper::setError(TranslateHelper::t("username_and_password_is_invalid", "Your username and password are invalid"));
                }
            }
        }

        // load template
        return $this->render('admin/login_2fa.html', array(
                    'scriptVersion' => CoreHelper::getScriptInstalledVersion(),
                    'userIpAddress' => CoreHelper::getUsersIPAddress(),
        ));
    }

    public function logout() {
        $Auth = AuthHelper::getAuth();
        $Auth->logout();

        return $this->redirect('login');
    }

    public function ajaxAccountViewAvatar() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $request = $this->getRequest();
        $Auth = AuthHelper::getAuth();

        // pickup variables
        $width = (int) $request->query->get('width');
        $height = (int) $request->query->get('height');
        $userId = $Auth->id;
        if ($request->query->has('userId')) {
            $userId = (int) $request->query->has('userId');
        }
        if (($width == 0) || ($height == 0)) {
            return $this->render404();
        }

        // block memory issues
        if (($width > 500) || ($height > 500)) {
            return $this->render404();
        }

        // setup paths
        $avatarCachePath = 'user/' . (int) $userId . '/profile';
        $avatarCacheFilename = MD5((int) $userId . $width . $height . 'square') . '.jpg';
        $originalFilename = 'avatar_original.png';

        // check if user has cached avatar
        if ($fileContent = CacheHelper::getCacheFromFile($avatarCachePath . '/' . $avatarCacheFilename)) {
            return $this->renderFileContent($fileContent, array(
                        'Content-Type' => 'image/jpeg',
                            )
            );
        }

        // check for original avatar image
        if (!CacheHelper::getCacheFromFile($avatarCachePath . '/' . $originalFilename)) {
            // no avatar uploaded, output default icon
            $defaultIcon = file_get_contents(CORE_ASSETS_ADMIN_DIRECTORY_ROOT . '/images/avatar_default.jpg');
            return $this->renderFileContent($defaultIcon, array(
                        'Content-Type' => 'image/jpeg',
                            )
            );
        }

        $avatarOriginal = CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath . '/' . $originalFilename;

        // resize image to square thumbnail
        list($ow, $oh) = getimagesize($avatarOriginal);
        switch (substr($avatarOriginal, strlen($avatarOriginal) - 3, 3)) {
            case 'png':
                $imageOriginal = imagecreatefrompng($avatarOriginal);
                break;
            case 'gif':
                $imageOriginal = imagecreatefromgif($avatarOriginal);
                break;
            default:
                $imageOriginal = imagecreatefromjpeg($avatarOriginal);
                break;
        }

        $imageThumb = imagecreatetruecolor($width, $height);
        if ($ow > $oh) {
            $offW = ($ow - $oh) / 2;
            $offH = 0;
            $ow = $oh;
        }
        elseif ($oh > $ow) {
            $offW = 0;
            $offH = ($oh - $ow) / 2;
            $oh = $ow;
        }
        else {
            $offW = 0;
            $offH = 0;
        }

        imagecopyresampled($imageThumb, $imageOriginal, 0, 0, $offW, $offH, $width, $height, $ow, $oh);

        // get content as variable so we can use the caching functions
        ob_start();
        imagejpeg($imageThumb, null, 100);
        $imageData = ob_get_contents();
        ob_end_clean();

        // save cache
        CacheHelper::saveCacheToFile($avatarCachePath . '/' . $avatarCacheFilename, $imageData);

        // output image
        return $this->renderFileContent($imageData, array(
                    'Content-Type' => 'image/jpeg',
                        )
        );
    }

    public function ajaxCheckForUpgrade() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $request = $this->getRequest();

        // add the core script for checking
        $items = [];
        $items[] = array(
            't' => 'core',
            'st' => ThemeHelper::getCurrentProductType(),
            'uid' => WEB_ROOT,
            'v' => CoreHelper::getScriptInstalledVersion(),
        );

        // load list of plugins and their current version numbers for checking
        $plugins = Plugin::loadAll('plugin_name');
        if ($plugins) {
            foreach ($plugins AS $plugin) {
                // load version number
                $pluginVersion = null;
                $pluginConfig = PluginHelper::getPluginConfigByFolderName($plugin->folder_name);
                if ($pluginConfig !== false) {
                    $pluginVersion = $pluginConfig->getPluginVersion();
                }

                if ($pluginVersion != null) {
                    $items[] = array(
                        't' => 'plugin',
                        'uid' => $plugin->folder_name,
                        'v' => $pluginVersion,
                    );
                }
            }
        }

        // prep url
        $url = 'yetishare';
        if (ThemeHelper::getCurrentProductType() == 'image_hosting') {
            $url = 'reservo';
        }
        elseif (ThemeHelper::getCurrentProductType() == 'cloudable') {
            $url = 'cloudable';
        }
        $url = '' . $url . '.php';

        // check we have curl
        if (!function_exists('curl_init')) {
            // send via normal get
            $responseStr = CoreHelper::getRemoteUrlContent($url . '?req=' . urlencode(json_encode($items)));
            if (!$responseStr) {
                $responseStr = '';
            }
        }
        else {
            // send the data via curl
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('req' => json_encode($items))));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseStr = curl_exec($ch);
            curl_close($ch);
        }

        // output response
        return $this->renderContent($responseStr);
    }

    public function ajaxDashboardChart12MonthsChart() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // last 12 months files
        $tracker = 12;
        $last12Months = [];
        while ($tracker >= 0) {
            $date = date("Y-m", strtotime("-" . $tracker . " month"));
            $last12Months[$date] = 0;
            $tracker--;
        }

        $tracker = 1;
        $data = [];
        $label = [];

        // get data
        $chartData = $db->getRows("SELECT COUNT(1) AS total, MID(uploadedDate, 1, 7) AS date_part "
                . "FROM file "
                . "WHERE file.uploadedDate >= DATE_ADD(CURDATE(), INTERVAL -13 MONTH) "
                . "GROUP BY (CONCAT(YEAR(uploadedDate), MONTH(uploadedDate)))");

        // format data for easier lookups
        $chartDataArr = [];
        if ($chartData) {
            foreach ($chartData AS $chartDataItem) {
                $chartDataArr[$chartDataItem['date_part']] = $chartDataItem['total'];
            }
        }

        // prepare for table
        foreach ($last12Months AS $k => $total) {
            $totalFiles = isset($chartDataArr[$k]) ? $chartDataArr[$k] : 0;
            $data[] = '[' . $tracker . ',' . (int) $totalFiles . ']';
            $label[] = '[' . $tracker . ',\'' . date('M y', strtotime($k)) . '\']';
            $tracker++;
        }

        // load template
        return $this->render('admin/ajax/dashboard_chart_12_months_chart.html', array(
                    'data' => $data,
                    'label' => $label,
        ));
    }

    public function ajaxDashboardChart14DayChart() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // last 14 days chart
        $tracker = 14;
        $last14Days = [];
        while ($tracker >= 0) {
            $date = date("Y-m-d", strtotime("-" . $tracker . " day"));
            $last14Days[$date] = 0;
            $tracker--;
        }

        $tracker = 1;
        $data = [];
        $label = [];

        // get data
        $chartData = $db->getRows("SELECT COUNT(1) AS total, MID(uploadedDate, 1, 10) AS date_part "
                . "FROM file "
                . "WHERE file.uploadedDate >= DATE_ADD(CURDATE(), INTERVAL -15 DAY) "
                . "GROUP BY DAY(uploadedDate)");

        // format data for easier lookups
        $chartDataArr = [];
        if ($chartData) {
            foreach ($chartData AS $chartDataItem) {
                $chartDataArr[$chartDataItem['date_part']] = $chartDataItem['total'];
            }
        }

        // prepare for table
        foreach ($last14Days AS $k => $total) {
            $totalFiles = isset($chartDataArr[$k]) ? $chartDataArr[$k] : 0;
            $data[] = '[' . $tracker . ',' . (int) $totalFiles . ']';
            $label[] = '[' . $tracker . ',\'' . date('jS', strtotime($k)) . '\']';
            $tracker++;
        }

        // load template
        return $this->render('admin/ajax/dashboard_chart_14_day_chart.html', array(
                    'data' => $data,
                    'label' => $label,
        ));
    }

    public function ajaxDashboardChart14DayUsers() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // last 14 days user registrations
        $tracker = 14;
        $last14Days = [];
        while ($tracker >= 0) {
            $date = date("Y-m-d", strtotime("-" . $tracker . " day"));
            $last14Days[$date] = 0;
            $tracker--;
        }

        $tracker = 1;
        $dataFree = [];
        $dataPaid = [];
        $label = [];

        // get data
        $chartData1 = $db->getRows("SELECT COUNT(1) AS total, MID(datecreated, 1, 10) AS date_part "
                . "FROM users "
                . "WHERE users.datecreated >= DATE_ADD(CURDATE(), INTERVAL -15 DAY) "
                . "AND level_id IN (SELECT id FROM user_level WHERE level_type = 'free') "
                . "GROUP BY DAY(datecreated)");

        // format data for easier lookups
        $chartDataArr1 = [];
        if ($chartData1) {
            foreach ($chartData1 AS $chartDataItem1) {
                $chartDataArr1[$chartDataItem1['date_part']] = $chartDataItem1['total'];
            }
        }

        // get data
        $chartData2 = $db->getRows("SELECT COUNT(1) AS total, MID(datecreated, 1, 10) AS date_part "
                . "FROM users "
                . "WHERE users.datecreated >= DATE_ADD(CURDATE(), INTERVAL -15 DAY) "
                . "AND level_id IN (SELECT id FROM user_level WHERE level_type = 'paid') "
                . "GROUP BY DAY(datecreated)");

        // format data for easier lookups
        $chartDataArr2 = [];
        if ($chartData2) {
            foreach ($chartData2 AS $chartDataItem2) {
                $chartDataArr2[$chartDataItem2['date_part']] = $chartDataItem2['total'];
            }
        }

        // prepare for table
        foreach ($last14Days AS $k => $total) {
            $totalUsers = isset($chartDataArr1[$k]) ? $chartDataArr1[$k] : 0;
            $dataFree[] = '[' . $tracker . ',' . (int) $totalUsers . ']';
            $totalUsers = isset($chartDataArr2[$k]) ? $chartDataArr2[$k] : 0;
            $dataPaid[] = '[' . $tracker . ',' . (int) $totalUsers . ']';
            $label[] = '[' . $tracker . ',\'' . date('jS', strtotime($k)) . '\']';
            $tracker++;
        }

        // load template
        return $this->render('admin/ajax/dashboard_chart_14_day_users.html', array(
                    'dataFree' => $dataFree,
                    'dataPaid' => $dataPaid,
                    'label' => $label,
        ));
    }

    public function ajaxDashboardChartFileStatusChart() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // pie chart of the status of items
        $data = [];
        $labels = [];
        $dataForPie = $db->getRows("SELECT COUNT(1) AS total, status "
                . "FROM file "
                . "GROUP BY file.status "
                . "ORDER BY COUNT(1) DESC");
        foreach ($dataForPie AS $dataRow) {
            $data[] = (int) $dataRow['total'];
            $labels[] = UCWords(AdminHelper::t($dataRow['status'], $dataRow['status']));
        }

        $colors = array("#BDC3C7",
            "#9B59B6",
            "#E74C3C",
            "#26B99A",
            "#3498DB");

        // load template
        return $this->render('admin/ajax/dashboard_chart_file_status_chart.html', array(
                    'data' => $data,
                    'labels' => $labels,
                    'colors' => $colors,
        ));
    }

    public function ajaxDashboardChartFileTypeChart() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // pie chart of file types
        $data = [];
        $labels = [];
        $dataForPie = $db->getRows("SELECT COUNT(1) AS total, file.extension AS status "
                . "FROM file "
                . "WHERE status = 'active' "
                . "GROUP BY file.extension "
                . "ORDER BY COUNT(1) DESC");
        $counter = 1;
        $otherTotal = 0;
        foreach ($dataForPie AS $dataRow) {
            if ($counter > 5) {
                $otherTotal = $otherTotal + $dataRow['total'];
            }
            else {
                $data[] = (int) $dataRow['total'];
                $labels[] = UCWords(AdminHelper::t($dataRow['status'], $dataRow['status']));
            }
            $counter++;
        }
        if ($otherTotal > 0) {
            $data[] = (int) $otherTotal;
            $labels[] = UCWords(strtolower(AdminHelper::t('other', 'other')));
        }

        $colors = array("#BDC3C7",
            "#9B59B6",
            "#E74C3C",
            "#26B99A",
            "#3498DB",
            "#26B99A");

        // load template
        return $this->render('admin/ajax/dashboard_chart_file_type_chart.html', array(
                    'data' => $data,
                    'labels' => $labels,
                    'colors' => $colors,
        ));
    }

    public function ajaxDashboardChartUserStatusChart() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // pie chart of user status
        $data = [];
        $labels = [];
        $dataForPie = $db->getRows("SELECT COUNT(1) AS total, user_level.label "
                . "FROM users "
                . "LEFT JOIN user_level ON users.level_id = user_level.id "
                . "GROUP BY users.level_id "
                . "ORDER BY COUNT(users.id) DESC");
        foreach ($dataForPie AS $dataRow) {
            $data[] = (int) $dataRow['total'];
            $labels[] = UCWords(AdminHelper::t($dataRow['label'], $dataRow['label']));
        }

        $colors = array("#BDC3C7",
            "#9B59B6",
            "#E74C3C",
            "#26B99A",
            "#3498DB");

        // load template
        return $this->render('admin/ajax/dashboard_chart_user_status_chart.html', array(
                    'data' => $data,
                    'labels' => $labels,
                    'colors' => $colors,
        ));
    }

}
