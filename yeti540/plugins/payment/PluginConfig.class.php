<?php

namespace Plugins\Payment;

use App\Services\PluginConfig AS CorePluginConfig;

class PluginConfig extends CorePluginConfig
{
    /**
     * Setup the plugin config.
     *
     * @var array
     */
    public $config = array(
        'plugin_name' => 'Payment Gateways Integration',
        'folder_name' => 'payment',
        'plugin_description' => 'Access to over 50 payment gateways for premium account upgrades.',
        'plugin_version' => '3.0',
        'required_script_version' => '5.4',
    );

}
