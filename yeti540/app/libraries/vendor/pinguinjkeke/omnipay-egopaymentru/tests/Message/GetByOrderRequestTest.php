<?php

namespace Omnipay\EgopayRu\Message;

class GetByOrderRequestTest extends AbstractRequestTest
{
    /**
     * Request class name
     *
     * @return string
     */
    protected function getRequestClassName()
    {
        return 'GetByOrderRequest';
    }
    
    /**
     * Response class name
     *
     * @return string
     */
    protected function getResponseClassName()
    {
        return 'GetByOrderResponse';
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
            'password' => $this->password
        );
    }

    /**
     * Test data array (getData)
     */
    public function testData()
    {
        $data = $this->request->getData();

        $this->assertEquals($data['order'], array(
            'shop_id' => $this->shopId,
            'number' => $this->orderId
        ));
    }
}
