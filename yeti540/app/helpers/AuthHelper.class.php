<?php

namespace App\Helpers;

use App\Core\Auth;
use App\Models\BannedIp;
use App\Models\LoginFailure;
use App\Models\LoginSuccess;

class AuthHelper
{
    // used to track current Auth
    private static $me;

    /**
     * Standard singleton
     * @return Auth
     */
    public static function getAuth($userToImpersonate = null) {
        if (is_null(self::$me)) {
            self::$me = new Auth($userToImpersonate);
        }

        return self::$me;
    }

    public static function logFailedLoginAttempt($ipAddress, $loginUsername = '') {
        // clear anything older than 24 hours
        self::clearOldLoginAttempts();

        // add failed login attempt
        $loginFailure = new LoginFailure();
        $loginFailure->ip_address = $ipAddress;
        $loginFailure->date_added = CoreHelper::sqlDateTime();
        $loginFailure->username = $loginUsername;
        $loginFailure->save();

        // block IP address if greater than x failed logins
        if ((int) SITE_CONFIG_SECURITY_BLOCK_IP_LOGIN_ATTEMPTS > 0) {
            $failedAttempts = LoginFailure::count('ip_address = :ip_address', array(
                        'ip_address' => $ipAddress,
            ));
            if ($failedAttempts >= SITE_CONFIG_SECURITY_BLOCK_IP_LOGIN_ATTEMPTS) {
                // add IP address to block list
                $bannedIp = new BannedIp();
                $bannedIp->ipAddress = $ipAddress;
                $bannedIp->banType = 'Login';
                $bannedIp->banNotes = 'Banned after too many failed logins.';
                $bannedIp->dateBanned = CoreHelper::sqlDateTime();
                $bannedIp->banExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $bannedIp->save();
            }
        }
    }

    public static function clearOldLoginAttempts() {
        // clear anything older than 24 hours
        LoginFailure::deleteByClause('date_added < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    }

    public static function clearAllLoginAttemptsForIp($ipAddress) {
        // clear anything older than 24 hours
        LoginFailure::deleteByClause('ip_address = :ip_address', array(
            'ip_address' => $ipAddress,
        ));
        
        // clear any banned ips
        BannedIp::deleteByClause('ipAddress = :ipAddress', array(
            'ipAddress' => $ipAddress,
        ));
    }

    public static function logSuccessfulLogin($userId, $ipAddress) {
        // clear anything older than 1 month
        self::clearOldSuccessfulLogins();

        // try to find country code based on IP address
        $countryCode = StatsHelper::getCountry($ipAddress);
        if (($countryCode == 'unknown') || ($countryCode == 'ZZ') || (!$countryCode)) {
            $countryCode = '';
        }
        $countryCode = substr($countryCode, 0, 2);

        // add success login attempt
        $loginSuccess = new LoginSuccess();
        $loginSuccess->ip_address = $ipAddress;
        $loginSuccess->date_added = CoreHelper::sqlDateTime();
        $loginSuccess->user_id = $userId;
        $loginSuccess->country_code = $countryCode;
        $loginSuccess->save();
    }

    public static function clearOldSuccessfulLogins() {
        // clear anything older than 1 month
        LoginSuccess::deleteByClause('date_added < DATE_SUB(NOW(), INTERVAL 1 MONTH)');
    }
}
