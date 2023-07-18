<?php

namespace Plugins\Filepreviewer;

use App\Services\PluginConfig AS CorePluginConfig;

class PluginConfig extends CorePluginConfig
{
    /**
     * Setup the plugin config.
     *
     * @var array
     */
    public $config = array(
        'plugin_name' => 'File Previewer',
        'folder_name' => 'filepreviewer',
        'plugin_description' => 'Display files directly within the file manager.',
        'plugin_version' => '6.0',
        'required_script_version' => '5.0',
        'database_sql' => 'offline/database.sql',
        'fixedSizes' => array('16x16', '32x32', '64x64', '125x125', '180x150', '250x250', '300x250', '120x240', '160x600', '500x500', '800x800'),
        'scaledPercentages' => array('10', '25', '35', '50', '70', '85'),
    );

}
