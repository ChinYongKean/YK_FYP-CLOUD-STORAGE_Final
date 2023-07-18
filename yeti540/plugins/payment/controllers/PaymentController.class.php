<?php

namespace Plugins\Payment\Controllers;

use App\Core\Database;
use App\Core\BaseController;
use App\Helpers\UserActionLogHelper;
use App\Models\File;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Models\User;
use App\Helpers\CoreHelper;
use App\Helpers\InternalNotificationHelper;
use App\Helpers\LogHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\OrderHelper;
use App\Helpers\PluginHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UserHelper;
use Omnipay\Omnipay;

class PaymentController extends BaseController
{

    public function upgradeBox($params = array()) {
        // pickup request for later
        $request = $this->getRequest();

        // preload enabled gateways
        $pluginObj = PluginHelper::getInstance('payment');
        $enabledGateways = $pluginObj->getEnabledGateways();

        // load template
        return $this->render('upgrade_box.html', array_merge($params, array(
                    'folder_name' => 'payment',
                    'enabledGateways' => $enabledGateways,
                    'i' => $request->query->has('i') ? $request->query->get('i') : '',
                    'f' => $request->query->has('f') ? $request->query->get('f') : '',
                        )), PLUGIN_DIRECTORY_ROOT . 'payment/views');
    }

    public function upgradeConfirmation() {
        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $Auth = $this->getAuth();

        // load plugin details
        $folderName = 'payment';
        $pluginConfig = PluginHelper::pluginSpecificConfiguration($folderName);

        // require login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        if ($request->request->has('user_level_pricing_id') === false) {
            return $this->redirect(WEB_ROOT . '/upgrade');
        }

        if ($request->query->has('cid') === false) {
            return $this->redirect(WEB_ROOT . '/upgrade');
        }

        // prepare template parameters
        $pageTitlePrepend = ucwords(TranslateHelper::t('premium', 'premium'));
        if ($Auth->level_id > 0) {
            $pageTitlePrepend = ucwords(TranslateHelper::t('upgrade', 'upgrade'));
        }

        // load current user type
        $accountType = $db->getValue('SELECT level_type '
                . 'FROM user_level '
                . 'WHERE id = :id '
                . 'LIMIT 1', array(
            'id' => $Auth->level_id,
        ));

        // get user account paid details
        $user = User::loadOneById($Auth->id);
        $accountExpiry = (in_array($accountType, array('admin', 'moderator'))) ? ucwords(TranslateHelper::t('never', 'never')) : CoreHelper::formatDate($user->paidExpiryDate);


        // prep dates
        $monthDropdown = [];
        $monthDropdownItems = range(1, 12);
        foreach ($monthDropdownItems AS $monthDropdownItem) {
            $monthDropdown[$monthDropdownItem] = str_pad($monthDropdownItem, 2, "0", STR_PAD_LEFT) . ' - ' . date('F', mktime(0, 0, 0, $monthDropdownItem, 10));
        }
        $yearDropdown = [];
        $yearDropdownItems = range(date('Y'), date('Y') + 20);
        foreach ($yearDropdownItems AS $yearDropdownItem) {
            $yearDropdown[$yearDropdownItem] = $yearDropdownItem;
        }

        // load user
        $userLevelPricingId = (int) $request->request->get('user_level_pricing_id');

        // check if we have a referring file
        $fileId = null;
        if ($request->request->has('f') && strlen($request->request->get('f'))) {
            $file = File::loadOneByShortUrl($request->request->get('f'));
            if ($file) {
                $fileId = $file->id;
            }
        }

        // load the payment gateway data
        $gatewayParams = $db->getRow('SELECT plugin_payment_gateways_configured.params, plugin_payment_gateways.class_name, gateway_type,'
                . 'plugin_payment_gateways.gateway_additional_params, plugin_payment_gateways.label '
                . 'FROM plugin_payment_gateways_configured '
                . 'LEFT JOIN plugin_payment_gateways ON plugin_payment_gateways_configured.gateway_id = plugin_payment_gateways.id '
                . 'WHERE plugin_payment_gateways_configured.id = :id '
                . 'LIMIT 1', array(
            'id' => (int) $request->query->get('cid'),
        ));
        if (!$gatewayParams) {
            return $this->redirect(WEB_ROOT . '/upgrade');
        }

        // prep the gateway class
        $gatewayParamsArr = json_decode($gatewayParams['params'], true);

