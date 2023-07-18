<?php

namespace Plugins\Payment\Controllers;

use App\Core\BaseController;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Models\File;
use Plugins\Payment\Controllers\PaymentController;

class HooksController extends BaseController
{

    public function adminPluginNav($params = null) {
        // output within the admin left-hand navigation
        $navigation = array(
            array('link_url' => '#', 'link_text' => 'Payment Gateways', 'link_key' => 'payment', 'icon_class' => 'fa fa-credit-card', 'children' => array(
                    array('link_url' => 'admin/plugin/payment/gateway_manage', 'link_text' => 'Manage Gateways', 'link_key' => 'gateway_manage'),
                    array('link_url' => 'admin/plugin/payment/settings', 'link_text' => 'Plugin Settings', 'link_key' => 'settings'),
                )),
        );

        // return array
        return $navigation;
    }
    
    public function upgradeBoxes($params = null) {
        // call the controller to create the upgrade box
        $paymentController = new PaymentController();
        $response = $paymentController->upgradeBox($params);
        
        // return response object
        return $response;
    }

}
