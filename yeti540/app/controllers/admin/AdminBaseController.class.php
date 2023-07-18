<?php

namespace App\Controllers\admin;

use App\Core\BaseController;
use App\Core\Database;
use App\Helpers\AdminHelper;
use App\Helpers\AuthHelper;
use App\Helpers\CoreHelper;
use App\Helpers\CrossSiteActionHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use Symfony\Component\HttpFoundation\Response;

class AdminBaseController extends BaseController
{
    public function restrictAdminAccess($minAccessLevel = 20, $ignoreLogin = false) {
        // for cross domain access
        CoreHelper::allowCrossSiteAjax();

        // process csaKeys and authenticate user
        $request = $this->getRequest();
        $csaKey1 = $request->query->has('csaKey1') ? trim($request->query->get('csaKey1')) : '';
        $csaKey2 = $request->query->has('csaKey2') ? trim($request->query->get('csaKey2')) : '';
        if (strlen($csaKey1) && strlen($csaKey2)) {
            CrossSiteActionHelper::setAuthFromKeys($csaKey1, $csaKey2);
        }

        if ($ignoreLogin === false) {
            $Auth = AuthHelper::getAuth();
            $Auth->requireAccessLevel($minAccessLevel, ADMIN_WEB_ROOT . "/login");
        }
    }
    
    public function render($template, $params = array(), $templatePath = null, $includeCoreTemplateParam = true):Response {
        return parent::render($template, array_merge($params, $this->getHeaderParams()), $templatePath, $includeCoreTemplateParam);
    }

    public function getHeaderParams() {
        // get database
        $db = Database::getDatabase();
        
        // load totals for navigation
        $totalReports = (int) $db->getValue("SELECT COUNT(id) AS total "
                . "FROM file_report "
                . "WHERE report_status='pending'");
        $totalPendingFileActions = (int) $db->getValue('SELECT COUNT(id) AS total '
                . 'FROM file_action '
                . 'WHERE status=\'pending\' '
                . 'OR status=\'processing\'');

        // load all config groups for navigation
        $navGroupDetails = $db->getRows("SELECT config_group "
                . "FROM site_config "
                . "WHERE config_group != 'System' "
                . "AND site_settings_hidden != 1 "
                . "GROUP BY config_group "
                . "ORDER BY config_group");

        // preload plugin list for navigation
        $navPluginList = $db->getRows("SELECT id, folder_name, plugin_name "
            . "FROM plugin "
            . "WHERE is_installed = 1 "
            . "ORDER BY plugin_name");

        // preload all site settings for quick search
        $siteSettingItems = $db->getRows("SELECT label, config_group, config_key "
            . "FROM site_config "
            . "WHERE config_group != 'System' "
            . "AND site_settings_hidden != 1 "
            . "ORDER BY label");
        $siteSettings = [];
        foreach($siteSettingItems AS $siteSettingItem) {
            $siteSettings[] = [
                'label' => $siteSettingItem['config_group'] .' > '.ucwords($siteSettingItem['label']),
                'url' => ADMIN_WEB_ROOT.'/setting_manage?filterByGroup='.urlencode($siteSettingItem['config_group']).'&selectedItem='.urlencode($siteSettingItem['config_key']),
            ];
        }
        
        $params = array(
            'totalReports' => $totalReports,
            'totalPendingFileActions' => $totalPendingFileActions,
            'navGroupDetails' => $navGroupDetails,
            'siteSettings' => $siteSettings,
            'adminThemeCss' => ThemeHelper::getAdminThemeCss(),
            'navPluginList' => $navPluginList,
            'currentProductUrl' => ThemeHelper::getCurrentProductUrl(),
            'currentProductName' => ThemeHelper::getCurrentProductName(),
            'currentProductType' => ThemeHelper::getCurrentProductType(),
            'currentThemeName' => ThemeHelper::getCurrentThemeName(),
            'themeNav' => ThemeHelper::getThemeAdminNavV2(),
            'pluginNav' => PluginHelper::getPluginAdminNavV2(),
            'scriptVersion' => CoreHelper::getScriptInstalledVersion(),
            'currentPageUrl' => $_SERVER['REQUEST_URI'],
            'msg_page_notifications' => AdminHelper::compileNotifications(),
        );
        
        return $params;
    }
}
