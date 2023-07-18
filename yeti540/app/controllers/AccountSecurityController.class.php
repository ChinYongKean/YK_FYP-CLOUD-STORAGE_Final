<?php

namespace App\Controllers;

use App\Helpers\UserActionLogHelper;
use App\Models\File;
use App\Models\User;
use App\Helpers\AuthHelper;
use App\Helpers\BannedIpHelper;
use App\Helpers\CoreHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PluginHelper;
use App\Helpers\UserHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;
use App\Services\Password;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;
use Symfony\Component\HttpFoundation\Cookie;

class AccountSecurityController extends AccountController
{

    public function login() {
        // direct to install if it exists still
        if (file_exists(DOC_ROOT . '/install')) {
            return $this->redirect(WEB_ROOT . '/install');
        }

        // call plugin hooks, for redirect types
        if (is_object($rs = PluginHelper::callHook('preLogin'))) {
            return $rs;
        }

        // get params for later
        $Auth = $this->getAuth();

        // if user already logged in revert to account home
        if ($Auth->loggedIn()) {
            return $this->redirect(CoreHelper::getCoreSitePath() . '/account');
        }

        // handle requests for login
        $request = $this->getRequest();
        if ($request->request->has('submitme')) {
            // attempt login
            $loginUsername = $request->request->get('username');
            $loginPassword = $request->request->get('password');
            $rs = $this->_handleLogin($loginUsername, $loginPassword);

            // success
            if (strlen($rs['redirect_url'])) {
                return $this->redirect($rs['redirect_url']);
            }

            // setup error for template
            NotificationHelper::setError($rs['error']);
        }

        // demo values
        $username = $password = '';
        if ($this->inDemoMode()) {
            $username = 'admin';
            $password = 'Password@Demo';
        }

        // pickup any success states
        if ($request->query->has('s') && (int) $request->query->get('s') === 1) {
            // setup success for template
            NotificationHelper::setSuccess(TranslateHelper::t('password_successfully_reset', 'Password successfully reset.'));
        }

        // load template
        return $this->render('account/login.html', [
                    'username' => $username,
                    'password' => $password,
                    'Auth' => AuthHelper::getAuth(),
                    'HookLoginLoginBoxHtml' => PluginHelper::outputHook('loginLoginBox'),
        ]);
    }

    public function login2fa() {
        // get params for later
        $Auth = $this->getAuth();

        // handle requests for login
        $request = $this->getRequest();

        // require session un/pw
        if (!isset($_SESSION['_2faUser']['username'])) {
            return $this->redirect(WEB_ROOT . '/account/login');
        }

        if ($request->request->has('submitme')) {
            // attempt login
            $accessCode2fa = $request->request->has('access_code_2fa')?$request->request->get('access_code_2fa'):null;
            $loginUsername = $_SESSION['_2faUser']['username'];
            
            // social logins
            $success = false;
            $error = 'There was a general error, please try again.';
            $redirectUrl = CoreHelper::getCoreSitePath() . '/account';
            if(isset($_SESSION['_2faUser']['socialLogin']) && $_SESSION['_2faUser']['socialLogin'] === true) {
                $rs = $Auth->impersonate($loginUsername, $accessCode2fa);
                if($rs) {
                    $success = true;
                    $_SESSION['socialLogin'] = $_SESSION['_2faUser']['socialLogin'];
                    $_SESSION['socialProvider'] = $_SESSION['_2faUser']['provider'];
                    $_SESSION['socialData'] = $_SESSION['_2faUser']['userProfileData'];
                }
                else {
                    $error = TranslateHelper::t("2fa_code_is_invalid", "2FA code is invalid");
                }
            }
            // normal logins
            else {
                $loginPassword = $_SESSION['_2faUser']['rawPassword'];
                $rs = $this->_handleLogin($loginUsername, $loginPassword, true);
                if ($rs['login_status'] === 'success') {
                    $success = true;
                    $redirectUrl = $rs['redirect_url'];
                }
                else {
                    $error = $rs['error'];
                }
            }

            // success
            if ($success === true) {
                unset($_SESSION['_2faUser']);
                
                return $this->redirect($redirectUrl);
            }

            // setup error for template
            NotificationHelper::setError($error);
        }

        // load template
        return $this->render('account/login_2fa.html', [
                    'Auth' => AuthHelper::getAuth(),
        ]);
    }

