<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\Common\Message\ResponseInterface;
use SoapClient;

/**
 * Order status from gateway v4 instead of v2 because of improved API
 * with card tokens, etc
 */
class GetByOrderRequest extends SoapAbstractRequest
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
        return $soapClient->get_by_order($data);
    }

    /**
     * Send the request with specified data
     *
     * @param  mixed $data The data to send
     * @return ResponseInterface
     */
    public function sendData($data)
    {
        $this->response = new GetByOrderResponse($this, parent::sendData($data));

        return $this->response;
    }
}
