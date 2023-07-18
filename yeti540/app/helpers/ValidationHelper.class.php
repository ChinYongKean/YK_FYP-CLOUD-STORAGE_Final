<?php

namespace App\Helpers;

class ValidationHelper
{

    // makes sure all content is made safe when outputted to screen
    static function safeOutputToScreen($input, $allowedChars = null, $length = null) {
        if ($allowedChars != null) {
            $input = self::removeInvalidCharacters($input, $allowedChars);
        }

        if ($length != null) {
            if (strlen($input) > $length) {
                $input = substr($input, 0, $length - 3) . '...';
            }
        }

        $input = htmlspecialchars($input, ENT_QUOTES, "UTF-8");

        return $input;
    }

    // tests for a valid email address and optionally tests for valid MX records, too.
    static function validEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    static function validUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]+$/', $username);
    }

    static function containsInvalidCharacters($input, $allowedChars = 'abcdefghijklmnopqrstuvwxyz 1234567890') {
        if (self::removeInvalidCharacters($input, $allowedChars) != $input) {
            return true;
        }

        return false;
    }

    static function removeInvalidCharacters($input, $allowedChars = 'abcdefghijklmnopqrstuvwxyz 1234567890') {
        $str = '';
        for ($i = 0; $i < strlen($input); $i++) {
            if (!stristr($allowedChars, $input[$i])) {
                continue;
            }

            $str .= $input[$i];
        }

        return $str;
    }

    static function validDate($date, $format = 'Y-m-d H:i:s') {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) == $date;
    }

    static function validIPAddress($ipAddress) {
        return filter_var($ipAddress, FILTER_VALIDATE_IP);
    }

}
