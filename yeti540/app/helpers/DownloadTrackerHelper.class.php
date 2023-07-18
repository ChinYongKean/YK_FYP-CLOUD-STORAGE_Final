<?php

namespace App\Helpers;

use App\Core\Database;

class DownloadTrackerHelper
{
    public $errorMsg = null;
    public $file = null;
    public $id = null;

    function __construct($file) {
        $this->errorMsg = null;
        $this->file = $file;
    }

    function create($startOffset = 0, $seekEnd = -1) {
        $db = Database::getDatabase();

        // get logged in username
        $downloadUsername = '';
        $Auth = AuthHelper::getAuth();
        if ($Auth->loggedIn()) {
            $downloadUsername = $Auth->username;
        }

        // add tracker to db
        $db->query("INSERT INTO download_tracker (file_id, ip_address, download_username, date_started, date_updated, status, start_offset, seek_end) "
                . "VALUES (:file_id, :ip_address, :download_username, NOW(), NOW(), 'downloading', :start_offset, :seek_end)", array(
            'file_id' => (int) $this->file->id,
            'ip_address' => CoreHelper::getUsersIPAddress(),
            'download_username' => $downloadUsername,
            'start_offset' => $startOffset,
            'seek_end' => $seekEnd,
                )
        );
        $this->id = $db->insertId();
        $db->close();

        return $this->id;
    }

    function update() {
        $db = Database::getDatabase(true);
        $rs = $db->query("UPDATE download_tracker "
                . "SET date_updated=NOW(), "
                . "status='downloading' "
                . "WHERE id=" . (int) $this->id);
        $db->close();

        return $rs;
    }

    function finish() {
        $db = Database::getDatabase(true);
        $rs = $db->query("UPDATE download_tracker "
                . "SET date_updated=NOW(), "
                . "date_finished=NOW(), "
                . "status='finished' "
                . "WHERE id=" . (int) $this->id);
        $db->close();

        return $rs;
    }

    static function clearTimedOutDownloads() {
        $db = Database::getDatabase(true);
        $db->query("UPDATE download_tracker "
                . "SET date_finished=NOW(), "
                . "status='cancelled' "
                . "WHERE status='downloading' "
                . "AND date_updated < DATE_SUB(NOW(), INTERVAL " . (int) DOWNLOAD_TRACKER_UPDATE_FREQUENCY . " second)");
        $db->close();
    }

    static function purgeDownloadData() {
        $db = Database::getDatabase(true);
        $db->query("DELETE FROM download_tracker "
                . "WHERE date_started < DATE_SUB(NOW(), INTERVAL " . (int) DOWNLOAD_TRACKER_PURGE_PERIOD . " day)");
        $db->close();
    }

}
