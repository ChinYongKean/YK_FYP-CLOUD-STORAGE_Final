<?php

namespace Omnipay\CoinPayments\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;

class APIPurchaseResponse extends APIResponse implements RedirectResponseInterface
{
    /* protected $redirectUrl;

    public function __construct(RequestInterface $request, $data, $redirectUrl)
    {
        parent::__construct($request, $data);
        $this->redirectUrl = $redirectUrl;
    } */

    

    public function isRedirect()
    {
        return false;
    }

    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    public function getRedirectMethod()
    {
        return 'POST';
    }

    public function getRedirectData()
    {
        return $this->data;
    }
}
