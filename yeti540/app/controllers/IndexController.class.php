<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Helpers\UserActionLogHelper;
use App\Models\FileFolder;
use App\Models\User;
use App\Helpers\CoreHelper;
use App\Helpers\InternalNotificationHelper;
use App\Helpers\LanguageHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UserHelper;
use App\Helpers\ValidationHelper;
use Symfony\Component\HttpFoundation\Cookie;
use App\Services\Password;

class IndexController extends BaseController
{

    public function index() {
        // normally overridden at theme level
        return $this->renderContent('Could not load theme index view, likely an issue with the configured site theme.');
    }

    public function register() {
        // get params for later
        $Auth = $this->getAuth();

        // if user already logged in and with a non-trial user, revert to account home
        if ($Auth->loggedIn() && $Auth->level_id > 0) {
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountWebRoot());
        }

        // pickup request for later
        $request = $this->getRequest();

        // check for previous cookie for 'non accounts'
        $trialUser = false;
        $cookieUsername = $request->cookies->has('trial_username') ? $request->cookies->get('trial_username') : null;
        $cookieHash = $request->cookies->has('trial_hash') ? $request->cookies->get('trial_hash') : null;
        if ($cookieUsername !== null && $cookieHash !== null) {
            // try to reload the account
            $newUser = User::loadOneByClause('username = :username AND identifier = :identifier '
                            . 'AND level_id = 0 AND status = "active"', [
                        'username' => $cookieUsername,
                        'identifier' => $cookieHash,
            ]);
            if ($newUser) {
                $trialUser = true;
            }
        }

        // make sure user registration is enabled
        if (SITE_CONFIG_ENABLE_USER_REGISTRATION === 'no') {
            NotificationHelper::setError(TranslateHelper::t("account_registration_disabled", "Account registration is disabled, please contact support for more information."));
        }