    public function logout() {
        // get params for later
        $Auth = $this->getAuth();

        // logout the user
        $Auth->logout();
        $request = $this->getRequest();

        // prepare response
        $response = $this->redirect(CoreHelper::getCoreSitePath());

        // clear any trial account cookies
        if ($request->cookies->has('trial_username')) {
            $cookieUsername = new Cookie('trial_username', $user->username, strtotime('-1 day'));
            $response->headers->setCookie($cookieUsername);
        }
        if ($request->cookies->has('trial_hash')) {
            $cookieHash = new Cookie('trial_hash', $user->username, strtotime('-1 day'));
            $response->headers->setCookie($cookieHash);
        }

        // redirect to the login page
        return $response;
    }

    public function ajaxLogin() {
        $request = $this->getRequest();
        $loginUsername = $request->request->get('username');
        $loginPassword = $request->request->get('password');
        
        $rs = $this->_handleLogin($loginUsername, $loginPassword);

        return $this->renderJson($rs);
    }

    private function _handleLogin($loginUsername, $loginPassword, $skipCaptcha = false) {
        // get params for later
        $Auth = $this->getAuth();
        $request = $this->getRequest();

        // setup result array
        $rs = [];

        // do login
        $request = $this->getRequest();
        $loginStatus = 'invalid';
        $rs['error'] = '';

        // clear any expired IPs
        BannedIpHelper::clearExpiredBannedIps();

        // check user isn't banned from logging in
        $bannedIp = BannedIpHelper::getBannedIPData(CoreHelper::getUsersIPAddress());
        if ($bannedIp) {
            if ($bannedIp->banType === 'Login') {
                $rs['error'] = TranslateHelper::t("login_ip_banned", "You have been temporarily blocked from logging in due to too many failed login attempts. Please try again [[[EXPIRY_TIME]]].", ['EXPIRY_TIME' => ($bannedIp->banExpiry != null ? CoreHelper::formatDate($bannedIp->banExpiry) : TranslateHelper::t('later', 'later'))]);
            }
        }

        // initial validation
        if (strlen($rs['error']) == 0) {
            if (!strlen($loginUsername)) {
                // log failure
                AuthHelper::logFailedLoginAttempt(CoreHelper::getUsersIPAddress(), $loginUsername);

                $rs['error'] = TranslateHelper::t("please_enter_your_username", "Please enter your username");
            }
            elseif (!strlen($loginPassword)) {
                // log failure
                AuthHelper::logFailedLoginAttempt(CoreHelper::getUsersIPAddress(), $loginUsername);

                $rs['error'] = TranslateHelper::t("please_enter_your_password", "Please enter your password");
            }
        }

        // check for openssl, required for login
        if (strlen($rs['error']) == 0) {
            if (!extension_loaded('openssl')) {
                $rs['error'] = TranslateHelper::t("openssl_not_found", "OpenSSL functions not found within PHP, please ask support to install and try again.");
            }
        }

        // check captcha
        if (($skipCaptcha === false) && (strlen($rs['error']) == 0) && (SITE_CONFIG_CAPTCHA_LOGIN_SCREEN_NORMAL == 'yes')) {
            $resp = CoreHelper::captchaCheck();
            if ($resp == false) {
                $rs['error'] = TranslateHelper::t("invalid_captcha", "Captcha confirmation text is invalid.");
            }
        }

        $redirectUrl = '';
        if (strlen($rs['error']) == 0) {
            $accessCode2fa = $request->request->has('access_code_2fa')?$request->request->get('access_code_2fa'):null;
            $loginResult = $Auth->login($loginUsername, $loginPassword, true, $accessCode2fa);
            if ($loginResult) {
                // if we know the file
                if ($request->request->has('loginShortUrl')) {
                    // download file
                    $file = File::loadOneByShortUrl($request->request->get('loginShortUrl'));
                    if ($file) {
                        $redirectUrl = $file->getFullShortUrl();
                    }
                }
                else {
                    // successful login
                    $redirectUrl = CoreHelper::getCoreSitePath() . '/account';
                }

                $loginStatus = 'success';
            }
            else {
                // login failed, check reason
                switch ($Auth->lastLoginError) {
                    case '2fa_check_needed':
                        $redirectUrl = WEB_ROOT . '/account/login_2fa';
                        break;
                    case 'invalid_2fa_code':
                        $redirectUrl = WEB_ROOT . '/account/login_2fa?error='.urlencode(TranslateHelper::t("2fa_code_is_invalid", "2FA code is invalid"));
                        $rs['error'] = TranslateHelper::t("2fa_code_is_invalid", "2FA code is invalid");
                        break;
                    case 'account_suspended':
                        $rs['error'] = TranslateHelper::t("account_suspended", "This account is suspended");
                        break;
                    case 'account_not_active':
                        $rs['error'] = TranslateHelper::t("account_is_inactive", "Account is inactive");
                        break;
                    default:
                        $rs['error'] = TranslateHelper::t("username_and_password_is_invalid", "Your username and password are invalid");
                }
            }
        }

        $rs['login_status'] = $loginStatus;

        // login success
        if (strlen($redirectUrl)) {
            // Set the redirect url after successful login
            $rs['redirect_url'] = $redirectUrl;
        }

        return $rs;
    }

