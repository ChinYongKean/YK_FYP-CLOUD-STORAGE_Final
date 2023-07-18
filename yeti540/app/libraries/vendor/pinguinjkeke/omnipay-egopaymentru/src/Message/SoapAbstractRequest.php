<?php

namespace Omnipay\EgopayRu\Message;

use Guzzle\Http\ClientInterface;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\ResponseInterface;
use SoapClient;
use SoapFault;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

abstract class SoapAbstractRequest extends AbstractRequest
{
    /**
     * SPL Soap Client
     *
     * @var SoapClient
     */
    protected $soapClient;

    /**
     * Request
     *
     * @var string
     */
    protected $request;

    /**
     * SOAP timeout
     *
     * @var int
     */
    public $timeout = 12;

    /**
     * Create a new Request (SOAP client added to class constructor)
     *
     * @param ClientInterface $httpClient A Guzzle client to make API calls with
     * @param HttpRequest $httpRequest A Symfony HTTP request object
     * @param SoapClient $soapClient
     */
    public function __construct(
        ClientInterface $httpClient,
        HttpRequest $httpRequest,
        SoapClient $soapClient = null
    ) {
        parent::__construct($httpClient, $httpRequest);
        $this->soapClient = $soapClient;
    }

    /**
     * Get payment endpoint
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->getParameter('endpoint');
    }

    /**
     * Set payment endpoint
     *
     * @param string $endpoint
     * @return AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setEndpoint($endpoint)
    {
        return $this->setParameter('endpoint', $endpoint);
    }

    /**
     * Get WSDL path
     *
     * @return string
     */
    public function getWsdl()
    {
        return $this->getParameter('wsdl');
    }

    /**
     * Set WSDL path
     *
     * @param string $wsdl
     * @return AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setWsdl($wsdl)
    {
        return $this->setParameter('wsdl', $wsdl);
    }

    /**
     * Get shop id you received by Egopayment
     *
     * @return int
     */
    public function getShopId()
    {
        return $this->getParameter('shop_id');
    }

    /**
     * Set shop id you received by Egopayment
     *
     * @param $shopId
     * @return AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setShopId($shopId)
    {
        return $this->setParameter('shop_id', $shopId);
    }

    /**
     * Get user you received by Egopayment
     *
     * @return string
     */
    public function getUser()
    {
        return $this->getParameter('user');
    }

    /**
     * Set user you received by Egopayment
     *
     * @param string $user
     * @return AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setUser($user)
    {
        return $this->setParameter('user', $user);
    }

    /**
     * Get password you received by Egopayment
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->getParameter('password');
    }

    /**
     * Set password you received by Egopayment
     *
     * @param string $password
     * @return AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setPassword($password)
    {
        return $this->setParameter('password', $password);
    }

    /**
     * Get your application's order id
     *
     * @return string
     */
    public function getOrderId()
    {
        return $this->getParameter('order_id');
    }

    /**
     * Set your application's order id
     *
     * @param int $orderId
     * @return AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setOrderId($orderId)
    {
        return $this->setParameter('order_id', $orderId);
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
        $this->validate('shop_id', 'order_id', 'user', 'password');

        $this->request = array(
            'order' => array(
                'shop_id' => $this->getShopId(),
                'number' => $this->getOrderId()
            )
        );

        return $this->request;
    }

    /**
     * Creates SOAP client with gateway parameters
     *
     * @return SoapClient
     */
    public function buildSoapClient()
    {
        if ($this->soapClient !== null) {
            return $this->soapClient;
        }

        try {
            if (!$this->getEndpoint()) {
                throw new SoapFault('Client', 'No endpoint provided');
            }

            $this->soapClient = new SoapClient($this->getWsdl(), array(
                'uri' => 'http://www.sirena-travel.ru/',
                'location' => $this->getEndpoint(),
                'login' => $this->getUser(),
                'password' => $this->getPassword(),
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'encoding' => 'utf-8',
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                'exceptions' => true,
                'trace' => (int) $this->getTestMode(),
                'connection_timeout' => $this->timeout,
            ));

            return $this->soapClient;
        } catch (SoapFault $e) {
            return null;
        }
    }

    /**
     * Runs SOAP request
     *
     * @param SoapClient $soapClient
     * @param $data
     * @return mixed
     */
    abstract protected function runTransaction(SoapClient $soapClient, $data);

    /**
     * Send the request with specified data
     *
     * @param  mixed $data The data to send
     * @return ResponseInterface
     */
    public function sendData($data)
    {
        if (!$soapClient = $this->buildSoapClient()) {
            return 'SOAP fail';
        }

        try {
            return $this->runTransaction($soapClient, $data);
        } catch (SoapFault $e) {
            return $e->getMessage();
        }
    }
}
