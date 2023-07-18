<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Helpers\ApiV2Helper;

class ApiController extends AdminBaseController
{

    public function apiDocumentation() {
        // admin restrictions
        $this->restrictAdminAccess();

        // load template
        return $this->render('admin/api_documentation.html', [
            'apiUrl' => ApiV2Helper::getApiUrl(),
            'showAdminEndpoints' => true,
        ]);
    }

    public function apiTestFramework() {
        // admin restrictions
        $this->restrictAdminAccess();

        // allow some time to run
        set_time_limit(60 * 60);

        // list of actions and params
        $db = Database::getDatabase();
        $Auth = $this->getAuth();
        $actions = [];
        $key1 = $key2 = '';
        if (SITE_CONFIG_API_AUTHENTICATION_METHOD == 'Account Access Details') {
            $actions['/authorize'] = array('username' => $Auth->username, 'password' => '');
        }
        else {
            // load keys
            $accountAPIKeys = $db->getRow('SELECT key_public, key_secret '
                    . 'FROM apiv2_api_key '
                    . 'WHERE user_id = :user_id '
                    . 'LIMIT 1', array(
                        'user_id' => $Auth->id,
                        )
                    );
            if ($accountAPIKeys) {
                $key1 = $accountAPIKeys['key_public'];
                $key2 = $accountAPIKeys['key_secret'];
            }
            $actions['/authorize'] = array('key1' => $key1, 'key2' => $key2);
        }
        $actions['/disable_access_token'] = array('access_token' => '', 'account_id' => '');
        $actions['/account/info'] = array('access_token' => '', 'account_id' => '');
        $actions['/account/package'] = array('access_token' => '', 'account_id' => '');
        $actions['/account/create'] = array('access_token' => '', 'username' => '', 'password' => '', 'email' => '', 'package_id' => '', 'status' => '', 'title' => '', 'firstname' => '', 'lastname' => '', 'paid_expiry_date' => '');
        $actions['/account/edit'] = array('access_token' => '', 'account_id' => '', 'password' => '', 'email' => '', 'package_id' => '', 'status' => '', 'title' => '', 'firstname' => '', 'lastname' => '', 'paid_expiry_date' => '');
        $actions['/account/delete'] = array('access_token' => '', 'account_id' => '');
        $actions['/file/upload'] = array('access_token' => '', 'account_id' => '', 'upload_file' => '', 'folder_id' => '');
        $actions['/file/download'] = array('access_token' => '', 'account_id' => '', 'file_id' => '', 'short_url' => '');
        $actions['/file/info'] = array('access_token' => '', 'account_id' => '', 'file_id' => '');
        $actions['/file/edit'] = array('access_token' => '', 'account_id' => '', 'file_id' => '', 'filename' => '', 'fileType' => '', 'folder_id' => '');
        $actions['/file/delete'] = array('access_token' => '', 'account_id' => '', 'file_id' => '');
        $actions['/file/move'] = array('access_token' => '', 'account_id' => '', 'file_id' => '', 'new_parent_folder_id' => '');
        $actions['/file/copy'] = array('access_token' => '', 'account_id' => '', 'file_id' => '', 'copy_to_folder_id' => '');
        $actions['/folder/create'] = array('access_token' => '', 'account_id' => '', 'folder_name' => '', 'parent_id' => '', 'is_public' => '', 'access_password' => '');
        $actions['/folder/listing'] = array('access_token' => '', 'account_id' => '', 'parent_folder_id' => '');
        $actions['/folder/info'] = array('access_token' => '', 'account_id' => '', 'folder_id' => '');
        $actions['/folder/edit'] = array('access_token' => '', 'account_id' => '', 'folder_id' => '', 'folder_name' => '', 'parent_id' => '', 'is_public' => '', 'access_password' => '');
        $actions['/folder/delete'] = array('access_token' => '', 'account_id' => '', 'folder_id' => '');
        $actions['/folder/move'] = array('access_token' => '', 'account_id' => '', 'folder_id' => '', 'new_parent_folder_id' => '');
        $actions['/package/listing'] = array('access_token' => '');

        // load template
        return $this->render('admin/api_test_framework.html', [
            'apiUrl' => ApiV2Helper::getApiUrl(),
            'key1' => $key1,
            'key2' => $key2,
            'actions' => $actions,
        ]);
    }

}
