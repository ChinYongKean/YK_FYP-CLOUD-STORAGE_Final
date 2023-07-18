<?php

namespace Omnipay\EgopayRu\Message;

class ConfirmRequestTest extends AbstractRequestTest
{
    /**
     * Request class name
     *
     * @return string
     */
    protected function getRequestClassName()
    {
        return 'ConfirmRequest';
    }

    /**
     * Response class name
     *
     * @return string
     */
    protected function getResponseClassName()
    {
        return 'ConfirmResponse';
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
            'amount' => '10.00',
            'currency' => 'RUB',
            'txn_id' => 'confirm1_123456'
        );
    }

    /**
     * Test data array (getData)
     */
    public function testData()
    {
        $data = $this->request->getData();

        $this->assertEquals($data['order'], array('shop_id' => $this->shopId, 'number' => $this->orderId));
        $this->assertEquals($data['cost'], array('amount' => '10.00', 'currency' => 'RUB'));
        $this->assertEquals($data['txn_id'], 'confirm1_123456');
    }
}
