<?php

// disable error reporting
error_reporting(0);

// application name
define('INSTALLER_APPLICATION_NAME', 'Yetishare');

// minimum required PHP version
define('INSTALLER_PHP_MINIMUM_VERSION', '7.3.0');

// default language for the installer
define('INSTALLER_DEFAULT_LANGUAGE', 'en');

// array of available languages
$arr_active_languages = ['en' => 'English'];

// the main script config file
define('INSTALLER_CONFIG_FILE_PATH', '../_config.inc.php');

// initial database structure and data
define('INSTALLER_SQL_DUMP_FILE', 'resources/database.sql');

// config template path
define('INSTALLER_CONFIG_FILE_TEMPLATE', '_config_inc_template.php');