    public function forgotPassword() {
        // get params for later
        $Auth = $this->getAuth();

        // if user already logged in revert to account home
        if ($Auth->loggedIn()) {
            return $this->redirect(CoreHelper::getCoreSitePath());
        }

        // handle requests for login (non ajax)
        $request = $this->getRequest();
        if ($request->request->has('submitme')) {
            // attempt login
            $rs = $this->_handleForgotPassword();

            // success
            if ($rs['forgot_password_status'] === 'success') {
                return $this->redirect($rs['redirect_url']);
            }

            // setup error for template
            NotificationHelper::setError($rs['error']);
        }

        // load template
        return $this->render('account/forgot_password.html');
    }

    private function _handleForgotPassword() {
        // get request
        $request = $this->getRequest();

        // setup result array
        $rs = [];
        $rs['error'] = '';
        $rs['forgot_password_status'] = 'success';

        // clear any expired IPs
        BannedIpHelper::clearExpiredBannedIps();

        // do login
        $emailAddress = trim($request->request->get('emailAddress'));

        // initial validation
        if (strlen($rs['error']) === 0) {
            if (!strlen($emailAddress)) {
                // log failure
                $rs['error'] = TranslateHelper::t("please_enter_your_email_address", "Please enter the account email address");
                $rs['forgot_password_status'] = 'invalid';
            }
        }

        if (strlen($rs['error']) === 0) {
            $checkEmail = User::loadOne('email', $emailAddress);
            if (!$checkEmail) {
                // username exists
                $rs['error'] = TranslateHelper::t("account_not_found", "Account with that email address not found");
                $rs['forgot_password_status'] = 'invalid';
            }
        }

        // reset password
        if (strlen($rs['error']) === 0) {
            $userAccount = User::loadOne('email', $emailAddress);
            if ($userAccount) {
                // create password reset hash
                $resetHash = UserHelper::createPasswordResetHash($userAccount->id);

                $subject = TranslateHelper::t('forgot_password_email_subject', 'Password reset instructions for account on [[[SITE_NAME]]]', ['SITE_NAME' => SITE_CONFIG_SITE_NAME]);

                $replacements = [
                    'FIRST_NAME' => $userAccount->firstname,
                    'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                    'WEB_ROOT' => ThemeHelper::getLoadedInstance()->getAccountWebRoot(),
                    'USERNAME' => $userAccount->username,
                    'ACCOUNT_ID' => $userAccount->id,
                    'RESET_HASH' => $resetHash
                ];
                $defaultContent = "Dear [[[FIRST_NAME]]],<br/><br/>";
                $defaultContent .= "We've received a request to reset your password on [[[SITE_NAME]]] for account [[[USERNAME]]]. Follow the url below to set a new account password:<br/><br/>";
                $defaultContent .= "<a href='[[[WEB_ROOT]]]/forgot_password_reset?u=[[[ACCOUNT_ID]]]&h=[[[RESET_HASH]]]'>[[[WEB_ROOT]]]/forgot_password_reset?u=[[[ACCOUNT_ID]]]&h=[[[RESET_HASH]]]</a><br/><br/>";
                $defaultContent .= "If you didn't request a password reset, just ignore this email and your existing password will continue to work.<br/><br/>";
                $defaultContent .= "Regards,<br/>";
                $defaultContent .= "[[[SITE_NAME]]] Admin";
                $htmlMsg = TranslateHelper::t('forgot_password_email_content_v5', $defaultContent, $replacements);

                CoreHelper::sendHtmlEmail($emailAddress, $subject, $htmlMsg, SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM, strip_tags(str_replace("<br/>", "\n", $htmlMsg)));
                $rs['redirect_url'] = ThemeHelper::getLoadedInstance()->getAccountWebRoot() . "/forgot_password_confirm";
            }
        }

        return $rs;
    }

