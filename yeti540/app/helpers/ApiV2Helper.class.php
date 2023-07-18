<?php

namespace App\Helpers;

/**
 * API V2 class for remote file management.
 * 
 * See /api/v2/index.php for full usage details.
 */
class ApiV2Helper
{
    public static function init($requestUrl, $origin)
    {
        // convert $requestUrl to path
        $requestUrl = trim(strip_tags($requestUrl));
        $requestUrl = strtolower($requestUrl);

        $args = explode('/', rtrim($requestUrl, '/'));
        $endpoint = array_shift($args);

        // make sure endpoint exists, if so instantiate it
        $endPointClassPath = CORE_FRAMEWORK_SERVICES_ROOT . '/api/v2/endpoint/Api' . str_replace(' ', '', ucwords(str_replace('_', ' ', $endpoint))) . '.class.php';
        if(!file_exists($endPointClassPath))
        {
            header("HTTP/1.1 404 Not Found'");

            return json_encode("No endpoint found for: " . $endpoint);
        }

        // include the endpoint
        include_once($endPointClassPath);

        // instantiae the object
        $className = 'App\Services\Api\V2\Endpoint\Api' . str_replace(' ', '', ucwords(str_replace('_', ' ', $endpoint)));
        $endPointInit = new $className($requestUrl, $origin);

        return $endPointInit;
    }

    public static function getApiUrl() {
        // load it from the config if we have it
        if (strlen(SITE_CONFIG_API_ACCESS_HOST)) {
            return SITE_CONFIG_API_ACCESS_HOST;
        }

        // fallback on the default
        return WEB_ROOT . '/api/v2/';
    }

}
