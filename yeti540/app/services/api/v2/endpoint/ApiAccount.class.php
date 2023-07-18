<?php

/*
 * API endpoint class
 */

namespace App\Services\Api\V2\Endpoint;

use App\Services\Api\V2\ApiV2;
use App\Core\Database;
use App\Helpers\CoreHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UserHelper;
use App\Helpers\ValidationHelper;
use App\Models\User;
use App\Services\Password;
use App\Services\PasswordPolicy;

class ApiAccount extends ApiV2
{

    public function __construct($request, $origin) {
        parent::__construct($request);

        // all api requests require the access_token and account_id (apart from the initial authorize
        if (!array_key_exists('access_token', $this->request) || (strlen($this->request['access_token']) == 0)) {
            throw new \Exception('Please provide the access_token param.');
        }

        // validate access_token and account_id
        $rs = $this->_validateAccessToken($this->request['access_token']);
        if (!$rs) {
            throw new \Exception('Could not validate access_token, please reauthenticate or try again.');
        }
    }

    /**
     * endpoint action
     */
    protected function info() {
        if ((!array_key_exists('account_id', $this->request) || (strlen($this->request['account_id']) == 0))) {
            if ($this->_validateAdminOnly($this->request['access_token']) === false) {
                throw new \Exception('Please provide the account_id param.');
            }
        }

        // if it's not the current API user, only allow admin
        if ((int) $this->request['account_id'] !== $this->apiUserId) {
            if ($this->_validateAdminOnly($this->request['access_token']) === false) {
                throw new \Exception('You do not have access to this feature.');
            }
        }

        $db = Database::getDatabase();

        // load account details
        $accountDetails = $db->getRow('SELECT id, username, level_id, email, lastlogindate, lastloginip, status, title, '
                . 'firstname, lastname, languageId, datecreated, lastPayment, paidExpiryDate, storageLimitOverride FROM users '
                . 'WHERE id = :user_id '
                . 'LIMIT 1', array(
                    'user_id' => (int) $this->request['account_id'],
                    ), \PDO::FETCH_ASSOC);

        return ['data' => $accountDetails];
    }

    /**
     * endpoint action
     */
    protected function package() {
        if (!array_key_exists('account_id', $this->request) || (strlen($this->request['account_id']) == 0)) {
            throw new \Exception('Please provide the account_id param.');
        }

        // if it's not the current API user, only allow admin
        if ((int) $this->request['account_id'] !== $this->apiUserId) {
            if ($this->_validateAdminOnly($this->request['access_token']) === false) {
                throw new \Exception('You do not have access to this feature.');
            }
        }

        $db = Database::getDatabase();

        // load account details
        $accountPackage = $db->getRow('SELECT user_level.id, label, max_upload_size, '
                . 'can_upload, wait_between_downloads, download_speed, max_storage_bytes, '
                . 'show_site_adverts, show_upgrade_screen, days_to_keep_inactive_files, concurrent_uploads, '
                . 'concurrent_downloads, downloads_per_24_hours, download_size_per_24_hours, max_download_filesize_allowed, max_remote_download_urls, '
                . 'level_type, on_upgrade_page FROM user_level '
                . 'LEFT JOIN users ON user_level.id = users.level_id '
                . 'WHERE users.id = :user_id '
                . 'LIMIT 1', [
                    'user_id' => (int) $this->request['account_id'],
        ], \PDO::FETCH_ASSOC);

        return ['data' => $accountPackage];
    }