    public function ajaxForgotPassword() {
        $rs = $this->_handleForgotPassword();

        return $this->renderJson($rs);
    }

    public function forgotPasswordConfirm() {
        // load template
        return $this->render('account/forgot_password_confirm.html');
    }

    public function forgotPasswordReset() {
        // get params for later
        $Auth = $this->getAuth();

        // if user already logged in revert to account home
        if ($Auth->loggedIn()) {
            return $this->redirect(CoreHelper::getCoreSitePath());
        }

        // get request
        $request = $this->getRequest();

        // check for pending hash
        $userId = $request->query->has('u') ? (int) $request->query->get('u') : $request->request->get('u');
        $passwordHash = $request->query->has('h') ? $request->query->get('h') : $request->request->get('h');
        $user = UserHelper::loadUserByPasswordResetHash($passwordHash);
        if (!$user) {
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountWebRoot());
        }

        // check user id passed is valid
        if ($user->id != $userId) {
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountWebRoot());
        }

        // handle requests for login (non ajax)
        if ($request->request->has('submitme')) {
            // attempt login
            $rs = $this->_handleForgotPasswordReset();

            // success
            if ($rs['forgot_password_status'] === 'success') {
                return $this->redirect($rs['redirect_url']);
            }

            // setup error for template
            NotificationHelper::setError($rs['error']);
        }

        // load template
        return $this->render('account/forgot_password_reset.html', [
                    'userId' => $userId,
                    'passwordHash' => $passwordHash,
        ]);
    }

    public function ajaxForgotPasswordReset() {
        $rs = $this->_handleForgotPasswordReset();

        // render
        return $this->renderJson($rs);
    }

    private function _handleForgotPasswordReset() {
        // get request
        $request = $this->getRequest();

        // setup result array
        $rs = [];
        $rs['error'] = '';
        $rs['forgot_password_status'] = 'success';

        // validation
        $userId = (int) $request->request->get('u');
        $passwordHash = $request->request->get('h');
        $user = UserHelper::loadUserByPasswordResetHash($passwordHash);
        if (!$user) {
            $rs['error'] = TranslateHelper::t("account_details_not_found", "Account with them details not found");
            $rs['forgot_password_status'] = 'invalid';

            // render
            return $rs;
        }

        // make sure it matches our userId
        if ((int) $user->id !== $userId) {
            $rs['error'] = TranslateHelper::t("account_details_not_found", "Account with them details not found");
            $rs['forgot_password_status'] = 'invalid';

            // render
            return $rs;
        }

        // validate the submitted password
        $password = $request->request->get('password');
        $confirmPassword = $request->request->get('confirmPassword');
        if (!strlen($password)) {
            $rs['error'] = TranslateHelper::t("please_enter_your_password", "Please enter your new password");
            $rs['forgot_password_status'] = 'invalid';
        }
        elseif ($password != $confirmPassword) {
            $rs['error'] = TranslateHelper::t("password_confirmation_does_not_match", "Your password confirmation does not match");
            $rs['forgot_password_status'] = 'invalid';
        }
        else {
            $passValid = UserHelper::validatePassword($password);
            if (is_array($passValid)) {
                $rs['error'] = implode('<br/>', $passValid);
                $rs['forgot_password_status'] = 'invalid';
            }
        }

        // create the account
        if (strlen($rs['error']) === 0) {
            // load user and update password
            $user = User::loadOneById($userId);
            $user->passwordResetHash = '';
            $user->password = Password::createHash($password);
            $user->save();

            // success
            $rs['redirect_url'] = ThemeHelper::getLoadedInstance()->getAccountWebRoot() . "/login?s=1";
        }

        return $rs;
    }

    public function enable2fa() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // make sure 2fa is enabled in the site settings
        if (SITE_CONFIG_ENABLE_2FA !== 'yes') {
            return $this->render404(false);
        }

        // get auth
        $Auth = $this->getAuth();

        // load user for salt lookup, better not to use the session object here
        $user = User::loadOneById($Auth->id);

        // create QR code
        $secret = $user->get2FASalt();
        $qrCodeImageUrl = GoogleQrUrl::generate($Auth->user->username, $secret, rawurlencode(SITE_CONFIG_SITE_NAME), 200);

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['html'] = $this->getRenderedTemplate('account/ajax/enable_2fa.html', [
            'qrCodeImageUrl' => $qrCodeImageUrl,
        ]);

        // output response
        return $this->renderJson($result);
    }

    public function enable2faProcess() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // make sure 2fa is enabled in the site settings
        if (SITE_CONFIG_ENABLE_2FA !== 'yes') {
            return $this->render404(false);
        }

        // get auth and request
        $request = $this->getRequest();
        $Auth = $this->getAuth();

        // prepare result
        $result = [];
        $result['success'] = false;
        $result['msg'] = '';

        // validation
        if (!$request->request->has('confirmationCode') || strlen($request->request->get('confirmationCode')) !== 6) {
            $result['msg'] = TranslateHelper::t("settings_enable_2fa_enter_confirmation_code", "Please ensure you set the confirmation code and it's 6 characters in length.");

            return $this->renderJson($result);
        }
        elseif ($this->inDemoMode()) {
            $result['msg'] = TranslateHelper::t("no_changes_in_demo_mode");

            return $this->renderJson($result);
        }

        // load user for salt lookup, better not to use the session object here
        $user = User::loadOneById($Auth->id);

        // load authenticator class
        $secret = $user->get2FASalt();
        $g = new GoogleAuthenticator();

        // check submitted code
        if (!$g->checkCode($secret, $request->request->get('confirmationCode'))) {
            $result['msg'] = TranslateHelper::t("settings_enable_2fa_confirmation_code_invalid", "Confirmation code is invalid, please check and try again.");

            return $this->renderJson($result);
        }

        // valid code, enable 2FA on the account
        $user->login_2fa_enabled = 1;
        $user->save();

        // user action logs
        UserActionLogHelper::log('Enabled 2FA', 'ACCOUNT', 'UPDATE', [
            'user_id' => $user->id,
            'data' => [
                'login_2fa_enabled' => $user->login_2fa_enabled,
            ],
        ]);

        // confirm by response
        $result['success'] = true;
        $result['msg'] = TranslateHelper::t("settings_enable_2fa_has_been_enabled", "2FA has been enabled on your account. The next time you login you'll be prompted to enter a code from the Google Authenticator app.");

        // output response
        return $this->renderJson($result);
    }

    public function disable2faProcess() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // make sure 2fa is enabled in the site settings
        if (SITE_CONFIG_ENABLE_2FA !== 'yes') {
            return $this->render404(false);
        }

        // get auth and request
        $Auth = $this->getAuth();

        // set 2fa as disabled
        $user = User::loadOneById($Auth->id);
        $user->login_2fa_enabled = 0;
        $user->save();

        // user action logs
        UserActionLogHelper::log('Disabled 2FA', 'ACCOUNT', 'UPDATE', [
            'user_id' => $user->id,
            'data' => [
                'login_2fa_enabled' => $user->login_2fa_enabled,
            ],
        ]);

        // confirm by response
        $result = [];
        $result['success'] = true;
        $result['msg'] = TranslateHelper::t("settings_disable_2fa_has_been_disabled", "2FA has been disabled on your account.");

        // output response
        return $this->renderJson($result);
    }

}
