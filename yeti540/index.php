<?php

/**
 * Entry point. All requests are forwarded via .htaccess/mod_rewrite
 *
 * i.e. index.php?url=http://urlsample.com/controller/action/
 *
 * @author      MFScripts.com - info@mfscripts.com
 */
// get requesting url
$url = (isset($_GET['_page_url']) && (strlen($_GET['_page_url']))) ? $_GET['_page_url'] : 'index';
$url = str_replace(array('../'), '', $url);
$url = ltrim($url, '/');
define('_INT_PAGE_URL', $url);

// include framework
use App\Core\Framework;
require_once('app/core/Framework.class.php');

// setup environment and process route
Framework::run();