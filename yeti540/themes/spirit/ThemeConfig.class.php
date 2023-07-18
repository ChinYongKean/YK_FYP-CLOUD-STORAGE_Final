<?php

namespace Themes\Spirit;

use App\Services\ThemeConfig AS CoreThemeConfig;

class ThemeConfig extends CoreThemeConfig
{
    /**
     * Setup the theme config.
     *
     * @var array
     */
    public $config = array(
        'theme_name' => 'Spirit Theme',
        'folder_name' => 'spirit',
        'theme_description' => 'Bootstrap Yetishare theme included with the core script.',
        'author_name' => 'Yetishare',
        'author_website' => 'https://yetishare.com',
        'theme_version' => '1.0',
        'required_script_version' => '5.0',
        'product' => 'file_hosting',
        'product_name' => 'Yetishare',
        'product_url' => 'https://yetishare.com',
    );

}
