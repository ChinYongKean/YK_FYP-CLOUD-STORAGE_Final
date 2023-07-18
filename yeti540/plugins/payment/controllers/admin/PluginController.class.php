<?php

namespace Plugins\Payment\Controllers\Admin;

use App\Core\Database;
use App\Controllers\Admin\PluginController AS CorePluginController;
use App\Helpers\AdminHelper;
use App\Helpers\CoreHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\UserActionLogHelper;
use App\Models\Plugin;
use Plugins\Payment\Models\PluginPaymentGatewaysConfigured;
use Omnipay\Omnipay;

class PluginController extends CorePluginController
{

    public function pluginSettings() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'payment';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', array(
                    'folder_name' => $folderName,
        ));

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        // prepare variables
        $plugin_enabled = (int) $plugin->plugin_enabled;

        // handle page submissions
        if ($request->request->has('submitted')) {
            // get variables
            $oldPluginSettings = json_decode($plugin->plugin_settings, true);
            $plugin_enabled = (int) $request->request->get('plugin_enabled');
            $plugin_enabled = $plugin_enabled != 1 ? 0 : 1;

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t('no_changes_in_demo_mode', 'No change permitted in demo mode.'));
            }

            // update the settings
            if (AdminHelper::isErrors() == false) {
                // compile new settings
                $settingsArr = [];

                // update the plugin settings
                $plugin->plugin_enabled = $plugin_enabled;
                $plugin->plugin_settings = json_encode($settingsArr);
                $plugin->save();

                // user action logs
                UserActionLogHelper::logAdmin('Edited "'.$folderName.'" plugin settings', 'ADMIN', 'UPDATE', [
                    'plugin' => $folderName,
                    'data' => UserActionLogHelper::getChangedData($oldPluginSettings, $settingsArr),
                ]);

                // set onscreen alert
                AdminHelper::setSuccess('Plugin settings updated.');
            }
        }

        // load template
        return $this->render('admin/plugin_settings.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'yesNoOptions' => array(
                        0 => 'No',
                        1 => 'Yes'),
                    'plugin_enabled' => $plugin_enabled,
                        ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

    public function gatewayManage() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'payment';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', array(
                    'folder_name' => $folderName,
        ));

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        // get the plugin
        $pluginObj = PluginHelper::getInstance('payment');

        // preload available gateways for bottom list
        $availableGateways = $pluginObj->getAvailableGatewayGroups();

        // make sure the plugin is enabled
        if (PluginHelper::pluginEnabled('payment') === false) {
            AdminHelper::setError(AdminHelper::t("plugin_not_enabled", "Payment plugin is not enabled, please enable it before adding payment gateways."));
        }

        // make sure the site is using https
        if (_CONFIG_SITE_PROTOCOL !== 'https') {
            AdminHelper::setError(AdminHelper::t("site_not_secure", "Your site is not running on https, some payment gateways will not allow non-https sites. It is highly recommended you install a SSL certificate on your site to avoid callback issues."));
        }

        // action any requests
        if ($request->query->has('remove_gateway_config_id')) {
            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $db->query('DELETE FROM plugin_payment_gateways_configured '
                        . 'WHERE id = :id '
                        . 'LIMIT 1', array(
                    'id' => (int) $request->query->get('remove_gateway_config_id'),
                ));
            }
        }

        if ($request->query->has('enable_gateway_config_id')) {
            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $db->query('UPDATE plugin_payment_gateways_configured '
                        . 'SET status = "active" '
                        . 'WHERE id = :id '
                        . 'LIMIT 1', array(
                    'id' => (int) $request->query->get('enable_gateway_config_id'),
                ));
            }
        }

        if ($request->query->has('disable_gateway_config_id')) {
            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $db->query('UPDATE plugin_payment_gateways_configured '
                        . 'SET status = "disabled" '
                        . 'WHERE id = :id '
                        . 'LIMIT 1', array(
                    'id' => (int) $request->query->get('disable_gateway_config_id'),
                ));
            }
        }

        // load template
        return $this->render('admin/gateway_manage.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'availableGateways' => $availableGateways,
                        ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

    public function ajaxGatewayManage() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $iDisplayLength = (int) $request->query->get('iDisplayLength');
        $iDisplayStart = (int) $request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';

        // get sorting columns
        $iSortCol_0 = (int) $request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'plugin_payment_gateways.label';
        switch ($sortColumnName) {
            case 'date_created':
                $sort = 'date_created';
                break;
            case 'gateway':
                $sort = 'plugin_payment_gateways.label';
                break;
            case 'status':
                $sort = 'status';
                break;
        }

        $sqlClause = "WHERE 1=1 ";

        $totalRS = $db->getValue("SELECT COUNT(plugin_payment_gateways_configured.id) AS total "
                . "FROM plugin_payment_gateways_configured "
                . "LEFT JOIN plugin_payment_gateways ON plugin_payment_gateways_configured.gateway_id = plugin_payment_gateways.id "
                . $sqlClause);
        $gatewayConfigs = $db->getRows("SELECT plugin_payment_gateways_configured.*, "
                . "plugin_payment_gateways.label "
                . "FROM plugin_payment_gateways_configured "
                . "LEFT JOIN plugin_payment_gateways ON plugin_payment_gateways_configured.gateway_id = plugin_payment_gateways.id "
                . $sqlClause . " "
                . "ORDER BY " . $sort . " " . $db->escape($sSortDir_0) . " "
                . "LIMIT " . $iDisplayStart . ", " . $iDisplayLength);

        $data = [];
        if (count($gatewayConfigs) > 0) {
            foreach ($gatewayConfigs AS $gatewayConfig) {
                $lRow = [];
                $lRow[] = '<img src="' . PLUGIN_WEB_ROOT . '/payment/assets/img/icons/16px.png" width="16" height="16" title="request" alt="gateway"/>';
                $lRow[] = '<a href="#" onClick="editGatewayForm(' . (int) $gatewayConfig['id'] . '); return false;">' . ValidationHelper::safeOutputToScreen($gatewayConfig['label']) . '</a>';
                $lRow[] = CoreHelper::formatDate($gatewayConfig['date_created'], SITE_CONFIG_DATE_TIME_FORMAT);
                $lRow[] = '<span class="statusText' . ucfirst($gatewayConfig['status']) . '">' . ValidationHelper::safeOutputToScreen($gatewayConfig['status']) . '</span>';

                $links = [];
                $links[] = '<a href="#" onClick="editGatewayForm(' . (int) $gatewayConfig['id'] . '); return false;">edit</a>';
                $links[] = '<a href="' . ADMIN_WEB_ROOT . '/plugin/payment/gateway_manage?remove_gateway_config_id=' . (int) $gatewayConfig['id'] . '" onClick="return confirm(\'Are you sure you to remove this payment gateway configuration? Users on your site will no longer be able to submit payment using this payment method.\');">remove</a>';
                if ($gatewayConfig['status'] === 'active') {
                    $links[] = '<a href="' . ADMIN_WEB_ROOT . '/plugin/payment/gateway_manage?disable_gateway_config_id=' . (int) $gatewayConfig['id'] . '">disable</a>';
                }
                else {
                    $links[] = '<a href="' . ADMIN_WEB_ROOT . '/plugin/payment/gateway_manage?enable_gateway_config_id=' . (int) $gatewayConfig['id'] . '">activate</a>';
                }
                $lRow[] = implode(" | ", $links);

                $data[] = $lRow;
            }
        }

        $resultArr = [];
        $resultArr["sEcho"] = intval($request->query->get('sEcho'));
        $resultArr["iTotalRecords"] = (int) $totalRS;
        $resultArr["iTotalDisplayRecords"] = $resultArr["iTotalRecords"];
        $resultArr["aaData"] = $data;

        return $this->renderJson($resultArr);
    }

    public function ajaxGatewayAddForm() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load our payment object for later
        $pluginObj = PluginHelper::getInstance('payment');

        // preload available gateways for select
        $availableGateways = $pluginObj->getAvailableGateways();

        // is this an edit?
        $gatewayConfigId = null;
        $gatewayId = null;
        if ($request->request->has('gEditGatewayId') && (int) $request->request->get('gEditGatewayId')) {
            $gatewayConfigDetails = $db->getRow("SELECT * "
                    . "FROM plugin_payment_gateways_configured "
                    . "WHERE id = :id "
                    . "LIMIT 1", array(
                'id' => $request->request->get('gEditGatewayId'),
            ));
            if ($gatewayConfigDetails) {
                $gatewayConfigId = $gatewayConfigDetails['id'];
                $gatewayId = $gatewayConfigDetails['gateway_id'];
            }
        }

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['html'] = $this->getRenderedTemplate('admin/ajax/gateway_add_form.html', array(
            'availableGateways' => $availableGateways,
            'gatewayConfigId' => $gatewayConfigId,
            'gatewayId' => $gatewayId,
                ), PLUGIN_DIRECTORY_ROOT . 'payment/views');

        // output response
        return $this->renderJson($result);
    }

    public function ajaxGatewayAddFormConfig() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load our payment object for later
        $pluginObj = PluginHelper::getInstance('payment');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        // load the gateway we're interested in
        $gateway = $db->getRow('SELECT * '
                . 'FROM plugin_payment_gateways '
                . 'WHERE id = :id '
                . 'LIMIT 1', array(
            'id' => $request->request->get('selectedGateway'),
        ));
        if (!$gateway) {
            $result['msg'] = 'Failed load gateway, please try again later.';

            // output response
            return $this->renderJson($result);
        }

        // load our gateway object
        $gatewayObj = Omnipay::create($gateway['class_name']);
        $gatewayParams = $gatewayObj->getParameters();

        // is this an edit?
        $gatewaySettings = null;
        if ($request->request->has('gEditGatewayId') && (int) $request->request->get('gEditGatewayId')) {
            $gatewayConfigDetails = $db->getRow("SELECT * "
                    . "FROM plugin_payment_gateways_configured "
                    . "WHERE id = :id "
                    . "LIMIT 1", array(
                'id' => $request->request->get('gEditGatewayId'),
            ));
            if ($gatewayConfigDetails) {
                $gatewaySettings = json_decode($gatewayConfigDetails['params'], true);
            }
        }

        $result['html'] = $this->getRenderedTemplate('admin/ajax/gateway_add_form_config.html', array(
            'AdminHelper' => new AdminHelper,
            'gateway' => $gateway,
            'gatewayParams' => $gatewayParams,
            'gatewaySettings' => $gatewaySettings,
                ), PLUGIN_DIRECTORY_ROOT . 'payment/views');

        // output response
        return $this->renderJson($result);
    }

    public function ajaxGatewayAddProcess() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // pickup params
        $gateway_id = (int) $request->request->get('gateway_id');
        $gateway_settings = $request->request->get('gateway_settings');
        $gateway_config_id = $request->request->has('gateway_config_id') ? (int) $request->request->get('gateway_config_id') : null;

        // load the gateway for later
        $gateway = $db->getRow('SELECT * '
                . 'FROM plugin_payment_gateways '
                . 'WHERE id = :id '
                . 'LIMIT 1', array(
            'id' => (int) $gateway_id,
        ));

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        // validate submission
        if ($gateway_id === 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("gateway_invalid", "Please specify the gateway.");
        }
        elseif ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }

        if (strlen($result['msg']) === 0) {
            if ($gateway_config_id > 0) {
                // update the existing record
                $pluginPaymentGatewaysConfigured = PluginPaymentGatewaysConfigured::loadOneById($gateway_config_id);
                $pluginPaymentGatewaysConfigured->gateway_id = $gateway_id;
                $pluginPaymentGatewaysConfigured->params = json_encode($gateway_settings, true);
                $pluginPaymentGatewaysConfigured->date_updated = CoreHelper::sqlDateTime();
                $pluginPaymentGatewaysConfigured->save();

                $result['error'] = false;
                $result['msg'] = 'Gateway config for \'' . $gateway['label'] . '\' updated.';
            }
            else {
                // add the gateway entry
                $pluginPaymentGatewaysConfigured = new PluginPaymentGatewaysConfigured();
                $pluginPaymentGatewaysConfigured->gateway_id = $gateway_id;
                $pluginPaymentGatewaysConfigured->params = json_encode($gateway_settings, true);
                $pluginPaymentGatewaysConfigured->status = 'active';
                $pluginPaymentGatewaysConfigured->date_created = CoreHelper::sqlDateTime();
                if (!$pluginPaymentGatewaysConfigured->save()) {
                    $result['error'] = true;
                    $result['msg'] = AdminHelper::t("gateway_error_problem_record", "There was a problem adding the gateway config, please try again.");
                }
                else {
                    $result['error'] = false;
                    $result['msg'] = 'Gateway config for \'' . $gateway['label'] . '\' has been added.';
                }
            }
        }

        // output response
        return $this->renderJson($result);
    }

}