        // setup the payment gateway object
        $gateway = Omnipay::create($gatewayParams['class_name']);
        foreach ($gatewayParamsArr AS $k => $gatewayParam) {
            $funcName = 'set' . ucfirst($k);
            if (method_exists($gateway, $funcName)) {
                $gateway->{$funcName}($gatewayParam);
            }
        }
        $cardNumber = '';
        $cardHolderName = '';
        $expiryMonth = '';
        $expiryYear = '';
        $cvv = '';

        // handle submissions or offsite gateways
        if ($request->request->has('submitted') || ($gatewayParams['gateway_type'] === 'offsite')) {
            // get form variables for onsite orders
            if ($gatewayParams['gateway_type'] === 'onsite') {
                $cardNumber = trim($request->request->get('cardNumber'));
                $cardHolderName = trim($request->request->get('cardHolderName'));
                $expiryMonth = trim($request->request->get('expiryMonth'));
                $expiryYear = trim($request->request->get('expiryYear'));
                $cvv = trim($request->request->get('cvv'));
            }

            // create order entry
            $order = OrderHelper::createByPackageId($user->id, $userLevelPricingId, $fileId);
            if ($order) {
                // user action logs
                UserActionLogHelper::log('User upgrade via '.$gatewayParams['label'].' requested', 'PAYMENT', 'REQUEST', [
                    'plugin' => 'payment',
                    'user_id' => $this->getAuth()->id,
                    'data' => UserActionLogHelper::getNewDataFromObject($order),
                ]);

                // prep gateway params
                $gatewayPost = [];
                $gatewayPost['amount'] = $order->amount;
                $gatewayPost['currency'] = SITE_CONFIG_COST_CURRENCY_CODE;
                $gatewayPost['returnUrl'] = WEB_ROOT . '/payment_complete';
                $gatewayPost['cancelUrl'] = WEB_ROOT . '/upgrade';

                // pickup form data for onsite orders
                if ($gatewayParams['gateway_type'] === 'onsite') {
                    $formData = [
                        'number' => $cardNumber,
                        'firstName' => $cardHolderName,
                        'expiryMonth' => $expiryMonth,
                        'expiryYear' => $expiryYear,
                        'cvv' => $cvv,
                    ];
                    $gatewayPost['card'] = $formData;
                }
                else {
                    $additionalParams = [];
                    if (strlen($gatewayParams['gateway_additional_params'])) {
                        $additionalParams = json_decode($gatewayParams['gateway_additional_params'], true);
                    }

                    if (isset($additionalParams['description'])) {
                        $gatewayPost['description'] = $order->description;
                    }

                    if (isset($additionalParams['issuer'])) {
                        $gatewayPost['issuer'] = SITE_CONFIG_SITE_NAME;
                    }
                }

                // send purchase request
                try {
                    $response = $gateway->purchase($gatewayPost)->send();

                    // handle redirects, mainly for offsite payments
                    if ($response->isRedirect()) {
                        // redirect to offsite payment gateway
                        return $this->redirect($response->getRedirectUrl());
                    }
                }
                catch (\Exception $e) {
                    // internal error, log exception and display a generic message to the customer
                    NotificationHelper::setError('Error! ' . $e->getMessage());
                }

                // if no errors
                if (NotificationHelper::isErrors() === false) {
                    // process response
                    if ($response->isSuccessful()) {
                        // payment was successful, upgrade account
                        $extendedDays = $order->days;
                        $upgradeUserId = $order->upgrade_user_id;

                        // insert payment log
                        $paymentLog = PaymentLog::create();
                        $paymentLog->user_id = $order->user_id;
                        $paymentLog->date_created = date("Y-m-d H:i:s", time());
                        $paymentLog->amount = $order->amount;
                        $paymentLog->currency_code = SITE_CONFIG_COST_CURRENCY_CODE;
                        $paymentLog->from_email = $user->email;
                        $paymentLog->to_email = $response->getTransactionReference();
                        $paymentLog->description = $order->description;
                        $paymentLog->request_log = print_r($response, true);
                        $paymentLog->payment_method = $gatewayParams['class_name'];
                        $paymentLog->save();

                        // update order status to paid
                        $order->order_status = 'completed';
                        if ($order->save() === false) {
                            // failed to update order
                            LogHelper::info('Failed - failed to update order');

                            return $this->renderEmpty200Response();
                        }

                        // extend/upgrade user
                        $rs = UserHelper::upgradeUserByPackageId($order->user_id, $order);
                        if ($rs === false) {
                            // failed to update user
                            LogHelper::info('Failed - failed to update user');

                            return $this->renderEmpty200Response();
                        }
                        else {
                            // append any plugin includes
                            PluginHelper::callHook('postUpgradePaymentIpn', array(
                                'order' => $order,
                            ));

                            // add confirmation message within their account
                            InternalNotificationHelper::add($order->user_id, 'Thanks for your payment of ' . SITE_CONFIG_COST_CURRENCY_SYMBOL . $order->amount . '. Your premium account will expire on ' . CoreHelper::formatDate($user->paidExpiryDate));

                            // redirect to account home
                            return $this->redirect(WEB_ROOT . '/account');
                        }
                    }
                    elseif ($response->isRedirect()) {
                        // redirect to offsite payment gateway
                        return $this->redirect($response->getRedirectUrl());
                    }
                    else {
                        // payment failed
                        NotificationHelper::setError('Error! ' . $response->getMessage());
                    }
                }
            }
        }

