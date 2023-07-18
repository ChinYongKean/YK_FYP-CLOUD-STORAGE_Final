<?php

namespace Plugins\Sociallogin\Controllers;

use App\Core\BaseController;
use App\Helpers\NotificationHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ValidationHelper;

class HooksController extends BaseController
{

    public function loginLoginBox($params = null) {
        // load plugin details
        $pluginObj = PluginHelper::getInstance('sociallogin');
        $pluginDetails = PluginHelper::pluginSpecificConfiguration($pluginObj->config['folder_name']);
        $pluginSettings = json_decode($pluginDetails['data']['plugin_settings'], true);
        
        // get request
        $request = $this->getRequest();
        if($request->query->has('plugin_social_login_error')) {
            NotificationHelper::setError($request->query->get('plugin_social_login_error'));
        }
        
        // load template
        return $this->render('login_login_box.html', array(
                    'pluginSettings' => $pluginSettings,
                    'pluginConfig' => $pluginObj->config,
                        ), PLUGIN_DIRECTORY_ROOT . $pluginObj->config['folder_name'] . '/views');
    }

}
