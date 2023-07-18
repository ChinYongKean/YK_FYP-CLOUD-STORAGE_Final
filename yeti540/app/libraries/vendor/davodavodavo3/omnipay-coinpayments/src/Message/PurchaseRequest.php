<?php

namespace Omnipay\CoinPayments\Message;

class PurchaseRequest extends AbstractRequest
{
    public function getMerchantId()
    {
        return $this->getParameter('merchant_id');
    }

    public function setMerchantId($value)
    {
        return $this->setParameter('merchant_id', $value);
    }

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
        $this->validate('merchant_id', 'private_key', 'public_key', 'ipn_secret');

        $data['cmd'] = '_pay_simple';
        $data['reset'] = 1;
        $data['merchant'] = $this->getMerchantId();
        $data['currency'] = $this->getCurrency();
        $data['amountf'] = $this->getAmount();
        $data['item_name'] = $this->getItemName();
        $data['invoice'] = $this->getTransactionId();
        $data['cancel_url'] = $this->getCancelUrl();
		$data['success_url'] = $this->getReturnUrl();

        return $data;
    }

    public function sendData($data)
    {
        return $this->response = new PurchaseResponse($this, $data, $this->getMerchantEndpoint());
    }
}
