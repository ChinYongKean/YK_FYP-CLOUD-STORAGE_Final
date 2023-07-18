<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\EgopayRu\GatewayMockValues;

class RegisterResponseTest extends AbstractResponseTest
{
    /**
     * Response class name
     *
     * @return string
     */
    public function getResponseClassName()
    {
        return 'RegisterResponse';
    }

    /**
     * Response data returned when response succeeded
     *
     * @return array
     */
    public function getSuccessResponseData()
    {
        return GatewayMockValues::getRegisterSuccess('https://sandbox.egopay.ru/payments/request', '123456');
    }

    /**
     * Response data returned when response failed
     *
     * @return array
     */
    public function getFailResponseData()
    {
        return GatewayMockValues::getRegisterFail();
    }

    /**
     * Test success response
     */
    public function testSuccess()
    {
        $this->assertTrue($this->responseSuccess->isSuccessful());
        $this->assertTrue($this->responseSuccess->isRedirect());
        $this->assertFalse($this->responseSuccess->getRedirectData());
        $this->assertEquals(
            $this->responseSuccess->getRedirectUrl(),
            'https://sandbox.egopay.ru/payments/request?session=123456'
        );
        $this->assertSame($this->responseSuccess->getRedirectMethod(), 'GET');
    }
}