    /**
     * endpoint action
     */
    protected function create() {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // enable admin only access
        $rs = $this->_validateAdminOnly($this->request['access_token']);
        if (!$rs) {
            throw new \Exception('API user must be an admin user for this endpoint.');
        }

        // validation
        if (!array_key_exists('username', $this->request) || (strlen($this->request['username']) == 0)) {
            throw new \Exception('Please provide the username param.');
        }
        if ((strlen($this->request['username']) < 6) || (strlen($this->request['username']) > 20)) {
            throw new \Exception(TranslateHelper::t("username_length_invalid"));
        }
        if (!array_key_exists('password', $this->request) || (strlen($this->request['password']) == 0)) {
            throw new \Exception('Please provide the password param.');
        }
        $passValid = UserHelper::validatePassword($this->request['password']);
        if (is_array($passValid)) {
            throw new \Exception(implode('<br/>', $passValid));
        }
        if (!array_key_exists('email', $this->request) || (strlen($this->request['email']) == 0)) {
            throw new \Exception('Please provide the email param.');
        }
        if (ValidationHelper::validEmail($this->request['email']) == false) {
            throw new \Exception(TranslateHelper::t("entered_email_address_invalid"));
        }
        $checkEmail = User::loadOne('email', $this->request['email']);
        if ($checkEmail) {
            // email exists
            throw new \Exception(TranslateHelper::t("email_address_already_exists", "Email address already exists on another account"));
        }
        $checkUser = User::loadOne('username', $this->request['username']);
        if ($checkUser) {
            // username exists
            throw new \Exception(TranslateHelper::t("username_already_exists", "Username already exists on another account"));
        }
        if ((int) $this->request['package_id'] >= 10) {
            throw new \Exception('API can not be used to create admin or moderator accounts.');
        }

        // update item
        $params = [];
        $sQLClauseLeft = [];
        $sQLClauseRight = [];

        // folder_name
        $params['username'] = trim(strtolower($this->request['username']));
        $sQLClauseLeft[] = 'username';
        $sQLClauseRight[] = ':username';

        $params['password'] = Password::createHash($this->request['password']);
        $sQLClauseLeft[] = 'password';
        $sQLClauseRight[] = ':password';

        $params['email'] = strtolower($this->request['email']);
        $sQLClauseLeft[] = 'email';
        $sQLClauseRight[] = ':email';

        $packageId = (int) $this->request['package_id'];
        if ($packageId <= 0 || $packageId >= 10) {
            throw new \Exception('Can not have package ids greater or equal to 10 or less than 0 via the API.');
        }
        $params['level_id'] = $packageId;
        $sQLClauseLeft[] = 'level_id';
        $sQLClauseRight[] = ':level_id';

        $status = $this->request['status'];
        if (!in_array($status, ['pending', 'active', 'disabled', 'suspended'])) {
            $status = 'active';
        }
        $params['status'] = $status;
        $sQLClauseLeft[] = 'status';
        $sQLClauseRight[] = ':status';

        $title = $this->request['title'];
        if (!in_array($title, UserHelper::getUserTitles())) {
            $title = 'Mr';
        }
        $params['title'] = $title;
        $sQLClauseLeft[] = 'title';
        $sQLClauseRight[] = ':title';

        $params['firstname'] = $this->request['firstname'];
        $sQLClauseLeft[] = 'firstname';
        $sQLClauseRight[] = ':firstname';

        $params['lastname'] = $this->request['lastname'];
        $sQLClauseLeft[] = 'lastname';
        $sQLClauseRight[] = ':lastname';


        if ((isset($this->request['paid_expiry_date'])) && (strlen($this->request['paid_expiry_date']) == 19)) {
            $params['paidExpiryDate'] = date("Y-m-d H:i:s", strtotime($this->request['paid_expiry_date']));
            $sQLClauseLeft[] = 'paidExpiryDate';
            $sQLClauseRight[] = ':paidExpiryDate';
        }
        // insert
        $db = Database::getDatabase();
        $rs = $db->query('INSERT INTO users (' . implode(', ', $sQLClauseLeft) . ', datecreated) VALUES (' . implode(', ', $sQLClauseRight) . ', NOW())', $params);
        if (!$rs) {
            // error
            throw new \Exception('Failed creating the user.');
        }

        // return the folder details
        $this->request['account_id'] = $db->insertId();

        // return the updated file item
        return array_merge(['response' => 'User successfully created.'], $this->info());
    }

