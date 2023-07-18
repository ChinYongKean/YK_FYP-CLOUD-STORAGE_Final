<?php

/*
 * API endpoint class
 */

namespace App\Services\Api\V2\Endpoint;

use App\Services\Api\V2\ApiV2;

class ApiDisableAccessToken extends ApiV2
{

    public function __construct($request, $origin) {
        parent::__construct($request);

        // all api requests require the access_token and account_id (apart from the initial authorize
        if (!array_key_exists('access_token', $this->request) || (strlen($this->request['access_token']) == 0)) {
            throw new \Exception('Please provide the access_token param.');
        }
        elseif (!array_key_exists('account_id', $this->request) || (strlen($this->request['account_id']) == 0)) {
            throw new \Exception('Please provide the account_id param.');
        }

        // validate access_token and account_id
        $rs = $this->_validateAccessToken($this->request['access_token'], $this->request['account_id']);
        if (!$rs) {
            throw new \Exception('Could not validate access_token and account_id, please reauthenticate or try again.');
        }
    }

    /**
     * default endpoint action
     */
    protected function index() {
        // disable token
        $this->_clearAllAccessTokensByUserId($this->request['account_id'], $this->request['access_token']);

        return [
            'response' => 'Token removed or no longer available.'
        ];
    }

}
