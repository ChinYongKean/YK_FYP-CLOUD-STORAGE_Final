<?php

namespace App\Helpers;

/**
 * main file manager class
 */
class FileManagerHelper
{
    static function init($type = 'folder') {
        $formattedType = ucfirst(preg_replace("/[^A-Za-z]/", '', $type));
        $fullClassname = '\App\Services\File_Manager\\'.$formattedType.'FileManager';
        if(!class_exists($fullClassname)) {
            return false;
        }
        
        return new $fullClassname($type);
    }
    
    static function getFileBrowserSortingOptions() {
        $orderByOptions = [];
        $orderByOptions['order_by_filename_asc'] = TranslateHelper::t('order_by_filename_asc', 'Filename ASC');
        $orderByOptions['order_by_filename_desc'] = TranslateHelper::t('order_by_filename_desc', 'Filename DESC');
        $orderByOptions['order_by_uploaded_date_asc'] = TranslateHelper::t('order_by_uploaded_date_asc', 'Uploaded Date ASC');
        $orderByOptions['order_by_uploaded_date_desc'] = TranslateHelper::t('order_by_uploaded_date_desc', 'Uploaded Date DESC');
        $orderByOptions['order_by_downloads_asc'] = TranslateHelper::t('order_by_downloads_asc', 'Total Downloads ASC');
        $orderByOptions['order_by_downloads_desc'] = TranslateHelper::t('order_by_downloads_desc', 'Total Downloads DESC');
        $orderByOptions['order_by_filesize_asc'] = TranslateHelper::t('order_by_filesize_asc', 'Filesize ASC');
        $orderByOptions['order_by_filesize_desc'] = TranslateHelper::t('order_by_filesize_desc', 'Filesize DESC');
        $orderByOptions['order_by_last_access_date_asc'] = TranslateHelper::t('order_by_last_access_date_asc', 'Last Access Date ASC');
        $orderByOptions['order_by_last_access_date_desc'] = TranslateHelper::t('order_by_last_access_date_desc', 'Last Access Date DESC');

        return $orderByOptions;
    }
    
    static function getSortingDefault() {
        return 'order_by_filename_asc';
    }
    
    static function getPerPageOptions() {
        return array(15, 30, 50, 100, 250, 500);
    }
    
    static function getPerPageDefault() {
        return 30;
    }
    
    static function getViewLayoutDefault() {
        return SITE_CONFIG_FILE_MANAGER_DEFAULT_VIEW;
    }
}
