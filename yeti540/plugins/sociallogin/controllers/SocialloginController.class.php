<?php

namespace Plugins\Sociallogin\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Models\File;
use App\Models\User;
use App\Helpers\AuthHelper;
use App\Helpers\CacheHelper;
use App\Helpers\CoreHelper;
use App\Helpers\PluginHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UserHelper;
use App\Services\Password;
use Hybridauth\Hybridauth;
use Spatie\Image\Image;

class SocialloginController extends BaseController
{

    /**
     * The initial entry point for social logins but also the callback after
     * remote authentication.
     * 
     * @param type $params
     * @return type
     */
    public function login($provider = null) {
        // get request
        $request = $this->getRequest();

        // make sure we have the provider
        if ($provider !== null) {
            try {
                // create an instance for Hybridauth with the configuration
                $config = $this->getProviderConfig($provider);
                $hybridauth = new Hybridauth($config);

                // try to authenticate the selected $provider
                $adapter = $hybridauth->authenticate($provider);

                // returns a boolean of whether the user is connected with the $provider
                $isConnected = $adapter->isConnected();

                // log the user in if we're connected
                if ($isConnected === true) {
                    return $this->processUserLogin($adapter, $provider);
                }
            }
            catch (\Exception $e) {
                // In case we have errors 6 or 7, then we have to use Hybrid_Provider_Adapter::logout() to 
                // let hybridauth forget all about the user so we can try to authenticate again.
                // Display the recived error, 
                // to know more please refer to Exceptions handling section on the userguide
                switch ($e->getCode()) {
                    case 0 : $error = TranslateHelper::t('plugin_sociallogin_unspecified_error', 'Unspecified error');
                        break;
                    case 1 : $error = TranslateHelper::t('plugin_sociallogin_hybriauth_configuration_error', 'Hybriauth configuration error');
                        break;
                    case 2 : $error = TranslateHelper::t('plugin_sociallogin_provider_not_properly_configured', 'Provider not properly configured');
                        break;
                    case 3 : $error = TranslateHelper::t('plugin_sociallogin_unknown_or_disabled_provider', 'Unknown or disabled provider');
                        break;
                    case 4 : $error = TranslateHelper::t('plugin_sociallogin_missing_provider_application_credentials', 'Missing provider application credentials');
                        break;
                    case 5 : $error = TranslateHelper::t('plugin_sociallogin_authentication_failed_the_user_has_canceled_the_authentication_or_the_provider_refused_the_connection', 'Authentication failed. The user has canceled the authentication or the provider refused the connection');
                        break;
                    case 6 : $error = TranslateHelper::t('plugin_sociallogin_user_profile_request_failed_most_likely_the_user_is_not_connected_to_the_provider_and_he_should_to_authenticate_again', 'User profile request failed. Most likely the user is not connected to the provider and he should to authenticate again');
                        $adapter->logout();
                        break;
                    case 7 : $error = TranslateHelper::t('plugin_sociallogin_user_not_connected_to_the_provider', 'User not connected to the provider');
                        $adapter->logout();
                        break;
                }
            }
        }

        $Auth = AuthHelper::getAuth();
        if ($Auth->loggedIn() && $Auth->level_id > 0) {
            return $this->redirect(CoreHelper::getCoreSitePath() . '/account');
        }

        // pickup errors
        if ($request->query->has('error_message') && strlen($request->query->get('error_message'))) {
            $error = $request->query->get('error_message');
        }
        elseif ($request->query->has('error_description') && strlen($request->query->get('error_description'))) {
            $error = $request->query->get('error_description');
        }
        else {
            $error = TranslateHelper::t('plugin_sociallogin_hybriauth_general_error', 'General error logging into your account, please try again later or use another method.');
        }

        return $this->redirect(CoreHelper::getCoreSitePath() . '/account/login?plugin_social_login_error=' . urlencode($error));
    }

