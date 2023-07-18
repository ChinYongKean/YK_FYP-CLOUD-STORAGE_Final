<?php

/*
 * API endpoint class
 */

namespace App\Services\Api\V2\Endpoint;

use App\Services\Api\V2\ApiV2;
use App\Core\Database;
use App\Helpers\AuthHelper;

class ApiAuthorize extends ApiV2
{

    /**
     * default endpoint action
     */
    protected function index() {
        // get auth for later
        $Auth = AuthHelper::getAuth();

        // we have 2 options for API auth, username and password or API key, use the admin, site settings to configure
        if (SITE_CONFIG_API_AUTHENTICATION_METHOD == 'Account Access Details') {
            // check required params
            if (!array_key_exists('username', $this->request) || (strlen($this->request['username']) == 0)) {
                throw new \Exception('Please provide a username.');
            }
            elseif (!array_key_exists('password', $this->request) || (strlen($this->request['password']) == 0)) {
                throw new \Exception('Please provide a password.');
            }

            // clear any session redirect
            $_SESSION['_redirect_url'] = null;
            
            // validate the user
            $rs = $Auth->attemptLogin($this->request['username'], $this->request['password'], false, true, null, true);
            if ($rs === false) {
                throw new \Exception('Could not authenticate user. The username '
                        . 'and password may be invalid or your account may be locked '
                        . 'from too many failed logins.');
            }
        }
        // API keys
        else {
            // check required params
            if (!array_key_exists('key1', $this->request) || (strlen($this->request['key1']) != 64)) {
                throw new \Exception('Please provide key1. It must be 64 characters in length.');
            }
            elseif (!array_key_exists('key2', $this->request) || (strlen($this->request['key2']) != 64)) {
                throw new \Exception('Please provide key2. It must be 64 characters in length.');
            }

            // validate the user
            $rs = $Auth->loginUsingApiPair($this->request['key1'], $this->request['key2']);
            if ($rs === false) {
                throw new \Exception('Could not authenticate user. The key pair '
                        . 'may be invalid or your account may be locked from too '
                        . 'many failed logins.');
            }
        }

        // make sure their account type has access
        // setup access level
        $accessTypes = explode('|', SITE_CONFIG_API_ACCOUNT_ACCESS_TYPE);
        if (!in_array($Auth->level, $accessTypes)) {
            throw new \Exception('Your account level does not have access to the '
                    . 'file upload API. Please contact site support for more information.');
        }

        // user validated, generate an access token
        $accessToken = $this->_generateAccessToken();

        // delete any existing access tokens for this user
        $db = Database::getDatabase();
        $currentUserId = $db->getValue('SELECT id '
                . 'FROM users '
                . 'WHERE username = :username '
                . 'LIMIT 1', [
                    'username' => $Auth->username,
        ]);
        $this->_clearAllAccessTokensByUserId($currentUserId);

        // add new token
        $rs = $db->query('INSERT INTO apiv2_access_token '
                . '(user_id, access_token, date_added) VALUES '
                . '(:user_id, :access_token, NOW())', [
                    'user_id' => $currentUserId,
                    'access_token' => $accessToken,
            ]
                );
        if (!$rs) {
            throw new \Exception('Failed issuing access token.');
        }

        return [
            'data' => [
                'access_token' => $accessToken,
                'account_id' => $currentUserId
            ]
        ];
    }

}
