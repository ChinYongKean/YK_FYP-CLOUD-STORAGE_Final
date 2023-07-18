<?php

namespace Plugins\Paypal\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Helpers\UserActionLogHelper;
use App\Models\File;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Models\User;
use App\Helpers\CoreHelper;
use App\Helpers\LogHelper;
use App\Helpers\OrderHelper;
use App\Helpers\PluginHelper;
use App\Helpers\UserHelper;

class PaypalController extends BaseController
{

    public function upgradeBox($params = array()) {
        // pickup request for later
        $request = $this->getRequest();

        // load template
        return $this->render('upgrade_box.html', array_merge($params, array(
                    'folder_name' => 'paypal',
                    'gateway_label' => 'PayPal',
                    'i' => $request->query->has('i') ? $request->query->get('i') : '',
                    'f' => $request->query->has('f') ? $request->query->get('f') : '',
                        )), PLUGIN_DIRECTORY_ROOT . 'paypal/views');
    }

    public function pay($params = array()) {
        // pickup request for later
        $request = $this->getRequest();

        // load plugin details
        $pluginConfig = PluginHelper::pluginSpecificConfiguration('paypal');
        $pluginSettings = $pluginConfig['data']['plugin_settings'];
        $paypalEmail = '';
        $enableSandboxMode = 0;
        $enableSubscriptions = 0;
        if (strlen($pluginSettings)) {
            $pluginSettingsArr = json_decode($pluginSettings, true);
            $paypalEmail = $pluginSettingsArr['paypal_email'];
            $enableSandboxMode = (int) $pluginSettingsArr['enable_sandbox_mode'];
            $enableSubscriptions = (int) $pluginSettingsArr['enable_subscriptions'];
        }

        if (!$request->request->has('user_level_pricing_id')) {
            return $this->redirect(WEB_ROOT);
        }

        // require login
        if ($request->request->has('i') && strlen($request->request->get('i'))) {
            $user = User::loadOneByClause('identifier = :identifier', array(
                        'identifier' => $request->request->get('i')
            ));
            if (!$user) {
                return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode('Could not load user based on identifier, please contact support.'));
            }

            // setup variables for later
            $userId = $user->id;
            $username = $user->username;
            $userEmail = $user->email;
        }
        else {
            if (($response = $this->requireLogin('/register', 1)) !== false) {
                return $response;
            }

            // setup variables for later
            $Auth = $this->getAuth();
            $userId = $Auth->id;
            $username = $Auth->username;
            $userEmail = $Auth->email;
        }

        $userLevelPricingId = (int) $request->request->get('user_level_pricing_id');

        // check if we have a referring file
        $fileId = null;
        if ($request->request->has('f') && strlen($request->request->get('f'))) {
            $file = File::loadOneByShortUrl($request->request->get('f'));
            if ($file) {
                $fileId = $file->id;
            }
        }

        // create order entry
        $order = OrderHelper::createByPackageId($userId, $userLevelPricingId, $fileId);
        if ($order) {
            // redirect to the payment gateway
            $baseUrl = "https://www.paypal.com/cgi-bin/webscr";
            if ($enableSandboxMode == 1) {
                $baseUrl = "https://www.sandbox.paypal.com/cgi-bin/webscr";
            }

            // for subscriptions
            if ($enableSubscriptions == 1) {
                // load up pricing data for the subscription
                $db = Database::getDatabase();
                $pricingData = $db->getRow('SELECT pricing_label, period, price '
                        . 'FROM user_level_pricing '
                        . 'WHERE id = :id '
                        . 'LIMIT 1', array(
                    'id' => (int) $userLevelPricingId,
                ));
                if (!$pricingData) {
                    return $this->redirect(WEB_ROOT);
                }

                $periodType = substr($pricingData['period'], strlen($pricingData['period']) - 1, 1);
                $periodValue = substr($pricingData['period'], 0, strlen($pricingData['period']) - 1);
                $paypalUrl = $baseUrl . '?cmd=_xclick-subscriptions&notify_url=' . urlencode(WEB_ROOT . '/' . $pluginConfig['data']['folder_name'] . '/payment_ipn') . '&email=' . urlencode($userEmail) . '&return=' . urlencode(WEB_ROOT . '/payment_complete') . '&business=' . urlencode($paypalEmail) . '&item_name=' . urlencode($order->description) . '&item_number=1&a3=' . urlencode($order->amount) . '&no_shipping=2&no_note=1&currency_code=' . SITE_CONFIG_COST_CURRENCY_CODE . '&lc=' . substr(SITE_CONFIG_COST_CURRENCY_CODE, 0, 2) . '&bn=PP%2dBuyNowBF&charset=UTF%2d8&custom=' . $order->payment_hash . '&t3=' . $periodType . '&p3=' . (int) $periodValue . '&sra=1&src=1';
            }
            // for non subscriptions
            else {
                $paypalUrl = $baseUrl . '?cmd=_xclick&notify_url=' . urlencode(WEB_ROOT . '/' . $pluginConfig['data']['folder_name'] . '/payment_ipn') . '&email=' . urlencode($userEmail) . '&return=' . urlencode(WEB_ROOT . '/payment_complete') . '&business=' . urlencode($paypalEmail) . '&item_name=' . urlencode($order->description) . '&item_number=1&amount=' . urlencode($order->amount) . '&no_shipping=2&no_note=1&currency_code=' . SITE_CONFIG_COST_CURRENCY_CODE . '&lc=' . substr(SITE_CONFIG_COST_CURRENCY_CODE, 0, 2) . '&bn=PP%2dBuyNowBF&charset=UTF%2d8&custom=' . $order->payment_hash;
            }

            // user action logs
            UserActionLogHelper::log('User upgrade via PayPal requested', 'PAYMENT', 'REQUEST', [
                'plugin' => 'paypal',
                'user_id' => $this->getAuth()->id,
                'data' => UserActionLogHelper::getNewDataFromObject($order),
            ]);

            return $this->redirect($paypalUrl);
        }