    private function processUserLogin($adapter, $provider) {
        // get the user profile
        $userProfileData = $adapter->getUserProfile();

        // setup changes to user session and login user
        if ($userProfileData !== false) {
            // generate username based on provider and user id
            $username = $provider . '|' . $userProfileData->identifier;

            // attempt to load user from db
            $db = Database::getDatabase();
            $Auth = $this->getAuth();
            $request = $this->getRequest();

            // check if user exists
            $user = User::loadOne('username', $username);
            if (!$user) {
                // if user not found, create new
                $newPassword = UserHelper::generatePassword();
                $emailAddress = $userProfileData->email;
                $title = null;
                $firstname = strlen($userProfileData->firstName) ? $userProfileData->firstName : null;
                $lastname = strlen($userProfileData->lastName) ? $userProfileData->lastName : null;

                // check for previous cookie for 'non accounts'
                $trialUser = false;
                $cookieUsername = $request->cookies->has('trial_username') ? $request->cookies->get('trial_username') : null;
                $cookieHash = $request->cookies->has('trial_hash') ? $request->cookies->get('trial_hash') : null;
                if ($cookieUsername !== null && $cookieHash !== null) {
                    // try to reload the account
                    $user = User::loadOneByClause('username = :username AND identifier = :identifier '
                                    . 'AND level_id = 0 AND status = "active"', array(
                                'username' => $cookieUsername,
                                'identifier' => $cookieHash,
                    ));
                    if ($user) {
                        $trialUser = true;
                    }
                }

                // if this is a trial account, convert it
                if ($trialUser === true) {
                    // figure out the account status
                    $status = 'active';
                    if (SITE_CONFIG_ADMIN_APPROVE_REGISTRATIONS === 'yes') {
                        $status = 'awaiting approval';
                    }

                    // update account
                    $user->level_id = 1;
                    $user->username = $username;
                    $user->password = Password::createHash($newPassword);
                    ;
                    $user->firstname = $firstname;
                    $user->lastname = $lastname;
                    $user->email = $emailAddress;
                    $user->status = $status;
                    $user->identifier = md5(time() . $username . $password);
                    $user->save();

                    // make sure we logout trial accounts
                    $Auth->logout();
                }
                else {
                    // otherwise create a new account
                    $user = UserHelper::create($username, $newPassword, $emailAddress, $title, $firstname, $lastname);
                }
            }
            else {
                // make sure the user has not been disabled
                if ($user->status !== 'active') {
                    $error = TranslateHelper::t('plugin_sociallogin_hybriauth_account_not_active', 'Account not active.');

                    return $this->redirect(CoreHelper::getCoreSitePath() . '/account/login?plugin_social_login_error=' . urlencode($error));
                }

                // ensure user is updated
                $user->firstname = strlen($userProfileData->firstName) ? $userProfileData->firstName : null;
                $user->lastname = strlen($userProfileData->lastName) ? $userProfileData->lastName : null;
                $user->email = $userProfileData->email;
                $user->save();
            }

            // success
            if ($user) {
                // clear any existing avatar cache so it's recached
                $avatarCachePath = 'user/' . (int) $user->id . '/profile';
                CacheHelper::removeCacheSubFolder($avatarCachePath);

                // locally save avatar
                if (strlen($userProfileData->photoURL)) {
                    // save contents of photoURL locally
                    CoreHelper::getRemoteUrlContent($userProfileData->photoURL, CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath . '/avatar_original.png');
                }

                // if we're expecting 2FA, redirect
                // check for 2fa, if enabled
                if (SITE_CONFIG_ENABLE_2FA === 'yes' && (int) $user->login_2fa_enabled === 1) {
                    // store username and password for later login
                    $_SESSION['_2faUser'] = array(
                        'username' => $username,
                        'socialLogin' => true,
                        'socialProvider' => $provider,
                        'socialData' => serialize($userProfileData),
                    );

                    return $this->redirect(WEB_ROOT . '/account/login_2fa');
                }

                // no 2fa, setup session
                $Auth = AuthHelper::getAuth();
                $Auth->impersonate($username);
                if ($Auth->loggedIn() == true) {
                    $_SESSION['socialLogin'] = true;
                    $_SESSION['socialProvider'] = $provider;
                    $_SESSION['socialData'] = serialize($userProfileData);
                }

                // redirect to account home
                return $this->redirect(CoreHelper::getCoreSitePath() . '/account');
            }
        }

        // failed, redirect to the login page
        $error = TranslateHelper::t('plugin_sociallogin_hybriauth_general_error', 'General error logging into your account, please try again later or use another method.');

        return $this->redirect(CoreHelper::getCoreSitePath() . '/account/login?plugin_social_login_error=' . urlencode($error));
    }

    private function getProviderConfig($provider) {
        // load plugin details
        $pluginDetails = PluginHelper::pluginSpecificConfiguration('sociallogin');
        $pluginConfig = $pluginDetails['config'];
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);

        // make sure the logs folder exists
        $logFolder = LOCAL_SITE_CONFIG_BASE_LOG_PATH . 'plugin_sociallogin';
        if (_CONFIG_DEBUG === true) {
            if (!file_exists($logFolder)) {
                mkdir($logFolder);
            }
        }

        // compile config
        $config = [];
        $config['base_url'] = WEB_ROOT;
        $config['callback'] = PLUGIN_WEB_ROOT . '/sociallogin/login/' . $provider;
        $config['debug_mode'] = _CONFIG_DEBUG === true ? true : false;
        $config['debug_file'] = $logFolder . '/' . date('Ymd') . '.txt';
        $config['providers'] = [];

        // add provider specific config
        switch ($provider) {
            case 'Facebook';
                if ((int) $pluginSettings['facebook_enabled'] == 1) {
                    $config['providers']['Facebook'] = array(
                        'enabled' => true,
                        'keys' => array(
                            'id' => $pluginSettings['facebook_application_id'],
                            'secret' => $pluginSettings['facebook_application_secret'],
                        ),
                        'scope' => 'email',
                        'trustForwarded' => false,
                    );
                }
                break;
            case 'Twitter';
                if ((int) $pluginSettings['twitter_enabled'] == 1) {
                    $config['providers']['Twitter'] = array(
                        'enabled' => true,
                        'keys' => array(
                            'key' => $pluginSettings['twitter_application_key'],
                            'secret' => $pluginSettings['twitter_application_secret'],
                        ),
                    );
                }
                break;
            case 'Google';
                if ((int) $pluginSettings['google_enabled'] == 1) {
                    $config['providers']['Google'] = array(
                        'enabled' => true,
                        'keys' => array(
                            'id' => $pluginSettings['google_application_id'],
                            'secret' => $pluginSettings['google_application_secret'],
                        ),
                    );
                }
                break;
            case 'LinkedIn';
                if ((int) $pluginSettings['linkedin_enabled'] == 1) {
                    $config['providers']['LinkedIn'] = array(
                        'enabled' => true,
                        'keys' => array(
                            'id' => $pluginSettings['linkedin_application_key'],
                            'secret' => $pluginSettings['linkedin_application_secret'],
                        ),
                        'scope' => 'r_liteprofile r_emailaddress',
                    );
                }
                break;
            case 'Foursquare';
                if ((int) $pluginSettings['foursquare_enabled'] == 1) {
                    $config['providers']['Foursquare'] = array(
                        'enabled' => true,
                        'keys' => array(
                            'id' => $pluginSettings['foursquare_application_id'],
                            'secret' => $pluginSettings['foursquare_application_secret'],
                        ),
                    );
                }
                break;
        }

        return $config;
    }

}
