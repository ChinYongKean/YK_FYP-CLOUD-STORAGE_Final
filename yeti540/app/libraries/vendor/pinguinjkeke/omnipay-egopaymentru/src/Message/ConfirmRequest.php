<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\Common\Message\ResponseInterface;
use SoapClient;

class ConfirmRequest extends SoapAbstractRequest
{
    /**
     * Get unique operation identifier from application
     * 
     * @return mixed
     */
    public function getTxnId()
    {
        return $this->getParameter('txn_id');
    }

    /**
     * Set unique operation identifier from application
     *
     * @param $txnId
     * @return \Omnipay\Common\Message\AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setTxnId($txnId)
    {
        return $this->setParameter('txn_id', $txnId);
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
        $this->validate('shop_id', 'order_id', 'user', 'password', 'amount', 'currency', 'txn_id');
        
        return array(
            'order' => array(
                'shop_id' => $this->getShopId(),
                'number' => $this->getOrderId()
            ),
            'cost' => array(
                'amount' => $this->getAmount(),
                'currency' => $this->getCurrency()
            ),
            'txn_id' => $this->getTxnId()
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
        $this->response = new ConfirmResponse($this, parent::sendData($data));
        
        return $this->response;
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
        return $soapClient->confirm($data);
    }
}