        // fallback
        return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode('Failed creating order, please try again later.'));
    }

    public function paymentIpn($params = array()) {
        // pickup request for later
        $request = $this->getRequest();

        // log response
        LogHelper::setContext('paypal');
        LogHelper::info('Received called back on IPN: ' . print_r($_REQUEST, true));

        // load plugin details
        $pluginConfig = PluginHelper::pluginSpecificConfiguration('paypal');
        $pluginSettings = $pluginConfig['data']['plugin_settings'];
        $paypalEmail = '';
        $enableSandboxMode = 0;
        if (strlen($pluginSettings)) {
            $pluginSettingsArr = json_decode($pluginSettings, true);
            $paypalEmail = $pluginSettingsArr['paypal_email'];
            $enableSandboxMode = (int) $pluginSettingsArr['enable_sandbox_mode'];
            $enableSubscriptions = (int) $pluginSettingsArr['enable_subscriptions'];
        }

        // deal with subscriptions
        // source - https://www.paypal.com/uk/cgi-bin/webscr?cmd=p/acc/ipn-subscriptions-outside
        if ($request->request->has('txn_type')) {
            // load order using custom payment tracker hash
            $paymentTracker = $request->request->get('custom');
            $order = OrderHelper::loadByPaymentTracker($paymentTracker);
            if ($order) {
                // new subscription
                if ($request->request->get('txn_type') == 'subscr_signup') {
                    // only do this if subscriptions is enabled
                    if ($enableSubscriptions == 1) {
                        // insert subscription
                        $order->newSubscription('paypal', $request->request->get('subscr_id'));
                    }
                }
                elseif ($request->request->get('txn_type') == 'subscr_cancel') {
                    // cancel subscription
                    $order->cancelSubscription('paypal', $request->request->get('subscr_id'));

                    return $this->renderEmpty200Response();
                }
            }
        }

        // check for some required variables in the request
        if (!$request->request->has('payment_status') || !$request->request->has('business')) {
            return $this->renderEmpty200Response();
        }

        // make sure payment has completed and it's for the correct PayPal account
        if (($request->request->get('payment_status') == "Completed") && (strtolower($request->request->get('business')) == $paypalEmail)) {
            // load order using custom payment tracker hash
            $paymentTracker = $request->request->get('custom');

            // log
            LogHelper::info('Order is complete, loading based on order tracker "' . $paymentTracker . '"');

            // load order
            $order = OrderHelper::loadByPaymentTracker($paymentTracker);
            if ($order) {
                // log
                LogHelper::info('Loaded order id "' . $order->id . '"');

                $extendedDays = $order->days;
                $userId = $order->user_id;
                $upgradeUserId = $order->upgrade_user_id;
                $orderId = $order->id;

                // retain all posted gateway parameters
                $gatewayVars = "";
                foreach ($_REQUEST AS $k => $v) {
                    $gatewayVars .= $k . " => " . $v . "\n";
                }

                // insert payment log
                $paymentLog = PaymentLog::create();
                $paymentLog->user_id = $userId;
                $paymentLog->date_created = date("Y-m-d H:i:s", time());
                $paymentLog->amount = $request->request->get('mc_gross');
                $paymentLog->currency_code = $request->request->get('mc_currency');
                $paymentLog->from_email = $request->request->get('payer_email');
                $paymentLog->to_email = $request->request->get('business');
                $paymentLog->description = $order->description;
                $paymentLog->request_log = $gatewayVars;
                $paymentLog->payment_method = 'PayPal';
                $paymentLog->save();

                // make sure the amount paid matched what we expect
                if ($request->request->get('mc_gross') != $order->amount) {
                    // order amounts did not match
                    LogHelper::info('Failed - order amounts did not match');

                    return $this->renderEmpty200Response();
                }

                // make sure the order is pending
                if ($order->order_status == 'completed') {
                    // order has already been completed
                    LogHelper::info('Failed - order has already been completed');

                    return $this->renderEmpty200Response();
                }

                // update order status to paid
                $order->order_status = 'completed';
                if ($order->save() === false) {
                    // failed to update order
                    LogHelper::info('Failed - failed to update order');

                    return $this->renderEmpty200Response();
                }

                // log
                LogHelper::info('Updated order, extending account.');

                // extend/upgrade user
                $rs = UserHelper::upgradeUserByPackageId($userId, $order);
                if ($rs === false) {
                    // failed to update user
                    LogHelper::info('Failed - failed to update user');

                    return $this->renderEmpty200Response();
                }

                // log
                LogHelper::info('Account upgrade process complete.');

                // append any plugin includes
                PluginHelper::callHook('postUpgradePaymentIpn', array(
                    'order' => $order,
                ));
            }
        }
        else {
            // log
            LogHelper::info('Order is either not complete or PayPal email address does not match.');
        }

        return $this->renderEmpty200Response();
    }

}
