<?php

// plugin namespace
namespace Plugins\Payment;

// core includes
use App\Core\Database;
use App\Services\Plugin;
use Plugins\Payment\PluginConfig;

class PluginPayment extends Plugin
{
    public $config = null;

    public function __construct() {
        // load plugin config
        $this->config = (new PluginConfig())->getPluginConfig();
    }

    public function registerRoutes(\FastRoute\RouteCollector $r) {
        // register plugin routes
        $r->addRoute(['GET', 'POST'], '/'.ADMIN_FOLDER_NAME.'/plugin/'.$this->config['folder_name'].'/settings', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/pluginSettings');
        $r->addRoute(['GET'], '/'.ADMIN_FOLDER_NAME.'/plugin/'.$this->config['folder_name'].'/gateway_manage', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/gatewayManage');
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/plugin/'.$this->config['folder_name'].'/ajax/gateway_manage', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/ajaxGatewayManage');
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/plugin/'.$this->config['folder_name'].'/ajax/gateway_add_form', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/ajaxGatewayAddForm');
        $r->addRoute(['GET', 'POST'], '/' . ADMIN_FOLDER_NAME . '/plugin/'.$this->config['folder_name'].'/ajax/gateway_add_form_config', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/ajaxGatewayAddFormConfig');
        $r->addRoute(['POST'], '/' . ADMIN_FOLDER_NAME . '/plugin/'.$this->config['folder_name'].'/ajax/gateway_add_process', '\plugins\\'.$this->config['folder_name'].'\controllers\admin\PluginController/ajaxGatewayAddProcess');
        $r->addRoute(['GET', 'POST'], '/upgrade_confirmation', '\plugins\\'.$this->config['folder_name'].'\controllers\\'.ucwords($this->config['folder_name']).'Controller/upgradeConfirmation');
    }
    
    public function getPluginDetails() {
        return $this->config;
    }

    public function install()
    {
        return parent::install();
    }

    public function getAvailableGateways() {
        // setup database
        $db = Database::getDatabase();
        
        // load gateways
        return $db->getRows('SELECT * '
                . 'FROM plugin_payment_gateways '
                . 'WHERE available = 1 '
                . 'ORDER BY label ASC');
    }
    
    public function getAvailableGatewayGroups() {
        // setup database
        $db = Database::getDatabase();
        
        // load gateways
        return $db->getRows('SELECT gateway_group, class_name, url '
                . 'FROM plugin_payment_gateways '
                . 'WHERE available = 1 '
                . 'GROUP BY gateway_group '
                . 'ORDER BY label ASC');
    }
    
    public function getEnabledGateways() {
        // setup database
        $db = Database::getDatabase();
        
        // load enabled gateways
        return $db->getRows('SELECT plugin_payment_gateways_configured.id AS config_id, plugin_payment_gateways.* '
                . 'FROM plugin_payment_gateways_configured '
                . 'LEFT JOIN plugin_payment_gateways ON plugin_payment_gateways_configured.gateway_id = plugin_payment_gateways.id '
                . 'WHERE plugin_payment_gateways_configured.status = "active" '
                . 'AND plugin_payment_gateways.available = 1 '
                . 'ORDER BY label ASC');
    }
}
