<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\Order;

class OrderHelper
{
    // *************************************************
    // deprecated, use createByPackage() instead
    // *************************************************
    static function create($user_id, $payment_hash, $days, $amount, $upgradeFileId) {
        $upgradeUserId = null;
        if ((isset($_SESSION['plugin_rewards_aff_user_id'])) && ($user_id != (int) $_SESSION['plugin_rewards_aff_user_id'])) {
            $upgradeUserId = (int) $_SESSION['plugin_rewards_aff_user_id'];
        }

        // check for cookie
        if (isset($_COOKIE['source_aff_id'])) {
            if (((int) $_COOKIE['source_aff_id']) && ((int) $_COOKIE['source_aff_id'] != (int) $_SESSION['plugin_rewards_aff_user_id'])) {
                $upgradeUserId = (int) $_COOKIE['source_aff_id'];
            }

            // remove cookie
            unset($_COOKIE['source_aff_id']);
        }

        $order = Order::create();
        $order->user_id = $user_id;
        $order->payment_hash = $payment_hash;
        $order->days = $days;
        $order->amount = $amount;
        $order->order_status = 'pending';
        $order->date_created = date("Y-m-d H:i:s", time());
        $order->upgrade_file_id = $upgradeFileId;
        if ((int) $upgradeFileId) {
            // lookup user
            $db = Database::getDatabase();
            $upgradeUserId = (int) $db->getValue('SELECT userId '
                            . 'FROM file '
                            . 'WHERE id=' . (int) $upgradeFileId . ' '
                            . 'LIMIT 1');
        }

        $order->upgrade_user_id = $upgradeUserId;

        return $order->save();
    }

    static function createByPackageId($userId, $userLevelPricingId, $upgradeFileId) {
        $upgradeUserId = null;
        if ((isset($_SESSION['plugin_rewards_aff_user_id'])) && ($userId != (int) $_SESSION['plugin_rewards_aff_user_id'])) {
            $upgradeUserId = (int) $_SESSION['plugin_rewards_aff_user_id'];
        }

        // check for cookie
        if (isset($_COOKIE['source_aff_id'])) {
            if ((int) $_COOKIE['source_aff_id']) {
                $upgradeUserId = (int) $_COOKIE['source_aff_id'];
            }
        }

        // setup database
        $db = Database::getDatabase();

        // lookup days and amount based on $user_level_pricing_id
        $price = $db->getRow('SELECT id, pricing_label, period, price '
                . 'FROM user_level_pricing '
                . 'WHERE id = :id '
                . 'LIMIT 1', array(
                    'id' => $userLevelPricingId,
                ));
        if (!$price) {
            return false;
        }
        $amount = $price['price'];
        $days = (int) CoreHelper::convertStringDatePeriodToDays($price['period']);

        // load username for later
        $username = $db->getValue('SELECT username '
                . 'FROM users '
                . 'WHERE id = :id '
                . 'LIMIT 1', array(
                    'id' => $userId,
                ));
        if (!$username) {
            return false;
        }

        // create order hash for tracking
        $payment_hash = MD5(microtime() . $userId);

        // add order to the database
        $order = Order::create();
        $order->user_id = $userId;
        $order->payment_hash = $payment_hash;
        $order->user_level_pricing_id = $userLevelPricingId;
        $order->days = $days;
        $order->amount = $amount;
        $order->description = substr($price['pricing_label'] . ' ' . TranslateHelper::t('premium_for', 'Premium for') . ' ' . $username, 0, 100);
        $order->order_status = 'pending';
        $order->date_created = date("Y-m-d H:i:s", time());
        $order->upgrade_file_id = $upgradeFileId;
        if ((int) $upgradeFileId) {
            // lookup user
            $upgradeUserId = (int) $db->getValue('SELECT userId '
                            . 'FROM file '
                            . 'WHERE id = :id '
                            . 'LIMIT 1', array(
                                'id' => $upgradeFileId,
                            ));
        }
        $order->upgrade_user_id = $upgradeUserId;
        if($order->save()) {
            return $order;
        }

        return false;
    }

    static function loadByPaymentTracker($paymentHash) {
        return Order::loadOneByClause('payment_hash = :payment_hash', array(
                    'payment_hash' => $paymentHash,
        ));
    }

}
