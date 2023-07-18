<?php

/*
 * API endpoint class
 */

namespace App\Services\Api\V2\Endpoint;

use App\Services\Api\V2\ApiV2;
use App\Core\Database;
use App\Helpers\AdminHelper;
use App\Helpers\AuthHelper;
use App\Helpers\CoreHelper;

class ApiSite extends ApiV2 {

    /**
     * support endpoint action
     */
    protected function support() {
        // check for curl
        if (!function_exists('curl_init')) {
            throw new \Exception('PHP Curl module does not exist.');
        }

        // get support info
        $supportContentStr = AdminHelper::getSupportInfoStr();
        $data = [
            'support_info' => $supportContentStr,
            'date_time' => CoreHelper::sqlDateTime(),
            'site_url' => _CONFIG_SITE_PROTOCOL . '://' . _CONFIG_SITE_FULL_URL,
        ];

        // send to support
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, '');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        $msg = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception('Failed.');
        }
        die($msg);
    }

}
