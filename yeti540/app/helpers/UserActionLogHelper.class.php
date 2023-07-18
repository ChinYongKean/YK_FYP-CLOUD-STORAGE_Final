<?php

namespace App\Helpers;

use App\Models\UserActionLog;

/**
 * User action log class.
 *
 * Class file for managing USER ACTION logging functionality
 *
 * @author      MFScripts.com - info@mfscripts.com
 * @version     1.0
 */
class UserActionLogHelper
{
    /**
     * @param string $logMessage
     * @param string $category
     * @param string $actionType
     * @param array $logParams
     * @param bool $adminAreaAction
     * @return void
     */
    static function log(
        string $logMessage,
        string $category = 'GENERAL',
        string $actionType = 'UNKNOWN',
        array $logParams = [],
        bool $adminAreaAction = false
    ) {
        // get current logged in user
        $Auth = AuthHelper::getAuth();

        // create entry
        $userActionLog = UserActionLog::create();
        $userActionLog->message = substr($logMessage, 0, 1000);
        $userActionLog->params = !empty($logParams) ? substr(json_encode($logParams, true), 0, 2000) : null;
        $userActionLog->category = self::convertCategoryStringToInt($category);
        $userActionLog->action_type = self::convertLogActionTypeStringToInt($actionType);
        $userActionLog->date_created = CoreHelper::sqlDateTime();
        $userActionLog->action_user_id = $Auth->loggedIn() ? (int)$Auth->id : null;
        $userActionLog->user_ip = StatsHelper::getIP();
        $userActionLog->admin_area_action = (int)$adminAreaAction;
        $userActionLog->file_id = $logParams['file_id'] ?? null;
        $userActionLog->user_id = $logParams['user_id'] ?? null;
        $userActionLog->save();
    }

    /**
     * @param string $logMessage
     * @param string $category
     * @param string $actionType
     * @param array $logParams
     * @return void
     */
    static function logAdmin(
        string $logMessage,
        string $category = 'GENERAL',
        string $actionType = 'UNKNOWN',
        array $logParams = []
    ) {
        self::log($logMessage, $category, $actionType, $logParams, true);
    }

    /**
     * @param string $category
     * @return int
     */
    static function convertCategoryStringToInt(string $category = 'GENERAL'): int
    {
        if (!in_array($category, UserActionLog::$LOG_CATEGORIES)) {
            $category = 'GENERAL';
        }

        return array_search($category, UserActionLog::$LOG_CATEGORIES);
    }

    /**
     * @param string $logActionType
     * @return int
     */
    static function convertLogActionTypeStringToInt(string $logActionType = 'UNKNOWN'): int
    {
        if (!in_array($logActionType, UserActionLog::$LOG_ACTION_TYPES)) {
            $category = 'UNKNOWN';
        }

        return array_search($logActionType, UserActionLog::$LOG_ACTION_TYPES);
    }

    /**
     * @param $oldData
     * @param $newData
     * @return array
     */
    static function getChangedData($oldData = [], $newData = [])
    {
        // get changed elements
        $oldData = (array)$oldData;
        $newData = (array)$newData;
        $changedItems = self::getDifferences($oldData, $newData);

        // ensure we include the 'from => to'
        $rs = [];
        if (!empty($changedItems)) {
            foreach ($changedItems as $k => $changedItem) {
                $rs[$k] = [];
                if (isset($oldData[$k])) {
                    $rs[$k]['from'] = self::maskSensitibeDataValue($k, $oldData[$k]);
                }
                if (isset($newData[$k])) {
                    $rs[$k]['to'] = self::maskSensitibeDataValue($k, $newData[$k]);
                }
            }
        }

        return $rs;
    }

    static function getDifferences(array $oldData = [], array $newData = []) {
        $rs = [];

        // check old data array
        foreach($oldData AS $k => $v) {
            if(isset($newData[$k]) && $newData[$k] != $v) {
                $rs[$k] = $v;
            }
        }

        // check new data array
        foreach($newData AS $k => $v) {
            if(isset($oldData[$k]) && $oldData[$k] != $v) {
                $rs[$k] = $v;
            }
        }

        return $rs;
    }

    /**
     * @param $oldData
     * @param $newData
     * @return array
     */
    static function getNewDataFromObject($newData = [])
    {
        // get elements
        $newItems = (array)$newData;

        // ensure we mask the data
        $rs = [];
        if (!empty($newItems)) {
            foreach ($newItems as $k => $newItem) {
                $rs[$k] = self::maskSensitibeDataValue($k, $newItem);
            }
        }

        return $rs;
    }

    static function maskSensitibeDataValue($columnName, $value) {
        if(self::strposa($columnName, ['password','serverAccess']) !== false) {
            return '[HIDDEN]';
        }

        return $value;
    }

    static function strposa($haystack, $needles = [])
    {
        $chr = [];
        foreach($needles as $needle) {
            $res = stripos($haystack, $needle);
            if ($res !== false) {
                return $res;
            }
        }

        return false;
    }
}
