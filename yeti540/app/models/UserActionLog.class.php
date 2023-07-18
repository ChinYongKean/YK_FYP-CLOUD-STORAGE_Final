<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class UserActionLog extends Model
{
    private $userCache = [];

    public static $LOG_CATEGORIES = [
        1 => 'GENERAL',
        2 => 'LOGIN',
        3 => 'FILE',
        4 => 'ACCOUNT',
        5 => 'ADMIN',
        6 => 'FOLDER',
        7 => 'SHARE',
        8 => 'PLUGIN',
        9 => 'PAYMENT'
    ];

    public static $LOG_ACTION_TYPES = [
        1 => 'UNKNOWN',
        2 => 'READ',
        3 => 'ADD',
        4 => 'UPDATE',
        5 => 'DELETE',
        6 => 'UPLOAD',
        7 => 'DOWNLOAD',
        8 => 'WRITE',
        9 => 'IMPORT',
        10 => 'RESTORE',
        11 => 'REQUEST',
        12 => 'EXPORT',
    ];

    public function getCategoryLabel($humanReadable = false) {
        $category = self::$LOG_CATEGORIES[$this->category];
        if($humanReadable === true) {
            $category = str_replace('_', ' ', $category);
            $category = strtolower($category);
            $category = ucwords($category);
        }

        return $category;
    }

    public function getActionTypeLabel($humanReadable = false) {
        $actionType = self::$LOG_ACTION_TYPES[$this->action_type];
        if($humanReadable === true) {
            $actionType = str_replace('_', ' ', $actionType);
            $actionType = strtolower($actionType);
            $actionType = ucwords($actionType);
        }

        return $actionType;
    }

    public function getUserActionUser() {
        return User::loadOneById($this->action_user_id);
    }

    public function formatParamsForUserView() {
        // get the params as an array
        $formatted = json_decode($this->params, true);

        $failedResponse = '<ul><li><em>N/A</em></li></ul>';
        if(!is_array($formatted)) {
            return $failedResponse;
        }

        // we're only interested in the 'data' element
        if(!isset($formatted['data'])) {
            return $failedResponse;
        }

        // loop data element and create result
        $rs = [];
        foreach($formatted['data'] AS $field => $dataItem) {
            $value = $dataItem;
            if(is_array($dataItem)) {
                $aVal = [];
                if(isset($dataItem['from']) && strlen($dataItem['from'])) {
                    $aVal[] = $dataItem['from'];
                }
                if(isset($dataItem['to']) && strlen($dataItem['to'])) {
                    $aVal[] = $dataItem['to'];
                }
                $dataItem = implode(' => ', $aVal);
            }

            $rs[] = '<em>'.UCwords(str_replace('_', ' ', $field)) . ':</em>&nbsp;&nbsp;' . $dataItem;
        }

        if(empty($rs)) {
            return $failedResponse;
        }

        return '<ul><li>'.implode('</li><li>', $rs).'</li></ul>';
    }

    public function getActionUsername() {
        $userId = $this->action_user_id;
        if($userId === null) {
            return $userId;
        }

        if(!isset($this->userCache[$userId])) {
            $this->userCache[$userId] = User::loadOneById($userId);
            if(!$this->userCache[$userId]) {
                return 'Unknown';
            }
        }

        return $this->userCache[$userId]->username;
    }

    public function getIconNameFromCategory() {
        // icons sourced from /app/assets/admin/images/icons/system/
        switch($this->getCategoryLabel()) {
            case 'LOGIN':
                return 'lock';
            case 'FILE':
                return 'full_page';
            case 'ACCOUNT':
                return 'user';
            case 'ADMIN':
                return 'process';
            case 'FOLDER':
                return 'folder';
            case 'SHARE':
                return 'mail';
            case 'PAYMENT':
                return 'tag_blue';
            default:
                return 'info';
        }
    }

    public function getSmallIconPathFromCategory() {
        return CORE_ASSETS_ADMIN_WEB_ROOT.'/images/icons/system/16x16/'.$this->getIconNameFromCategory().'.png';
    }
}
