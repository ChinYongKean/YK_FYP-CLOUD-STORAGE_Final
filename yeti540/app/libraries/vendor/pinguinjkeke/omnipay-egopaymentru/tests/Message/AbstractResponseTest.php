<?php

namespace Omnipay\EgopayRu\Message;

use Mockery;
use Omnipay\Common\Exception\RuntimeException;
use Omnipay\Tests\TestCase;

abstract class AbstractResponseTest extends TestCase
{
    /**
     * Generated shop id
     *
     * @var int
     */
    protected $shopId;

    /**
     * Generated order id
     *
     * @var int
     */
    protected $orderId;
    
    /**
     * Successful response
     *
     * @var SoapResponse
     */
    protected $responseSuccess;

    /**
     * Failed response
     *
     * @var SoapResponse
     */
    protected $responseFail;

    /**
     * Response class name
     *
     * @return string
     */
    abstract public function getResponseClassName();

    /**
     * Response data returned when response succeeded
     * 
     * @return array
     */
    abstract public function getSuccessResponseData();

    /**
     * Response data returned when response failed
     *
     * @return array
     */
    abstract public function getFailResponseData();

    /**
     * Mock SoapAbstractRequest
     *
     * @return \Mockery\MockInterface
     */
    public function getMockRequest()
    {
        return Mockery::mock('\Omnipay\EgopayRu\Message\SoapAbstractRequest');
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
        list($this->shopId, $this->orderId) = array(mt_rand(10000, 20000), mt_rand(1, 100));
        
        $responseClass = '\\Omnipay\\EgopayRu\\Message\\' . $this->getResponseClassName();

        if (!class_exists($responseClass)) {
            throw new RuntimeException("Cannot find \"{$responseClass}\" response class");
        }

        $this->responseSuccess = new $responseClass($this->getMockRequest(), $this->getSuccessResponseData());
        $this->responseFail = new $responseClass($this->getMockRequest(), $this->getFailResponseData());
    }

    /**
     * Test success response
     */
    public function testSuccess()
    {
        $this->assertTrue($this->responseSuccess->isSuccessful());
        $this->assertFalse($this->responseSuccess->isRedirect());
    }

    /**
     * Test fail response
     */
    public function testFail()
    {
        $this->assertFalse($this->responseFail->isSuccessful());
        $this->assertFalse($this->responseFail->isRedirect());
    }
}
