<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\Common\Message\ResponseInterface;
use SoapClient;

class RejectRequest extends SoapAbstractRequest
{
    /**
     * Get payment id
     *
     * @return string
     */
    public function getPaymentId()
    {
        return $this->getParameter('payment_id');
    }

    /**
     * Set payment id
     *
     * @param string $paymentId
     * @return \Omnipay\Common\Message\AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setPaymentId($paymentId)
    {
        return $this->setParameter('payment_id', $paymentId);
    }
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
        return $soapClient->reject($data);
    }

    /**
     * Get the raw data array for this message. The format of this varies from gateway to
     * gateway, but will usually be either an associative array, or a SimpleXMLElement.
     *
     * @return mixed
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    public function getData()
    {
        $this->validate('shop_id', 'order_id', 'user', 'password', 'payment_id');
        
        return array(
            'order' => array('shop_id' => $this->getShopId(), 'number' => $this->getOrderId()),
            'payment_id' => $this->getPaymentId()
        );
    }

    /**
     * Send the request with specified data
     *
     * @param  mixed $data The data to send
     * @return ResponseInterface
     */
    public function sendData($data)
    {
        $this->response = new RejectResponse($this, parent::sendData($data));
        
        return $this->response;
    }
}
