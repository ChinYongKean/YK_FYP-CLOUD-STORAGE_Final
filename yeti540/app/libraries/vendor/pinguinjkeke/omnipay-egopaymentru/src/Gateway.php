<?php

namespace Omnipay\EgopayRu;

use Guzzle\Http\ClientInterface;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Exception\RuntimeException;
use Omnipay\EgopayRu\Message\CancelRequest;
use Omnipay\EgopayRu\Message\GetByOrderRequest;
use Omnipay\EgopayRu\Message\RefundRequest;
use Omnipay\EgopayRu\Message\RegisterRequest;
use Omnipay\EgopayRu\Message\RejectRequest;
use Omnipay\EgopayRu\Message\SoapAbstractRequest;
use SoapClient;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * Egopay.ru payment gateway provider
 * Supports order registration
 * 
 * Link attached was very helpful for building gateway based on SOAP
 * 
 * @link https://github.com/delatbabel/omnipay-alliedwallet/blob/master/src/Gateway.php
 */
class Gateway extends AbstractGateway
{
    /**
     * Test order endpoint address
     *
     * @var string
     */
    protected $testOrderEndpoint = 'https://tws.egopay.ru/order/v2/';

    /**
     * Live order endpoint address
     *
     * @var string
     */
    protected $liveOrderEndpoint = 'https://ws.egopay.ru/order/v2/';

    /**
     * Test status endpoint address
     *
     * @var string
     */
    protected $testStatusEndpoint = 'https://tws.egopay.ru/status/v4/';

    /**
     * Live status endpoint address
     *
     * @var string
     */
    protected $liveStatusEndpoint = 'https://ws.egopay.ru/status/v4/';

    /**
     * SoapClient
     *
     * @var SoapClient
     */
    protected $soapClient;

    /**
     * Create a new gateway instance, soapClient added to constructor
     *
     * @param ClientInterface $httpClient A Guzzle client to make API calls with
     * @param HttpRequest $httpRequest A Symfony HTTP request object
     * @param SoapClient $soapClient A SPL SoapClient
     */
    public function __construct(
        ClientInterface $httpClient = null,
        HttpRequest $httpRequest = null,
        SoapClient $soapClient = null
    ) {
        parent::__construct($httpClient, $httpRequest);
        $this->soapClient = $soapClient;
    }

    /**
     * Create and initialize a request object
     *
     * This function is usually used to create objects of type
     * Omnipay\Common\Message\AbstractRequest (or a non-abstract subclass of it)
     * and initialise them with using existing parameters from this gateway.
     *
     * @see \Omnipay\Common\Message\AbstractRequest
     * @param string $class The request class name
     * @param array $parameters
     * @return \Omnipay\Common\Message\AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    protected function createRequest($class, array $parameters)
    {
        /** @var SoapAbstractRequest $obj */
        $obj = new $class($this->httpClient, $this->httpRequest, $this->soapClient);

