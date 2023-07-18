<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\Common\Message\ResponseInterface;
use SoapClient;

class CancelRequest extends SoapAbstractRequest
{
    /**
     * Runs SOAP request
     *
     * @param SoapClient $soapClient
     * @param $data
     * @return mixed
     */
    protected function runTransaction(SoapClient $soapClient, $data)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $soapClient->cancel($data);
    }

    /**
     * Send the request with specified data
     *
     * @param  mixed $data The data to send
     * @return ResponseInterface
     */
    public function sendData($data)
    {
        $this->response = new CancelResponse($this, parent::sendData($data));
        
        return $this->response;
    }
}
