<?php

namespace Omnipay\EgopayRu\Message;

class GetByOrderResponse extends SoapResponse
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
}
