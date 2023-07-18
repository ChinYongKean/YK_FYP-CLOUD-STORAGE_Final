<?php

namespace App\Services;

class ThemeConfig
{
    /**
     * The theme config, should be overridden by the child class
     *
     * @var array
     */
    public $config = [];

    public function getThemeConfig() {
        return $this->config;
    }

    public function getFolderName() {
        return $this->config['folder_name'];
    }

    public function getThemeName() {
        return $this->config['theme_name'];
    }

    public function getThemeVersion() {
        return $this->config['theme_version'];
    }
}
