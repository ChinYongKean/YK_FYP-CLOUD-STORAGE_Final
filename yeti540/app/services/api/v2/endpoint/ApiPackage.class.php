<?php

/*
 * API endpoint class
 */

namespace App\Services\Api\V2\Endpoint;

use App\Services\Api\V2\ApiV2;
use App\Core\Database;

class ApiPackage extends ApiV2
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
    protected function listing() {
        // enable admin only access
        $rs = $this->_validateAdminOnly($this->request['access_token']);
        if (!$rs) {
            throw new \Exception('API user must be an admin user for this endpoint.');
        }

        $db = Database::getDatabase();

        // load account details
        $packageListing = $db->getRows('SELECT id, label, max_upload_size, '
                . 'can_upload, wait_between_downloads, download_speed, max_storage_bytes, '
                . 'show_site_adverts, show_upgrade_screen, days_to_keep_inactive_files, concurrent_uploads, '
                . 'concurrent_downloads, downloads_per_24_hours, max_download_filesize_allowed, max_remote_download_urls, '
                . 'level_type, on_upgrade_page FROM user_level', [], \PDO::FETCH_ASSOC);

        return ['data' => ['packages' => $packageListing]];
    }

}
