<?php

namespace Plugins\Sociallogin\Controllers\Admin;

use App\Core\Database;
use App\Controllers\Admin\PluginController AS CorePluginController;
use App\Helpers\AdminHelper;
use App\Helpers\UserActionLogHelper;
use App\Models\Plugin;

class PluginController extends CorePluginController
{

    public function pluginSettings() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'sociallogin';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', array(
                    'folder_name' => $folderName,
        ));

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        // prepare variables
        $plugin_enabled = (int) $plugin->plugin_enabled;
        $facebook_enabled = 0;
        $facebook_application_id = '';
        $facebook_application_secret = '';
        $twitter_enabled = 0;
        $twitter_application_key = '';
        $twitter_application_secret = '';
        $google_enabled = 0;
        $google_application_id = '';
        $google_application_secret = '';
        $aol_enabled = 0;
        $instagram_enabled = 0;
        $instagram_application_key = '';
        $instagram_application_secret = '';
        $foursquare_enabled = 0;
        $foursquare_application_id = '';
        $foursquare_application_secret = '';
        $linkedin_enabled = 0;
        $linkedin_application_key = '';
        $linkedin_application_secret = '';

        // load existing settings
        if (strlen($plugin->plugin_settings)) {
            $plugin_settings = json_decode($plugin->plugin_settings, true);
            if ($plugin_settings) {
                $facebook_enabled = (int)$plugin_settings['facebook_enabled'];
                $facebook_application_id = $plugin_settings['facebook_application_id'];
                $twitter_enabled = (int)$plugin_settings['twitter_enabled'];
                $twitter_application_key = $plugin_settings['twitter_application_key'];
                $google_enabled = (int)$plugin_settings['google_enabled'];
                $google_application_id = $plugin_settings['google_application_id'];
                $foursquare_enabled = (int)$plugin_settings['foursquare_enabled'];
                $foursquare_application_id = $plugin_settings['foursquare_application_id'];
                $linkedin_enabled = (int)$plugin_settings['linkedin_enabled'];
                $linkedin_application_key = $plugin_settings['linkedin_application_key'];

                // hide secret keys in demo mode
                if ($this->inDemoMode()) {
                    $twitter_application_secret = '[hidden]';
                    $google_application_secret = '[hidden]';
                    $instagram_application_secret = '[hidden]';
                    $foursquare_application_secret = '[hidden]';
                    $facebook_application_secret = '[hidden]';
                    $linkedin_application_secret = '[hidden]';
                }
                else {
                    $twitter_application_secret = $plugin_settings['twitter_application_secret'];
                    $google_application_secret = $plugin_settings['google_application_secret'];
                    $instagram_application_secret = $plugin_settings['instagram_application_secret'];
                    $foursquare_application_secret = $plugin_settings['foursquare_application_secret'];
                    $facebook_application_secret = $plugin_settings['facebook_application_secret'];
                    $linkedin_application_secret = $plugin_settings['linkedin_application_secret'];
                }
            }
        }

        // handle page submissions
        if($request->request->has('submitted')) {
            // get variables
            $oldPluginSettings = json_decode($plugin->plugin_settings, true);
            $plugin_enabled = (int) $request->request->get('plugin_enabled');
            $plugin_enabled = $plugin_enabled != 1 ? 0 : 1;
            $facebook_enabled = (int) $request->request->get('facebook_enabled');
            $facebook_application_id = trim($request->request->get('facebook_application_id'));
            $facebook_application_secret = trim($request->request->get('facebook_application_secret'));
            $twitter_enabled = (int) $request->request->get('twitter_enabled');
            $twitter_application_key = trim($request->request->get('twitter_application_key'));
            $twitter_application_secret = trim($request->request->get('twitter_application_secret'));
            $google_enabled = (int) $request->request->get('google_enabled');
            $google_application_id = trim($request->request->get('google_application_id'));
            $google_application_secret = trim($request->request->get('google_application_secret'));
            $foursquare_enabled = (int) $request->request->get('foursquare_enabled');
            $foursquare_application_id = trim($request->request->get('foursquare_application_id'));
            $foursquare_application_secret = trim($request->request->get('foursquare_application_secret'));
            $linkedin_enabled = (int) $request->request->get('linkedin_enabled');
            $linkedin_application_key = trim($request->request->get('linkedin_application_key'));
            $linkedin_application_secret = trim($request->request->get('linkedin_application_secret'));

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            if ((AdminHelper::isErrors() === false) && ($facebook_enabled === 1)) {
                // validation
                if (strlen($facebook_application_id) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_facebook_application_id", "Please set the Facebook application id."));
                }
                elseif (strlen($facebook_application_secret) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_facebook_application_secret", "Please set the Facebook application secret."));
                }
            }
            if ((AdminHelper::isErrors() === false) && ($twitter_enabled === 1)) {
                // validation
                if (strlen($twitter_application_key) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_twitter_application_key", "Please set the Twitter application key."));
                }
                elseif (strlen($twitter_application_secret) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_twitter_application_secret", "Please set the Twitter application secret."));
                }
            }
            if ((AdminHelper::isErrors() === false) && ($google_enabled === 1)) {
                // validation
                if (strlen($google_application_id) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_google_application_id", "Please set the Google application id."));
                }
                elseif (strlen($google_application_secret) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_google_application_secret", "Please set the Google application secret."));
                }
            }
            if ((AdminHelper::isErrors() === false) && ($foursquare_enabled === 1)) {
                // validation
                if (strlen($foursquare_application_id) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_foursquare_application_id", "Please set the Disqus application id."));
                }
                elseif (strlen($foursquare_application_secret) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_foursquare_application_secret", "Please set the Disqus application secret."));
                }
            }
            if ((AdminHelper::isErrors() === false) && ($linkedin_enabled === 1)) {
                // validation
                if (strlen($linkedin_application_key) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_linkedin_application_key", "Please set the LinkedIn application key."));
                }
                elseif (strlen($linkedin_application_secret) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_sociallogin_set_linkedin_application_secret", "Please set the LinkedIn application secret."));
                }
            }

            // update the settings
            if (AdminHelper::isErrors() === false) {
                // compile new settings
                $settingsArr = [];
                $settingsArr['facebook_enabled'] = $facebook_enabled;
                $settingsArr['facebook_application_id'] = $facebook_application_id;
                $settingsArr['facebook_application_secret'] = $facebook_application_secret;
                $settingsArr['twitter_enabled'] = $twitter_enabled;
                $settingsArr['twitter_application_key'] = $twitter_application_key;
                $settingsArr['twitter_application_secret'] = $twitter_application_secret;
                $settingsArr['google_enabled'] = $google_enabled;
                $settingsArr['google_application_id'] = $google_application_id;
                $settingsArr['google_application_secret'] = $google_application_secret;
                $settingsArr['aol_enabled'] = $aol_enabled;
                $settingsArr['instagram_enabled'] = $instagram_enabled;
                $settingsArr['foursquare_enabled'] = $foursquare_enabled;
                $settingsArr['foursquare_application_id'] = $foursquare_application_id;
                $settingsArr['foursquare_application_secret'] = $foursquare_application_secret;
                $settingsArr['linkedin_enabled'] = $linkedin_enabled;
                $settingsArr['linkedin_application_key'] = $linkedin_application_key;
                $settingsArr['linkedin_application_secret'] = $linkedin_application_secret;

                // update the plugin settings
                $plugin->plugin_enabled = $plugin_enabled;
                $plugin->plugin_settings = json_encode($settingsArr);
                $plugin->save();

                // user action logs
                UserActionLogHelper::logAdmin('Edited "'.$folderName.'" plugin settings', 'ADMIN', 'UPDATE', [
                    'plugin' => $folderName,
                    'data' => UserActionLogHelper::getChangedData($oldPluginSettings, $settingsArr),
                ]);

                // set onscreen alert
                AdminHelper::setSuccess('Plugin settings updated.');
            }
        }
        else {
            // check for curl
            if (function_exists('curl_version') == false) {
                AdminHelper::setError(AdminHelper::t("plugin_sociallogin_curl_required", "Could not find Curl functions in your PHP configuration. Please contact your host to enable Curl otherwise this plugin wont work."));
            }

            // ensure site is on https
            if (_CONFIG_SITE_PROTOCOL !== 'https') {
                AdminHelper::setError(AdminHelper::t("plugin_sociallogin_https_required", "Most sites now require https otherwise they wont allow user logins via their service. Please ensure your site is running on https."));
            }
        }

        // load template
        return $this->render('admin/plugin_settings.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'yesNoOptions' => array(0 => 'No', 1 => 'Yes'),
                    'plugin_enabled' => $plugin_enabled,
                    'facebook_enabled' => $facebook_enabled,
                    'facebook_application_id' => $facebook_application_id,
                    'facebook_application_secret' => $facebook_application_secret,
                    'twitter_enabled' => $twitter_enabled,
                    'twitter_application_key' => $twitter_application_key,
                    'twitter_application_secret' => $twitter_application_secret,
                    'google_enabled' => $google_enabled,
                    'google_application_id' => $google_application_id,
                    'google_application_secret' => $google_application_secret,
                    'aol_enabled' => $aol_enabled,
                    'foursquare_enabled' => $foursquare_enabled,
                    'foursquare_application_id' => $foursquare_application_id,
                    'foursquare_application_secret' => $foursquare_application_secret,
                    'linkedin_enabled' => $linkedin_enabled,
                    'linkedin_application_key' => $linkedin_application_key,
                    'linkedin_application_secret' => $linkedin_application_secret,
                                ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

}
