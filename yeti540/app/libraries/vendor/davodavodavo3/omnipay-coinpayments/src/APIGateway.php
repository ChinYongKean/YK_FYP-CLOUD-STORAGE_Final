<?php

namespace Omnipay\CoinPayments;

use Omnipay\Common\AbstractGateway;

/**
 * Gateway Class
 */
class APIGateway extends AbstractGateway
{
    public function getName()
    {
        return 'CoinPaymentsApi';
    }

    public function getDefaultParameters()
    {
        return array(
            'merchant_id' => '',
            'private_key' 	=> '',
            'public_key' => '',
            'ipn_secret' 	=> ''
        );
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

    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\CoinPayments\Message\APIPurchaseRequest', $parameters);
    }

    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\CoinPayments\Message\APICompletePurchaseRequest', $parameters);
    }

    public function refund(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\CoinPayments\Message\APIRefundRequest', $parameters);
    }
}
