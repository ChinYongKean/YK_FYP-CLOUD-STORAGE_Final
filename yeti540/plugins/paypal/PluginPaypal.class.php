<?php

// plugin namespace
namespace Plugins\Paypal;

// core includes
use App\Services\Plugin;
use Plugins\Paypal\PluginConfig;

class PluginPaypal extends Plugin
{
    public $config = null;
    public $data = null;
    public $settings = null;

    public function __construct() {
        // load plugin config
        $this->config = (new PluginConfig())->getPluginConfig();
    }

    public function registerRoutes(\FastRoute\RouteCollector $r) {
        // register plugin routes
        $r->addRoute(['GET', 'POST'], '/'.ADMIN_FOLDER_NAME.'/plugin/'.$this->config['folder_name'].'/settings', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/pluginSettings');
        $r->addRoute(['GET', 'POST'], '/'.$this->config['folder_name'].'/pay', '\plugins\\'.$this->config['folder_name'].'\controllers\\'.ucwords($this->config['folder_name']).'Controller/pay');
        $r->addRoute(['GET', 'POST'], '/'.$this->config['folder_name'].'/payment_ipn', '\plugins\\'.$this->config['folder_name'].'\controllers\\'.ucwords($this->config['folder_name']).'Controller/paymentIpn');
    }
    
    public function getPluginDetails() {
        return $this->config;
    }

}