        return $obj->initialize(array_replace($this->getParameters(), $parameters));
    }

    public function setSoapClient(SoapClient $soapClient)
    {
        $this->soapClient = $soapClient;
    }

    /**
     * Get gateway display name
     * This can be used by carts to get the display name for each gateway.
     *
     * @return string
     */
    public function getName()
    {
        return 'Egopay';
    }

    /**
     * Define gateway parameters, in the following format:
     *
     * array(
     *     'username' => '', // string variable
     *     'testMode' => false, // boolean variable
     *     'landingPage' => array('billing', 'login'), // enum variable, first item is default
     * );
     */
    public function getDefaultParameters()
    {
        return array(
            'testMode' => false,
            'endpoint' => $this->testOrderEndpoint,
            'wsdl' => __DIR__ . '/Resource/orderv2_new.xml',
            'shop_id' => '',
            'order_id' => '',
            'user' => '',
            'password' => ''
        );
    }

    /**
     * Get WSDL file path
     *
     * @return mixed
     */
    public function getWsdl()
    {
        return $this->getParameter('wsdl');
    }

    /**
     * Set WSDL file path
     *
     * @param string $wsdl
     * @return $this
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setWsdl($wsdl)
    {
        if (!file_exists($wsdl)) {
            throw new RuntimeException("WSDL file not exists at \"{$wsdl}\"");
        }

        return $this->setParameter('wsdl', $wsdl);
    }

    /**
     * Returns endpoint address
     * 
     * @return string
     */
    public function getEndpoint()
    {
        return $this->getParameter('endpoint');
    }

    /**
     * Set endpoint address
     *
     * @param string $endpoint
     * @return $this
     */
    public function setEndpoint($endpoint)
    {
        return $this->setParameter('endpoint', $endpoint);
    }

    /**
     * Set status or order endpoint with or without test mode
     *
     * @param bool $statusEndpoint Status or order endpoint
     * @return $this
     */
    public function chooseEndpoint($statusEndpoint = false)
    {
        if ($statusEndpoint) {
            $endpoint = $this->getTestMode() ? $this->testStatusEndpoint : $this->liveStatusEndpoint;
        } else {
            $endpoint = $this->getTestMode() ? $this->testOrderEndpoint : $this->liveOrderEndpoint;
        }

        return $this->setParameter('endpoint', $endpoint);
    }

    /**
     * Get shop id you received from Egopay
     *
     * @return int
     */
    public function getShopId()
    {
        return $this->getParameter('shop_id');
    }

    /**
     * Set shop id you received from Egopay
     *
     * @param int $shopId
     * @return $this
     */
    public function setShopId($shopId)
    {
        return $this->setParameter('shop_id', $shopId);
    }

    /**
     * Get current order number inside your application
     *
     * @return int
     */
    public function getOrderId()
    {
        return $this->getParameter('order_id');
    }

    /**
     * Get current order number inside your application
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId($orderId)
    {
        return $this->setParameter('order_id', $orderId);
    }

    /**
     * Get username you received from Egopay
     *
     * @return string
     */
    public function getUser()
    {
        return $this->getParameter('user');
    }

    /**
     * Set username you received from Egopay
     *
     * @param string $user
     * @return $this
     */
    public function setUser($user)
    {
        return $this->setParameter('user', $user);
    }

    /**
     * Get password you received from Egopay
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->getParameter('password');
    }

    /**
     * Set password you received from Egopay
     *
     * @param string $password
     * @return $this
     */
    public function setPassword($password)
    {
        return $this->setParameter('password', $password);
    }

    /**
     * Supports cancel
     * 
     * @return boolean
     */
    public function supportsCancel()
    {
        return method_exists($this, 'cancel');
    }

    /**
     * Supports confirm
     * 
     * @return boolean
     */
    public function supportsConfirm()
    {
        return method_exists($this, 'confirm');
    }

    /**
     * Supports reject
     * 
     * @return bool
     */
    public function supportsReject()
    {
        return method_exists($this, 'reject');
    }

    /**
     * Support register
     * 
     * @return boolean
     */
    public function supportsRegister()
    {
        return method_exists($this, 'register');
    }

    /**
     * Supports status
     * 
     * @return boolean
     */
    public function supportsStatus()
    {
        return method_exists($this, 'status');
    }

    /**
     * Cancel request
     *
     * @param array $parameters
     * @return CancelRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     * @link https://tws.egopay.ru/docs/v2/#p-5
     */
    public function cancel(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\EgopayRu\Message\CancelRequest', $parameters);
    }

    /**
     * In some cases you may use payments with confirmation
     * Use only if you specified this in bank contract
     *
     * @param array $parameters
     * @return \Omnipay\Common\Message\AbstractRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     * @link https://tws.egopay.ru/docs/v2/#p-5.2
     */
    public function confirm(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\EgopayRu\Message\ConfirmRequest', $parameters);
    }

    /**
     * Reject the order (works in pair with confirm)
     * Use only if you specified this in bank contract
     *
     * @param array $parameters
     * @return RejectRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     * @link https://tws.egopay.ru/docs/v2/#p-5.3
     */
    public function reject(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\EgopayRu\Message\RejectRequest', $parameters);
    }

    /**
     * Refund the order money to client
     *
     * @param array $parameters
     * @return RefundRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     * @link https://tws.egopay.ru/docs/v2/#p-5.4
     */
    public function refund(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\EgopayRu\Message\RefundRequest', $parameters);
    }

    /**
     * Your application send order registration request to gateway.
     *
     * @param array $parameters
     * @return RegisterRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     * @link https://tws.egopay.ru/docs/v2/#p-3.1
     */
    public function register(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\EgopayRu\Message\RegisterRequest', $parameters);
    }

    /**
     * Order info.
     * After order registration, your application can get order status
     *
     * @param array $parameters
     * @return GetByOrderRequest
     * @throws \Omnipay\Common\Exception\RuntimeException
     * @link https://tws.egopay.ru/docs/v41/#p-2
     */
    public function status(array $parameters = array())
    {
        $this->chooseEndpoint(true);
        $this->setWsdl(__DIR__ . '/Resource/statusv4.xml');

        return $this->createRequest('\Omnipay\EgopayRu\Message\GetByOrderRequest', $parameters);
    }
}
