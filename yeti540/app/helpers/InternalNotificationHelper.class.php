<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\InternalNotification;

class InternalNotificationHelper {

    static function add($userId, $content, $type = 'entypo-info', $href_url = null, $onclick = null) {
        // create internal notification entry
        $internalNotification = new InternalNotification();
        $internalNotification->to_user_id = $userId;
        $internalNotification->date_added = CoreHelper::sqlDateTime();
        $internalNotification->content = substr($content, 0, 255);
        $internalNotification->href_url = substr($href_url, 0, 255);
        $internalNotification->onclick = substr($onclick, 0, 255);
        $internalNotification->notification_icon = $type;
        $internalNotification->save();

        return $internalNotification;
    }

    static function loadRecentByUser($userId) {
        // load the past 14 days
        return InternalNotification::loadByClause('to_user_id = :to_user_id '
                . 'AND date_added >= DATE_SUB(NOW(), INTERVAL 14 DAY)', array(
            'to_user_id' => $userId,
        ), 'date_added DESC');
    }

    static function markAllReadByUserId($userId) {
        // clear all notifications based on user id
        return Database::getDatabase()->query('UPDATE internal_notification '
                . 'SET is_read = 1 '
                . 'WHERE to_user_id = :to_user_id', array(
            'to_user_id' => $userId,
        ));
    }

}
