<?php

namespace App\Helpers;

use App\Core\Database;

class SessionHelper
{

    public static function register() {
        session_set_save_handler(
                array('App\Helpers\SessionHelper', 'open'), array('App\Helpers\SessionHelper', 'close'), array('App\Helpers\SessionHelper', 'read'), array('App\Helpers\SessionHelper', 'write'), array('App\Helpers\SessionHelper', 'destroy'), array('App\Helpers\SessionHelper', 'gc')
        );

        // the following prevents unexpected effects when using objects as save handlers
        register_shutdown_function('session_write_close');
    }

    public static function open() {
        $db = Database::getDatabase(true);

        return $db->isConnected();
    }

    public static function close() {
        return true;
    }

    public static function read($id) {
        // @TODO - do we need 'true' in this call?
        $db = Database::getDatabase(true);
        $db->query('SELECT `data` '
                . 'FROM `sessions` '
                . 'WHERE `id` = :id '
                . 'LIMIT 1', array(
            'id' => $id,
                )
        );

        return $db->hasRows() ? $db->getValue() : '';
    }

    public static function write($id, $data) {
        // load user id if the user is logged in
        $userId = null;
        $Auth = AuthHelper::getAuth();
        if ($Auth->loggedIn()) {
            $userId = $Auth->id;
        }

        $db = Database::getDatabase(true);
        $db->query('INSERT INTO `sessions` '
                . '(`id`, `data`, `updated_on`, `user_id`) '
                . 'VALUES '
                . '(:id, :data, :updated_on, :user_id) '
                . 'ON DUPLICATE KEY UPDATE data=:data, updated_on=:updated_on, user_id=:user_id', array(
            'id' => $id,
            'data' => $data,
            'updated_on' => time(),
            'user_id' => $userId,
        ));

        return true;
    }

    public static function destroy($id) {
        $db = Database::getDatabase(true);
        $db->query('DELETE FROM `sessions` '
                . 'WHERE `id` = :id '
                . 'LIMIT 1', array(
            'id' => $id,
        ));

        return true;
    }

    /*
     * $max set in php.ini with session.gc-maxlifetime
     */

    public static function gc($max) {
        // max override due to issues on certain server installs
        $max = 60 * 60 * 24 * 14;

        $db = Database::getDatabase(true);
        $db->query('DELETE FROM `sessions` '
                . 'WHERE `updated_on` < :updated_on', array(
            'updated_on' => time() - $max,
        ));

        return true;
    }

    /**
     * Clears any existing sessions associated with a user
     * 
     * @param type $userId
     */
    public static function clearSessionByUserId($userId) {
        // deletes any existing sessions for the same user id
        if ((SITE_CONFIG_PREMIUM_USER_BLOCK_ACCOUNT_SHARING == 'yes') && CoreHelper::currentIsMainSite()) {
            $db = Database::getDatabase();
            $db->query('DELETE FROM sessions '
                    . 'WHERE user_id = :id', array(
                'id' => $userId,
            ));
        }
    }

    /**
     * Used to clear the session if the script needs to run for a long time, for
     * example on download. Avoids session locks which means the browser becomes
     * unresponsive for a period of time.
     */
    public static function releaseSession() {
        session_write_close();
    }

}
