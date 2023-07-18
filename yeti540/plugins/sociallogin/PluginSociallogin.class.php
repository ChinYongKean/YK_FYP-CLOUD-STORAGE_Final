<?php

// plugin namespace
namespace Plugins\Sociallogin;

// core includes
use App\Services\Plugin;
use Plugins\Sociallogin\PluginConfig;

class PluginSociallogin extends Plugin
{
    public function __construct() {
        // load plugin config
        $this->config = (new PluginConfig())->getPluginConfig();
    }

    public function getPluginDetails() {
        return $this->config;
    }

    public function registerRoutes(\FastRoute\RouteCollector $r) {
        // register plugin routes
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/plugin/' . $this->config['folder_name'] . '/settings', '\plugins\\' . $this->config['folder_name'] . '\controllers\admin\PluginController/pluginSettings');
        $r->addRoute(['GET', 'POST'], '/' . PLUGIN_DIRECTORY_NAME . '/' . $this->config['folder_name'] . '/login/{provider}', '\plugins\\' . $this->config['folder_name'] . '\controllers\SocialloginController/login');
    }

}
