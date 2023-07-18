<?php

namespace Plugins\Newsletters;

use App\Services\PluginConfig AS CorePluginConfig;

class PluginConfig extends CorePluginConfig
{
    /**
     * Setup the plugin config.
     *
     * @var array
     */
    public $config = array(
        'plugin_name' => 'Newsletters',
        'folder_name' => 'newsletters',
        'plugin_description' => 'Manage & send newsletters to your users.',
        'plugin_version' => '8.0',
        'required_script_version' => '5.4',
        'database_sql' => 'offline/database.sql',
    );

}
