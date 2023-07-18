<?php

namespace App\Helpers;

use App\Services\TTwigExtension;

/**
 * main template class
 */
class TemplateHelper
{

    static function render($template, $params = array(), $templatePath = null) {
        // get cache path
        $cachePath = false;
        if (CacheHelper::isApplicationCachingEnabled() === true) {
            $cachePath = self::setupCacheFolder();
        }

        // allow for optional .twig on the extension
        if (substr($template, strlen($template) - 5, 5) === '.twig') {
            $template = substr($template, 0, strlen($template) - 5);
        }

        // figure out which view to use, first theme overide
        if ($templatePath === null) {
            $templatePath = CORE_APPLICATION_TEMPLATES_PATH;
            if (file_exists(SITE_TEMPLATES_PATH . '/' . $template . '.twig')) {
                $templatePath = SITE_TEMPLATES_PATH;
            }
        }
        else {
            // if $templatePath is in the plugins folder, check to see if we have
            // it overridden in the theme
            if (substr($templatePath, 0, strlen(PLUGIN_DIRECTORY_ROOT)) === PLUGIN_DIRECTORY_ROOT) {
                // we have a plugin template, check in theme
                $exPluginPath = substr($templatePath, strlen(PLUGIN_DIRECTORY_ROOT), strlen($templatePath));
                $exPluginPath = str_replace('/views', '', $exPluginPath);
                if (file_exists(SITE_TEMPLATES_PATH . '/plugins/' . $exPluginPath . '/' . $template . '.twig')) {
                    $templatePath = SITE_TEMPLATES_PATH . '/plugins/' . $exPluginPath;
                }
            }
        }

        // double check file exists
        if (!file_exists($templatePath . '/' . $template . '.twig')) {
            die('Error: Could not find template: ' . $templatePath . '/' . $template . '.twig');
        }

        // setup our template loader base
        $loader = new \Twig_Loader_Filesystem($templatePath);

        // add our core template path as a variable - used when theme and plugin
        // views extend core views
        $loader->addPath(CORE_APPLICATION_TEMPLATES_PATH, 'corePath');
        $loader->addPath(SITE_TEMPLATES_PATH, 'themePath');

        // prep the template handler
        $twig = new \Twig_Environment($loader, array(
            'cache' => $cachePath,
            'debug' => _CONFIG_DEBUG,
        ));

        // and translations helper to Twig ('t' filter)
        $twig->addExtension(new TTwigExtension());

        // append global params
        if (!isset($params['Auth'])) {
            $params['Auth'] = AuthHelper::getAuth();
        }
        // load theme functions
        if (!isset($params['theme'])) {
            $params['theme'] = ThemeHelper::getLoadedInstance();
        }
        // access to notifications
        if (NotificationHelper::isErrors()) {
            $params['msg_page_errors'] = NotificationHelper::getErrors();
        }
        elseif (NotificationHelper::isSuccess()) {
            $params['msg_page_successes'] = NotificationHelper::getSuccess();
        }
        // add other helpers
        if (!isset($params['CoreHelper'])) {
            $params['CoreHelper'] = new CoreHelper;
        }
        if (!isset($params['PluginHelper'])) {
            $params['PluginHelper'] = new PluginHelper;
        }
        if (!isset($params['ThemeHelper'])) {
            $params['ThemeHelper'] = new ThemeHelper;
        }
        if (!isset($params['ThemeHelper'])) {
            $params['ThemeHelper'] = new ThemeHelper;
        }
        if (!isset($params['UserHelper'])) {
            $params['UserHelper'] = new UserHelper;
        }

        // add global constants
        $userConstants = get_defined_constants(true)['user'];
        foreach ($userConstants AS $k => $userConstant) {
            $params[$k] = $userConstant;
        }
        $params['CORE_SITE_PATH'] = CoreHelper::getCoreSitePath();
        $params['documentDomain'] = CoreHelper::removeSubDomain(_CONFIG_CORE_SITE_HOST_URL);
        $params['app_twig_template'] = $template;

        // return rendered template
        return $twig->render($template . '.twig', $params);
    }

    static function setupCacheFolder() {
        // make sure we have a cache directory
        $cachePath = CACHE_DIRECTORY_ROOT . '/twig';
        if (!file_exists($cachePath)) {
            mkdir($cachePath);
        }

        return $cachePath;
    }

}
