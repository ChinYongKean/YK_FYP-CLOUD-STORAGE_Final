<?php

namespace App\Helpers;

class NotificationHelper
{
    private static $pageErrorArr = [];
    private static $pageSuccessArr = [];

    static function isErrors() {
        if (count(self::$pageErrorArr)) {
            return true;
        }

        return false;
    }

    static function setError($errorMsg) {
        self::$pageErrorArr[] = $errorMsg;
    }

    static function getErrors() {
        return self::$pageErrorArr;
    }

    static function isSuccess() {
        if (count(self::$pageSuccessArr)) {
            return true;
        }

        return false;
    }

    static function setSuccess($sucessMsg) {
        self::$pageSuccessArr[] = $sucessMsg;
    }

    static function getSuccess() {
        return self::$pageSuccessArr;
    }

}
