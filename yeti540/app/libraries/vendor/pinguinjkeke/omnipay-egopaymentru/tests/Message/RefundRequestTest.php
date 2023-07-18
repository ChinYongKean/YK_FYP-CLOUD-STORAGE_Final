<?php

namespace Omnipay\EgopayRu\Message;

use Mockery;
use Omnipay\Common\Exception\RuntimeException;

class RefundRequestTest extends AbstractRequestTest
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
        $this->paymentId = (string) mt_rand(1000000, 9000000);
        
        parent::setUp();
    }

    /**
     * Request class name
     *
     * @return string
     */
    protected function getRequestClassName()
    {
        return 'RefundRequest';
    }

    /**
     * Response class name
     *
     * @return string
     */
    protected function getResponseClassName()
    {
        return 'RefundResponse';
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
            'payment_id' => $this->paymentId,
            'refund_id' => "refund1_{$this->paymentId}",
            'amount' => '10.00',
            'currency' => 'RUB'
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
        $this->assertEquals($data['cost'], array(
            'amount' => '10.00',
            'currency' => 'RUB'
        ));
        $this->assertEquals($data['payment_id'], $this->paymentId);
        $this->assertEquals($data['refund_id'], "refund1_{$this->paymentId}");
    }

    /**
     * Test ability to add items to request
     */
    public function testItems()
    {
        // Test add array
        $item = array(
            'id' => mt_rand(1, 100),
            'amount' => array('amount' => '10.00', 'currency' => 'RUB')
        );
        
        $this->request->addItem($item);

        // Test add contract implemented object
        $contractItem = $this->getMockBuilder('\\Omnipay\\EgopayRu \\Contracts\\OrderItemContract')
            ->setMethods(array(
                'getOrderItemTypeName',
                'getOrderItemNumber',
                'getOrderItemCost',
                'getOrderItemDescription',
                'getOrderItemHost'
            ))->getMock();

        $this->request->addItem($contractItem);

        $data = $this->request->getData();

        $this->assertEquals($data['items'], array($item, array(
            'id' => $contractItem->getOrderItemNumber(),
            'amount' => array(
                'amount' => $contractItem->getOrderItemCost(),
                'currency' => $this->request->getCurrency()
            )
        )));

        try {
            $this->request->addItem('wrong data');
        } catch (RuntimeException $e) {
            $this->assertEquals($e->getMessage(), 'Item must be an array or implement OrderItemContract interface');
        }
    }
}
