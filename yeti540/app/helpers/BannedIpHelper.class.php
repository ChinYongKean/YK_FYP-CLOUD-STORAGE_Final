<?php

namespace App\Helpers;

use App\Models\BannedIp;

class BannedIpHelper
{

    static function getBannedIPData($userIP = null) {
        // if we don't have a userIP into the function, use the current users
        // IP address
        if ($userIP === null) {
            $userIP = CoreHelper::getUsersIPAddress();
        }

        // load banned IP entry based on current users IP address
        $bannedIp = BannedIp::loadOne('ipAddress', $userIP);
        if (!$bannedIp) {
            return false;
        }

        return $bannedIp;
    }

    static function getBannedType() {
        // load banned IP entry based on current users IP address
        $bannedIp = self::getBannedIPData(CoreHelper::getUsersIPAddress());
        if (!$bannedIp) {
            return false;
        }

        return $bannedIp->banType;
    }

    static function clearExpiredBannedIps() {
        // load all expired, limit to 100 each time to avoid memory issues
        $expiredBannedIps = BannedIp::loadByClause('banExpiry IS NOT NULL AND banExpiry < NOW()', array(), null, 100);
        if ($expiredBannedIps) {
            // set to different log file
            LogHelper::setContext('banned_ips');

            // loop the expired bans and remove
            foreach ($expiredBannedIps AS $expiredBannedIp) {
                // log the removal
                LogHelper::info('Expired banned ip: ' . $expiredBannedIp->ipAddress . '. Date Banned: ' . CoreHelper::formatDate($expiredBannedIp->dateBanned) . '. Type: ' . $expiredBannedIp->banType . '. Notes: ' . (strlen($expiredBannedIp->banNotes) ? $expiredBannedIp->banNotes : '-') . '. Expiry: ' . CoreHelper::formatDate($expiredBannedIp->banExpiry));

                // remove
                $expiredBannedIp->delete();
            }

            // revert logging
            LogHelper::revertContext();
        }
    }

}
