<?php

namespace Plugins\Paypal\Controllers\Admin;

use App\Core\Database;
use App\Controllers\Admin\PluginController AS CorePluginController;
use App\Helpers\AdminHelper;
use App\Helpers\PluginHelper;
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
        $folderName = 'paypal';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', array(
                    'folder_name' => $folderName,
        ));

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        // prepare variables
        $plugin_enabled = (int) $plugin->plugin_enabled;
        $paypal_email = 'paypal@yoursite.com';
        $enable_sandbox_mode = 0;
        $enable_subscriptions = 0;

        // load existing settings
        if (strlen($plugin->plugin_settings)) {
            $plugin_settings = json_decode($plugin->plugin_settings, true);
            if ($plugin_settings) {
                $paypal_email = $plugin_settings['paypal_email'];
                $enable_sandbox_mode = (int) $plugin_settings['enable_sandbox_mode'];
                $enable_subscriptions = (int) $plugin_settings['enable_subscriptions'];
            }
        }

        // handle page submissions
        if ($request->request->has('submitted')) {
            // get variables
            $oldPluginSettings = json_decode($plugin->plugin_settings, true);
            $plugin_enabled = (int) $request->request->get('plugin_enabled');
            $plugin_enabled = $plugin_enabled != 1 ? 0 : 1;
            $paypal_email = trim(strtolower($request->request->get('paypal_email')));
            $enable_sandbox_mode = (int) $request->request->get('enable_sandbox_mode');
            $enable_subscriptions = (int) $request->request->get('enable_subscriptions');

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t('no_changes_in_demo_mode', 'No change permitted in demo mode.'));
            }

            // update the settings
            if (AdminHelper::isErrors() == false) {
                // compile new settings
                $settingsArr = [];
                $settingsArr['paypal_email'] = $paypal_email;
                $settingsArr['enable_sandbox_mode'] = $enable_sandbox_mode;
                $settingsArr['enable_subscriptions'] = $enable_subscriptions;

                // update the plugin settings
                $plugin->plugin_enabled = $plugin_enabled;
                $plugin->plugin_settings = json_encode($settingsArr);
                $plugin->save();

                // set onscreen alert
                PluginHelper::loadPluginConfigurationFiles(true);

                // user action logs
                UserActionLogHelper::logAdmin('Edited "'.$folderName.'" plugin settings', 'ADMIN', 'UPDATE', [
                    'plugin' => $folderName,
                    'data' => UserActionLogHelper::getChangedData($oldPluginSettings, $settingsArr),
                ]);
                
                AdminHelper::setSuccess('Plugin settings updated.');
            }
        }

        // load template
        return $this->render('admin/plugin_settings.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'yesNoOptions' => array(
                        0 => 'No',
                        1 => 'Yes'),
                    'sandboxOptions' => array(
                        0 => 'Live Transactions - Use this on your live site',
                        1 => 'Sandbox Mode - For testing only, these payments wont actually be charged'),
                    'subsOptions' => array(
                        0 => 'No - The user will need to manually make a new payment when their account expires',
                        1 => 'Yes - Use subscriptions to automatically renew this account, unless the user cancels it'),
                    'plugin_enabled' => $plugin_enabled,
                    'paypal_email' => $paypal_email,
                    'enable_sandbox_mode' => $enable_sandbox_mode,
                    'enable_subscriptions' => $enable_subscriptions,
                                ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

}
