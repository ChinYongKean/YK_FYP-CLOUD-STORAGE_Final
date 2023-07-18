<?php

namespace Plugins\Paypal\Controllers;

use App\Core\BaseController;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Models\File;
use Plugins\Paypal\Controllers\PaypalController;

class HooksController extends BaseController
{

    public function upgradeBoxes($params = null) {
        // call the controller to create the upgrade box
        $paypalController = new PaypalController();
        $response = $paypalController->upgradeBox($params);
        
        // return response object
        return $response;
    }

}