        // register user
        $title = '';
        $firstname = '';
        $lastname = '';
        $emailAddress = '';
        $username = '';
        if ($request->request->has('submitme')) {
            // validation
            $title = trim($request->request->get('title'));
            $firstname = trim($request->request->get('firstname'));
            $lastname = trim($request->request->get('lastname'));
            $emailAddress = trim(strtolower($request->request->get('emailAddress')));
            $username = trim(strtolower($request->request->get('username')));

            $newPassword = null;
            if (SITE_CONFIG_REGISTER_FORM_ALLOW_PASSWORD === 'yes') {
                $newPassword = trim($request->request->get('password'));
                $password2 = trim($request->request->get('password2'));
                $validPassword = UserHelper::validatePassword($newPassword);

                if ($newPassword !== $password2) {
                    NotificationHelper::setError(TranslateHelper::t("passwords_do_not_match", "Your passwords do not match."));
                }
                elseif (is_array($validPassword)) {
                    NotificationHelper::setError(implode('<br/>', $validPassword));
                }
            }

            if (!strlen($firstname)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_firstname", "Please enter your firstname"));
            }
            elseif (!strlen($lastname)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_lastname", "Please enter your lastname"));
            }
            elseif (!strlen($emailAddress)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_email_address", "Please enter your email address"));
            }
            elseif (!ValidationHelper::validEmail($emailAddress)) {
                NotificationHelper::setError(TranslateHelper::t("your_email_address_is_invalid", "Your email address is invalid"));
            }
            elseif (!strlen($username)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_preferred_username", "Please enter your preferred username"));
            }
            elseif ((strlen($username) < SITE_CONFIG_USERNAME_MIN_LENGTH) || (strlen($username) > SITE_CONFIG_USERNAME_MAX_LENGTH)) {
                NotificationHelper::setError(TranslateHelper::t("username_must_be_between_min_and_max_characters", "Your username must be between [[[MIN]]] and [[[MAX]]] characters", [
                            'MIN' => SITE_CONFIG_USERNAME_MIN_LENGTH,
                            'MAX' => SITE_CONFIG_USERNAME_MAX_LENGTH,
                ]));
            }
            elseif (!ValidationHelper::validUsername($username)) {
                NotificationHelper::setError(TranslateHelper::t("your_username_is_invalid", "Your username can only contact alpha numeric and underscores."));
            }
            else {
                $checkEmail = User::loadOneByClause('email = :email', ['email' => $emailAddress]);
                if ($checkEmail) {
                    // username exists
                    NotificationHelper::setError(TranslateHelper::t("email_address_already_exists", "Email address already exists on another account"));
                }
                else {
                    $checkUser = User::loadOneByClause('username = :username', ['username' => $username]);
                    if ($checkUser) {
                        // username exists
                        NotificationHelper::setError(TranslateHelper::t("username_already_exists", "Username already exists on another account"));
                    }
                }
            }

            // make sure the username is not reserved
            if (!NotificationHelper::isErrors()) {
                if (strlen(SITE_CONFIG_RESERVED_USERNAMES)) {
                    $reservedUsernames = explode("|", SITE_CONFIG_RESERVED_USERNAMES);
                    if (in_array($username, $reservedUsernames)) {
                        // username is reserved
                        NotificationHelper::setError(TranslateHelper::t("username_is_reserved", "Username is reserved and can not be used, please choose another"));
                    }
                }
            }

            // make sure the email domain isn't banned
            if (!NotificationHelper::isErrors()) {
                if (strlen(SITE_CONFIG_SECURITY_BLOCK_REGISTER_EMAIL_DOMAIN)) {
                    $blockedEmailDomains = explode(",", SITE_CONFIG_SECURITY_BLOCK_REGISTER_EMAIL_DOMAIN);
                    $emailDomain = strtolower(end(explode('@', $emailAddress)));
                    if (in_array($emailDomain, $blockedEmailDomains)) {
                        // email domain is not allowed
                        NotificationHelper::setError(TranslateHelper::t("email_address_not_allowed", "Registration from email addresses on [[[EMAIL_DOMAIN]]] have been blocked on this site.", ['EMAIL_DOMAIN' => $emailDomain]));
                    }
                }
            }

            // check captcha
            if ((!NotificationHelper::isErrors()) && (SITE_CONFIG_REGISTER_FORM_SHOW_CAPTCHA == 'yes')) {
                $rs = CoreHelper::captchaCheck();
                if (!$rs) {
                    NotificationHelper::setError(TranslateHelper::t("invalid_captcha", "Captcha confirmation text is invalid."));
                }
            }

            // standardise title if invalid
            if (strlen($title)) {
                if (!in_array($title, UserHelper::getUserTitles())) {
                    $title = '';
                }
            }

            // create the account
            if (!NotificationHelper::isErrors()) {
                // create new password
                if (!$newPassword) {
                    $newPassword = UserHelper::generatePassword();
                }

                // if this is a trial account, convert it
                if ($trialUser === true) {
                    // figure out the account status
                    $status = 'active';
                    if (SITE_CONFIG_ADMIN_APPROVE_REGISTRATIONS === 'yes') {
                        $status = 'awaiting approval';
                    }

                    // figure out the privacy level
                    $isPublic = 0;
                    if (SITE_CONFIG_NEW_ACCOUNT_DEFAULT_FILE_PRIVACY === 'Public') {
                        $isPublic = 1;
                    }

                    // update account
                    $newUser->level_id = 1;
                    $newUser->username = $username;
                    $newUser->password = Password::createHash($newPassword);
                    $newUser->firstname = $firstname;
                    $newUser->lastname = $lastname;
                    $newUser->email = $emailAddress;
                    $newUser->status = $status;
                    $newUser->identifier = md5(time() . $username . $newPassword);
                    $newUser->isPublic = $isPublic;
                    $newUser->save();

                    // user action logs
                    UserActionLogHelper::log('Trial account converted to free', 'ACCOUNT', 'UPDATE', [
                        'user_id' => $newUser->id,
                        'data' => [
                            'username' => $username,
                        ],
                    ]);

                    // send approval email
                    if($newUser->status == 'awaiting approval') {
                        UserHelper::notifyAdminPendingUser($newUser);
                    }

                    // make sure we logout trial accounts
                    $Auth->logout();
                }
                else {
                    // otherwise create a new account
                    $newUser = UserHelper::create($username, $newPassword, $emailAddress, $title, $firstname, $lastname);
                }

                // send email notification
                if ($newUser) {
                    $subject = TranslateHelper::t('register_user_email_subject', 'Account details for [[[SITE_NAME]]]', ['SITE_NAME' => SITE_CONFIG_SITE_NAME]);

                    $replacements = [
                        'FIRST_NAME' => $firstname,
                        'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                        'WEB_ROOT' => ThemeHelper::getLoadedInstance()->getAccountWebRoot(),
                        'USERNAME' => $username,
                        'PASSWORD' => $newPassword
                    ];
                    $defaultContent = "Dear [[[FIRST_NAME]]],<br/><br/>";
                    $defaultContent .= "Your account on [[[SITE_NAME]]] has been created. Use the details below to login to your new account:<br/><br/>";
                    $defaultContent .= "<strong>Url:</strong> <a href='[[[WEB_ROOT]]]'>[[[WEB_ROOT]]]</a><br/>";
                    $defaultContent .= "<strong>Username:</strong> [[[USERNAME]]]<br/>";
                    $defaultContent .= "<strong>Password:</strong> [[[PASSWORD]]]<br/><br/>";
                    $defaultContent .= "Feel free to contact us if you need any support with your account.<br/><br/>";
                    $defaultContent .= "Regards,<br/>";
                    $defaultContent .= "[[[SITE_NAME]]] Admin";
                    $htmlMsg = TranslateHelper::t('register_user_email_content', $defaultContent, $replacements);

                    CoreHelper::sendHtmlEmail($emailAddress, $subject, $htmlMsg, SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM, strip_tags(str_replace("<br/>", "\n", $htmlMsg)));

                    // create account welcome notification
                    $content = TranslateHelper::t('register_account_notification_text', 'Thanks for registering and welcome to your account! Start uploading files straight away by clicking the \'Upload\' button below. Feel free to contact us if you need any help.');
                    $link = WEB_ROOT . '/account';
                    InternalNotificationHelper::add($newUser->id, $content, 'entypo-thumbs-up', $link);

                    // user action logs
                    UserActionLogHelper::log('Account registered', 'ACCOUNT', 'ADD', [
                        'user_id' => $newUser->id,
                        'data' => [
                            'type' => 'free',
                            'username' => $username,
                        ],
                    ]);

                    // confirmation page
                    return $this->redirect(WEB_ROOT . '/register_complete' . ($trialUser === true ? '?trial=1' : ''));
                }
                else {
                    NotificationHelper::setError(TranslateHelper::t("problem_creating_your_account_try_again_later", "There was a problem creating your account, please try again later"));
                }
            }
        }

