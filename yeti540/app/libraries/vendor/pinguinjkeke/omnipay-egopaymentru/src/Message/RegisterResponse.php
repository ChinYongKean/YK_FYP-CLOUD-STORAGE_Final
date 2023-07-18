<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

class RegisterResponse extends SoapResponse implements RedirectResponseInterface
{
    /**
     * Is the response successful?
     * In most cases if response is an array then it's successful
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        return is_array($this->data);
    }

    /**
     * Does the response require a redirect?
     *
     * @return boolean
     */
    public function isRedirect()
    {
        return $this->isSuccessful();
    }

    /**
     * Gets the redirect target url.
     * 
     * @return string
     */
    public function getRedirectUrl()
    {
        return "{$this->data['redirect_url']}?session={$this->data['session']}";
    }

    /**
     * Gateway Reference
     *
     * @return string A reference provided by the gateway to represent this transaction
     */
    public function getTransactionReference()
    {
        return $this->data['session'];
    }

    /**
     * Get the required redirect method (either GET or POST).
     * 
     * @return string
     */
    public function getRedirectMethod()
    {
        return 'GET';
    }

    /**
     * Gets the redirect form data array, if the redirect method is POST.
     * 
     * @return array|bool
     */
    public function getRedirectData()
    {
        return false;
    }
}
