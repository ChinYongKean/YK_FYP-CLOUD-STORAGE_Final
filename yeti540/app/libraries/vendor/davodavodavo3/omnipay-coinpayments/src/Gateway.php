<?php

namespace Omnipay\CoinPayments;

use Omnipay\Common\AbstractGateway;

/**
 * Gateway Class
 */
class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'CoinPayments';
    }

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

    public function getDefaultParameters()
    {
        return [
            'merchant_id' => '',
            'private_key' 	=> '',
            'public_key' => '',
            'ipn_secret' 	=> ''
        ];
    }

    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\CoinPayments\Message\PurchaseRequest', $parameters);
    }

    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\CoinPayments\Message\CompletePurchaseRequest', $parameters);
    }

    public function refund(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\CoinPayments\Message\RefundRequest', $parameters);
    }
}
