<?php

namespace Omnipay\EgopayRu\Message;

class CancelRequestTest extends AbstractRequestTest
{
    /**
     * Request class name
     *
     * @return string
     */
    protected function getRequestClassName()
    {
        return 'CancelRequest';
    }

    /**
     * Response class name
     *
     * @return string
     */
    protected function getResponseClassName()
    {
        return 'CancelResponse';
    }

    /**
     * Request parameters
     *
     * @return array
     */
    protected function getRequestParameters()
    {
        return array(
            'user' => $this->user,
            'password' => $this->password,
            'shop_id' => $this->shopId,
            'order_id' => $this->orderId
        );
    }

    /**
     * Test data array (getData)
     */
    public function testData()
    {
        $data = $this->request->getData();
        
        $this->assertSame($this->shopId, $data['order']['shop_id']);
        $this->assertSame($this->orderId, $data['order']['number']);
    }
}
