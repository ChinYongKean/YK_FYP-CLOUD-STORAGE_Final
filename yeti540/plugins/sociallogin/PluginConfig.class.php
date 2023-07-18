<?php

namespace Plugins\Sociallogin;

use App\Services\PluginConfig AS CorePluginConfig;

class PluginConfig extends CorePluginConfig
{
    /**
     * Setup the plugin config.
     *
     * @var array
     */
    public $config = array(
        'plugin_name' => 'Social Login',
        'folder_name' => 'sociallogin',
        'plugin_description' => 'Login with your Facebook, Twitter or Google Account.',
        'plugin_version' => '7.0',
        'required_script_version' => '5.0',
    );

}
