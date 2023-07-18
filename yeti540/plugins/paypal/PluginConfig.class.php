<?php

namespace Plugins\Paypal;

use App\Services\PluginConfig AS CorePluginConfig;

class PluginConfig extends CorePluginConfig
{
    /**
     * Setup the plugin config.
     *
     * @var array
     */
    public $config = array(
        'plugin_name' => 'PayPal Payment Integration',
        'folder_name' => 'paypal',
        'plugin_description' => 'Accept payments using PayPal.',
        'plugin_version' => '6.0',
        'required_script_version' => '5.4',
    );

}
