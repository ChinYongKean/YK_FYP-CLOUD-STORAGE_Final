<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Helpers\CoreHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LegacyController extends BaseController
{
    public function index() {
        return new RedirectResponse(CoreHelper::getCoreSitePath());
    }
    
    public function accountIndex() {
        return new RedirectResponse(CoreHelper::getCoreSitePath() . '/account');
    }
    
    public function accountEdit() {
        return new RedirectResponse(CoreHelper::getCoreSitePath() . '/account/edit');
    }
}
