<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Helpers\AdminApiHelper;

class AdminApiController extends AdminBaseController
{

    public function api() {
        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // required variables
        $key = '';
        if ($_REQUEST['key']) {
            $key = $_REQUEST['key'];
        }

        if (strlen($key) == 0) {
            AdminApiHelper::outputError('API access key not found.');
        }

        $username = '';
        if ($_REQUEST['username']) {
            $username = $_REQUEST['username'];
        }

        if (strlen($username) === 0) {
            AdminApiHelper::outputError('Username not found.');
        }

        $action = '';
        if ($_REQUEST['action']) {
            $action = $_REQUEST['action'];
        }

        if (strlen($action) == 0) {
            AdminApiHelper::outputError('Action not found.');
        }

        // make sure user has access
        $rs = AdminApiHelper::validateAccess($key, $username);
        if (!$rs) {
            AdminApiHelper::outputError('Could not validate api access details.');
        }

        // check action exists
        $actualMethod = 'api' . ucfirst($action);
        if (!method_exists('App\Helpers\AdminApiHelper', $actualMethod)) {
            AdminApiHelper::outputError('Action of \'' . $action . '\' not found. Method: ' . $actualMethod . '()');
        }

        // call action
        echo call_user_func(['App\Helpers\AdminApiHelper', $actualMethod], $_REQUEST);
        exit;
    }

}