        // load template
        return $this->render('register.html', [
                    'title' => $title,
                    'titleOptions' => UserHelper::getUserTitles(),
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'emailAddress' => $emailAddress,
                    'username' => $username,
                    'trialUser' => $trialUser,
                    'HookLoginLoginBoxHtml' => PluginHelper::outputHook('loginLoginBox'),
        ]);
    }

    public function registerComplete() {
        // make sure user registration is enabled
        if (SITE_CONFIG_ENABLE_USER_REGISTRATION === 'no') {
            return $this->redirect(WEB_ROOT);
        }

        // get params for later
        $Auth = $this->getAuth();
        $request = $this->getRequest();

        // if user already logged in revert to account home
        if ($Auth->loggedIn()) {
            return $this->redirect(CoreHelper::getCoreSitePath());
        }

        // load template
        return $this->render('register_complete.html', [
                    'isTrial' => $request->query->has('trial'),
        ]);
    }

    public function terms() {
        // load template
        return $this->render('terms.html');
    }

    public function privacy() {
        // load template
        return $this->render('privacy.html');
    }

    public function error() {
        // pickup request for later
        $request = $this->getRequest();

        // make safe the error message
        if ($request->query->has('e')) {
            $errorMsg = $request->query->get('e');
            $errorMsg = urldecode($errorMsg);
            $errorMsg = strip_tags($errorMsg);
            $errorMsg = str_replace(['"', '\'', ';'], '', $errorMsg);
        }
        else {
            $errorMsg = TranslateHelper::t("general_site_error", "There has been an error, please try again later.");
        }

        // load template
        return $this->render('error.html', [
                    'error_msg' => $errorMsg,
        ]);
    }

    public function setLanguage($languageName) {
        // get database connection
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // make sure the one passed is an active language
        $language = $db->getRow("SELECT languageName, flag "
                . "FROM language "
                . "WHERE isActive = 1 AND languageName = :languageName "
                . "LIMIT 1", [
            'languageName' => $languageName,
        ]);
        if ($language) {
            $_SESSION['_t'] = $language['languageName'];
            $_SESSION['_tFlag'] = $language['flag'];
        }
        else {
            $_SESSION['_t'] = SITE_CONFIG_SITE_LANGUAGE;
            $_SESSION['_tFlag'] = LanguageHelper::loadFlagFromLanguageName(SITE_CONFIG_SITE_LANGUAGE);
        }
        PluginHelper::reloadSessionPluginConfig();

        // redirect to index page
        return $this->redirect(WEB_ROOT);
    }

    public function jsTranslations() {
        // create missing translations for javascript
        TranslateHelper::t('selected_file', 'selected file');

        // output js translations
        header('Content-Type: application/javascript');
        echo TranslateHelper::generateJSLanguageCode();
        exit;
    }

    public function folderPasswordProcess() {
        // pickup request for later
        $request = $this->getRequest();

        // validation
        $folderId = (int) $request->request->get('folderId');
        $folderPassword = trim($request->request->get('folderPassword'));

        // load folder
        $fileFolder = FileFolder::loadOneById($folderId);
        if (!$fileFolder) {
            NotificationHelper::setError(TranslateHelper::t("problem_loading_folder", "There was a problem loading the folder, please try again later."));
        }

        // check password
        if (!NotificationHelper::isErrors()) {
            if (md5($folderPassword) == $fileFolder->accessPassword) {
                // successful
                if (!isset($_SESSION['folderPassword'])) {
                    $_SESSION['folderPassword'] = [];
                }
                $_SESSION['folderPassword'][$fileFolder->id] = $fileFolder->accessPassword;
            }
            else {
                NotificationHelper::setError(TranslateHelper::t("folder_password_is_invalid", "The folder password is invalid"));
            }
        }

        // prepare result
        $returnJson = [];
        $returnJson['success'] = false;
        $returnJson['msg'] = TranslateHelper::t("problem_updating_folder", "There was a problem accessing the folder, please try again later.");
        if (NotificationHelper::isErrors()) {
            // error
            $returnJson['success'] = false;
            $returnJson['msg'] = implode('<br/>', NotificationHelper::getErrors());
        }
        else {
            // success
            $returnJson['success'] = true;
            $returnJson['msg'] = implode('<br/>', NotificationHelper::getSuccess());
        }

        // output response
        return $this->renderJson($returnJson);
    }

    public function downForMaintenance() {
        // ignore maintenance mode to avoid continuous loops
        define('IGNORE_MAINTENANCE_MODE', true);

        // load template
        return $this->render('down_for_maintenance.html');
    }

    public function registerNonUser() {
        // get Auth for later
        $Auth = $this->getAuth();
        $request = $this->getRequest();

        // make sure user registration is enabled
        if (UserHelper::getAllowedToUpload(0) === false || SITE_CONFIG_ENABLE_USER_REGISTRATION === 'no') {
            return $this->redirect(WEB_ROOT);
        }

        // if user already logged in revert to account home
        if ($Auth->loggedIn()) {
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountWebRoot());
        }

        // check for previous cookie for 'non accounts'
        $user = false;
        $cookieUsername = $request->cookies->has('trial_username') ? $request->cookies->get('trial_username') : null;
        $cookieHash = $request->cookies->has('trial_hash') ? $request->cookies->get('trial_hash') : null;
        if ($cookieUsername !== null && $cookieHash !== null) {
            // try to reload the account
            $user = User::loadOneByClause('username = :username AND identifier = :identifier '
                            . 'AND level_id = 0 AND status = "active"', [
                        'username' => $cookieUsername,
                        'identifier' => $cookieHash,
            ]);
        }

        // create new 'non account' account
        if (!$user) {
            $username = 'trial_' . CoreHelper::generateRandomHash();
            $password = UserHelper::generatePassword();
            $user = UserHelper::create($username, $password, null, null, null, null, 0);
            if (!$user) {
                return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode(TranslateHelper::t('failed_creating_temporary_account', 'Failed creating temporary account to upload files, please try again later.')));
            }

            // user action logs
            UserActionLogHelper::log('Account registered', 'ACCOUNT', 'ADD', [
                'user_id' => $user->id,
                'data' => [
                    'type' => 'trial',
                    'username' => $username,
                ],
            ]);
        }

        // login as new user
        $Auth->impersonate($user->id, null, true);

        // set account message
        $content = TranslateHelper::t('register_account_trial_user_notification_text', 'We\'ve created you a trial account for uploading files. This is not linked to an account email address and any uploads will be publicly accessible. To enable private uploads and to retain access to your files, click here to fully register for an account.');
        $link = WEB_ROOT . '/register';
        InternalNotificationHelper::add($user->id, $content, 'entypo-thumbs-up', $link);

        // redirect to account home, triggering the upload box
        $response = $this->redirect(ThemeHelper::getLoadedInstance()->getAccountWebRoot() . '?triggerUpload=1');

        // set cookies for 'non accounts'
        $response->headers->setCookie(new Cookie('trial_username', $user->username, strtotime('+1 month')));
        $response->headers->setCookie(new Cookie('trial_hash', $user->identifier, strtotime('+1 month')));

        return $response;
    }

    public function jsAdblock() {
        // output adblock JS
        $response = $this->render('adblock_js.html');
        $response->headers->set('Content-Type', 'application/javascript');
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('max-age', 0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);

        return $response;
    }
}