        // load template
        return $this->render('upgrade_confirmation.html', array(
                    'i' => $request->query->has('i') ? $request->query->get('i') : '',
                    'f' => $request->query->has('f') ? $request->query->get('f') : '',
                    'pageTitlePrepend' => $pageTitlePrepend,
                    'cid' => $request->query->get('cid'),
                    'monthDropdown' => $monthDropdown,
                    'yearDropdown' => $yearDropdown,
                    'user' => $user,
                    'userLevelPricingId' => $userLevelPricingId,
                    'fileId' => $fileId,
                    'cardNumber' => $cardNumber,
                    'cardHolderName' => $cardHolderName,
                    'expiryMonth' => $expiryMonth,
                    'expiryYear' => $expiryYear,
                    'cvv' => $cvv,
                    'Auth' => $Auth,
                    'gatewayType' => $gatewayParams['gateway_type'],
                    'accountType' => $accountType,
                    'accountExpiry' => $accountExpiry,
                    'accountTypeLabel' => TranslateHelper::t('account_type_' . str_replace(' ', '_', $Auth->level), ucwords($Auth->level)),
                        ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

    /**
     * Currently unused, kept for future purposes
     * 
     * @param type $params
     * @return type
     */
    public function pay($params = array()) {
        // pickup request for later
        $request = $this->getRequest();
        $db = Database::getDatabase();

        // load plugin details
        $pluginConfig = PluginHelper::pluginSpecificConfiguration('payment');
        if (!$request->request->has('user_level_pricing_id')) {
            return $this->redirect(WEB_ROOT);
        }

        if (!$request->request->has('cid')) {
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

        // load the payment gateway data
        $gatewayParams = $db->getRow('SELECT plugin_payment_gateways_configured.params, '
                . 'plugin_payment_gateways.class_name '
                . 'FROM plugin_payment_gateways_configured '
                . 'LEFT JOIN plugin_payment_gateways ON plugin_payment_gateways_configured.gateway_id = plugin_payment_gateways.id '
                . 'WHERE plugin_payment_gateways_configured.id = :id '
                . 'LIMIT 1', array(
            'id' => (int) $request->request->get('cid'),
        ));
        if (!$gatewayParams) {
            return $this->redirect(WEB_ROOT);
        }

        // create order entry
        $order = OrderHelper::createByPackageId($userId, $userLevelPricingId, $fileId);
        if ($order) {
            // prep params
            $gatewayParamsArr = json_decode($gatewayParams['params'], true);

            // setup the payment gateway object
            $gateway = Omnipay::create($gatewayParams['class_name']);
            foreach ($gatewayParamsArr AS $k => $gatewayParam) {
                $funcName = 'set' . ucfirst($k);
                if (method_exists($gateway, $funcName)) {
                    $gateway->{$funcName}($gatewayParam);
                }
            }

            // Example form data
            $formData = [
                'number' => '4242424242424242',
                'expiryMonth' => '6',
                'expiryYear' => '2019',
                'cvv' => '123'
            ];

            // send purchase request
            try {
                $response = $gateway->purchase(
                                [
                                    'amount' => $order->amount,
                                    'currency' => SITE_CONFIG_COST_CURRENCY_CODE,
                                    'card' => $formData
                                ]
                        )->send();
            }
            catch (\Exception $e) {
                // internal error, log exception and display a generic message to the customer
                return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($e->getMessage()));
            }

            // Process response
            if ($response->isSuccessful()) {
                // Payment was successful
                print_r($response);
            }
            elseif ($response->isRedirect()) {
                // Redirect to offsite payment gateway
                return $response->redirect();
            }
            else {
                // Payment failed
                return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode($response->getMessage()));
            }
        }

        // fallback
        return $this->redirect(CoreHelper::getCoreSitePath() . "/error?e=" . urlencode('Failed creating order, please try again later.'));
    }

}
