<?php

namespace Omnipay\EgopayRu\Message;

class RejectRequestTest extends AbstractRequestTest
{
    /**
     * Payment id
     *
     * @var string
     */
    protected $paymentId;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setUp()
    {
        $this->paymentId = (string) mt_rand(10000, 100000);

        parent::setUp();
    }

    /**
     * Request class name
     *
     * @return string
     */
    protected function getRequestClassName()
    {
        return 'RejectRequest';
    }

    /**
     * Response class name
     *
     * @return string
     */
    protected function getResponseClassName()
    {
        return 'RejectResponse';
    }

    /**
     * Request parameters
     *
     * @return array
     */
    protected function getRequestParameters()
    {
        return array(
            'shop_id' => $this->shopId,
            'order_id' => $this->orderId,
            'user' => $this->user,
            'password' => $this->password,
            'payment_id' => $this->paymentId
        );
    }

    /**
     * Test data array (getData)
     */
    public function testData()
    {
        $data = $this->request->getData();

        $this->assertEquals($data['payment_id'], $this->paymentId);
        $this->assertEquals($data['order'], array(
            'shop_id' => $this->shopId,
            'number' => $this->orderId
        ));
    }
}
