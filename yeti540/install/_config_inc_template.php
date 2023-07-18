<?php

/**
 * Main configuration file for script
 */

/**
 * Site url host without the http:// and no trailing forward slash - i.e. www.mydomain.com or links.mydomain.com
 */
define("_CONFIG_SITE_HOST_URL", "<SITE_HOST_URL>");

/**
 * Full site url without the http:// and no trailing forward slash - i.e. www.mydomain.com/links or
 * the same as the _CONFIG_SITE_HOST_URL
 */
define("_CONFIG_SITE_FULL_URL", "<SITE_FULL_URL>");

/**
 * Database connection details
 */
define("_CONFIG_DB_HOST", "<DB_HOST>");
define("_CONFIG_DB_NAME", "<DB_NAME>");
define("_CONFIG_DB_USER", "<DB_USER>");
define("_CONFIG_DB_PASS", "<DB_PASSWORD>");

/**
 * Set this to the main site host if you're using direct web server uploads/downloads to remote servers
 *
 * Site url host without the http:// and no trailing forward slash - i.e. www.mydomain.com or links.mydomain.com
 */
define("_CONFIG_CORE_SITE_HOST_URL", "<SITE_HOST_URL>");

/**
 * Set this to the main site host if you're using direct web server uploads/downloads to remote servers
 *
 * Full site url without the http:// and no trailing forward slash - i.e. www.mydomain.com/links or the same
 * as the _CONFIG_SITE_HOST_URL
 */
define("_CONFIG_CORE_SITE_FULL_URL", "<SITE_FULL_URL>");

/**
 * This will display debug information when something fails in the DB - leave this as true if you're not sure
 */
define("_CONFIG_DEBUG", true);

/**
 * Which protocol to use, https or http
 */
define("_CONFIG_SITE_PROTOCOL", "<SITE_PROTOCOL>");

/**
 * Encryption key used for encoding data within the site
 */
define("_CONFIG_UNIQUE_ENCRYPTION_KEY", "<SECRET_KEY>");

/**
 * Toggle demo mode. Always leave this as false
 */
define("_CONFIG_DEMO_MODE", false);