    /**
     * endpoint action
     */
    protected function edit() {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // enable admin only access
        $rs = $this->_validateAdminOnly($this->request['access_token']);
        if (!$rs) {
            throw new \Exception('API user must be an admin user for this endpoint.');
        }

        // validate account_id
        if (!array_key_exists('account_id', $this->request) || (strlen($this->request['account_id']) == 0)) {
            throw new \Exception('Please provide the account_id param of the account to edit.');
        }

        // load the user
        $user = User::loadOneById($this->request['account_id']);
        if (!$user) {
            // error
            throw new \Exception('Failed loading a user account with the supplied account_id.');
        }

        // make sure it's not an admin user
        if ($user->isAdmin() === true) {
            // error
            throw new \Exception('You can not edit admin accounts via the API.');
        }

        // validation
        $params = [];
        $sQLClause = [];
        if (array_key_exists('password', $this->request) && strlen($this->request['password'])) {
            $passValid = UserHelper::validatePassword($this->request['password']);
            if (is_array($passValid)) {
                throw new \Exception(implode('<br/>', $passValid));
            }

            $params['password'] = Password::createHash($this->request['password']);
            $sQLClause[] = 'password = :password';
        }

        if (array_key_exists('email', $this->request) && strlen($this->request['email'])) {
            if (ValidationHelper::validEmail($this->request['email']) == false) {
                throw new \Exception(TranslateHelper::t("entered_email_address_invalid"));
            }

            $checkEmail = User::loadOne('email', $this->request['email']);
            if ($checkEmail) {
                // email exists
                throw new \Exception(TranslateHelper::t("email_address_already_exists", "Email address already exists on another account"));
            }

            $params['email'] = strtolower($this->request['email']);
            $sQLClause[] = 'email = :email';
        }

        if (array_key_exists('package_id', $this->request) && strlen($this->request['package_id'])) {
            if ((int) $this->request['package_id'] >= 10) {
                throw new \Exception('API can not be used to administer admin or moderator accounts.');
            }

            $packageId = (int) $this->request['package_id'];
            if ($packageId <= 0 || $packageId >= 10) {
                // we can not have package ids greater or equal to 10 or less than 0
                $packageId = 1;
            }
            $params['level_id'] = $packageId;
            $sQLClause[] = 'level_id = :level_id';
        }

        if (array_key_exists('status', $this->request) && strlen($this->request['status'])) {
            $status = $this->request['status'];
            if (!in_array($status, ['pending', 'active', 'disabled', 'suspended'])) {
                $status = 'active';
            }
            $params['status'] = $status;
            $sQLClause[] = 'status = :status';
        }

        if (array_key_exists('title', $this->request) && strlen($this->request['title'])) {
            $title = $this->request['title'];
            if (!in_array($title, UserHelper::getUserTitles())) {
                $title = 'Mr';
            }
            $params['title'] = $title;
            $sQLClause[] = 'title = :title';
        }

        if (array_key_exists('firstname', $this->request) && strlen($this->request['firstname'])) {
            $params['firstname'] = $this->request['firstname'];
            $sQLClause[] = 'firstname = :firstname';
        }

        if (array_key_exists('lastname', $this->request) && strlen($this->request['lastname'])) {
            $params['lastname'] = $this->request['lastname'];
            $sQLClause[] = 'lastname = :lastname';
        }

        if (array_key_exists('paid_expiry_date', $this->request) && strlen($this->request['paid_expiry_date'])) {
            $params['paidExpiryDate'] = date("Y-m-d H:i:s", strtotime($this->request['paid_expiry_date']));
            $sQLClause[] = 'paidExpiryDate = :paidExpiryDate';
        }

        // update
        $db = Database::getDatabase();

        // prep sql
        $sQL = 'UPDATE users SET ' . implode(', ', $sQLClause) . ' '
                . 'WHERE id = :account_id AND level_id < 10 LIMIT 1';

        // update params
        $params['account_id'] = (int) $this->request['account_id'];

        // execute sql
        $rs = $db->query($sQL, $params);

        // return the updated file item
        return array_merge(['response' => 'User successfully updated.'], $this->info());
    }

    /**
     * endpoint action
     */
    protected function delete() {
        // check for demo mode
        if (CoreHelper::inDemoMode()) {
            throw new \Exception('This API feature is not available in demo mode.');
        }

        // enable admin only access
        $rs = $this->_validateAdminOnly($this->request['access_token']);
        if (!$rs) {
            throw new \Exception('API user must be an admin user for this endpoint.');
        }

        // validate account_id
        if (!array_key_exists('account_id', $this->request) || (strlen($this->request['account_id']) == 0)) {
            throw new \Exception('Please provide the account_id param of the account to delete.');
        }

        // load the user
        $user = User::loadOneById($this->request['account_id']);
        if (!$user) {
            // error
            throw new \Exception('Failed loading a user account with the supplied account_id.');
        }

        // make sure it's not an admin user
        if ($user->isAdmin() === true) {
            // error
            throw new \Exception('You can not delete admin accounts via the API.');
        }

        // make sure it's not the current API user
        if ((int) $this->request['account_id'] === $this->apiUserId) {
            // error
            throw new \Exception('You can not delete the current API user.');
        }

        // finally remove the account
        $rs = $user->deleteUserData();
        if ($rs) {
            // return the confirmation message onscreen
            return array_merge(['response' => 'User successfully deleted.']);
        }
        else {
            // error
            throw new \Exception('There was a general issue removing the account, please try again later.');
        }
    }

}
