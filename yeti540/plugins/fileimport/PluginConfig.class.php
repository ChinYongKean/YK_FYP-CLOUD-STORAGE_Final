<?php

namespace Plugins\Fileimport;

use App\Services\PluginConfig AS CorePluginConfig;

class PluginConfig extends CorePluginConfig
{
    /**
     * Setup the plugin config.
     *
     * @var array
     */
    public $config = array(
        'plugin_name' => 'File Import',
        'folder_name' => 'fileimport',
        'plugin_description' => 'Batch import your existing files.',
        'plugin_version' => '4.0',
        'required_script_version' => '5.0',
    );

}
