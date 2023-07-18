<?php

namespace Omnipay\CoinPayments\Message;

class APIPurchaseRequest extends AbstractRequest
{
    public function getPrivateKey()
    {
        return $this->getParameter('private_key');
    }

    public function setPrivateKey($value)
    {
        return $this->setParameter('private_key', $value);
    }

    public function getPublicKey()
    {
        return $this->getParameter('public_key');
    }

    public function setPublicKey($value)
    {
        return $this->setParameter('public_key', $value);
    }

	public function getIpnSecret()
    {
        return $this->getParameter('ipn_secret');
    }

    public function setIpnSecret($value)
    {
        return $this->setParameter('ipn_secret', $value);
    }

	public function getCurrency2()
    {
        return $this->getParameter('currency2');
    }

    public function setCurrency2($value)
    {
        return $this->setParameter('currency2', $value);
    }

    public function getBuyerEmail()
    {
        return $this->getParameter('buyer_email');
    }

    public function setBuyerEmail($value)
    {
        return $this->setParameter('buyer_email', $value);
    }

    public function getBuyerName()
    {
        return $this->getParameter('buyer_name');
    }

    public function setBuyerName($value)
    {
        return $this->setParameter('buyer_name', $value);
    }

    public function getItemName()
    {
        return $this->getParameter('item_name');
    }

    public function setItemName($value)
    {
        return $this->setParameter('item_name', $value);
    }

    public function getData()
    {
        $this->validate('private_key', 'public_key', 'ipn_secret');

        $data['version'] = 1;
        $data['cmd'] = 'create_transaction';
        $data['key'] = $this->getPublicKey();
        $data['private_key'] = $this->getPrivateKey();
        $data['format'] = 'json'; //supported values are json and xml

        $data['amount'] = $this->getAmount();
	$data['currency1'] = $this->getCurrency();
	$data['currency2'] = $this->getCurrency2();

        $data['buyer_email'] = $this->getBuyerEmail();
        $data['buyer_name'] = $this->getBuyerName();
        $data['item_name'] = $this->getItemName();
        $data['invoice'] = $this->getTransactionId();
	    
        return $data;
    }

    public function sendData($data)
    {
        $httpResponse = $this->sendRequest('POST', $data);

        return $this->response = new APIPurchaseResponse($this, $httpResponse->json(), $this->getMerchantEndpoint());
    }
}
