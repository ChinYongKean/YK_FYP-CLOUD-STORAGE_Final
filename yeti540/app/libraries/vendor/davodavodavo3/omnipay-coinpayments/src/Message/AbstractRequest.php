<?php

namespace Omnipay\CoinPayments\Message;

use Omnipay\Common\Message\AbstractRequest as OmnipayRequest;

abstract class AbstractRequest extends OmnipayRequest
{
    protected $liveMerchantEndpoint = 'https://www.coinpayments.net/index.php';
    protected $liveApiEndpoint = 'https://www.coinpayments.net/api.php';


    protected function getMerchantEndpoint()
    {
        return $this->liveMerchantEndpoint;
    }

	protected function getApiEndpoint()
    {
        return $this->liveApiEndpoint;
    }

    protected function getIpnSecret()
    {
        return $this->getParameter('ipn_secret');
    }

    protected function setIpnSecret($value)
    {
        return $this->setParameter('ipn_secret', $value);
    }


	public function sendRequest($method, $data = null)
    {

        $url = $this->getApiEndpoint();
        $body = $data ? http_build_query($data, '', '&') : null;
        $hmac = hash_hmac('sha512', $body, $data['private_key']);

        $httpRequest = $this->httpClient->createRequest($method, $url, null, $body);
        $httpRequest->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $httpRequest->setHeader('HMAC', $hmac);

        return $httpRequest->send();
    }
}
