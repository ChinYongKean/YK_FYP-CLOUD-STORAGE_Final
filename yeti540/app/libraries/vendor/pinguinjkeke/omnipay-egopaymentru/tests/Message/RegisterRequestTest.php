<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\Common\Exception\RuntimeException;

class RegisterRequestTest extends AbstractRequestTest
{
    /**
     * Customer parameters
     *
     * @var array
     */
    protected $customer;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @throws \Omnipay\Common\Exception\RuntimeException
     */
    public function setUp()
    {
        $this->customer = array(
            'id' => mt_rand(1, 100),
            'name' => 'Vasya Pupkin',
            'email' => 'a@b.ru',
            'phone' => '1234567890'
        );

        parent::setUp();
    }
    /**
     * Request class name
     *
     * @return string
     */
    protected function getRequestClassName()
    {
        return 'RegisterRequest';
    }

    /**
     * Response class name
     *
     * @return string
     */
    protected function getResponseClassName()
    {
        return 'RegisterResponse';
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
            'url_ok' => '/payment/ok',
            'url_fault' => '/payment/fail',
            'language' => 'ru',
            'paytype' => 'card',
            'customer_id' => $this->customer['id'],
            'customer_name' => $this->customer['name'],
            'customer_email' => $this->customer['email'],
            'customer_phone' => $this->customer['phone'],
            'timelimit' => 10
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
        $this->assertEquals($data['postdata'], array(
            array('name' => 'Language', 'value' => 'ru'),
            array('name' => 'ReturnURLOk', 'value' => '/payment/ok'),
            array('name' => 'ReturnURLFault', 'value' => '/payment/fail'),
            array('name' => 'ChoosenCardType', 'value' => 'VI')
        ));
        $this->assertEquals($data['description'], array(
            'timelimit' => 10,
            'paytype' => 'card'
        ));
        $this->assertEquals($data['customer'], $this->customer);
    }

    /**
     * Test ability to add items to request
     */
    public function testItems()
    {
        $item = array(
            'typename' => 'good',
            'number' => mt_rand(1, 100),
            'amount' => array('amount' => '10.00', 'currency' => 'RUB'),
            'descr' => 'An item',
            'host' => ''
        );

        $this->request->addItem($item);
        
        $contractItem = $this->getMockBuilder('\\Omnipay\\EgopayRu\\Contracts\\OrderItemContract')
            ->setMethods(array(
                'getOrderItemTypeName',
                'getOrderItemNumber',
                'getOrderItemCost',
                'getOrderItemDescription',
                'getOrderItemHost'
            ))->getMock();

        $this->request->addItem($contractItem);
        
        $data = $this->request->getData();

        $this->assertEquals($data['description']['items'], array($item, array(
            'typename' => $contractItem->getOrderItemTypeName(),
            'number' => $contractItem->getOrderItemNumber(),
            'amount' => array(
                'amount' => $contractItem->getOrderItemCost(),
                'currency' => $this->request->getCurrency()
            ),
            'descr' => $contractItem->getOrderItemDescription(),
            'host' => $contractItem->getOrderItemHost()
        )));

        try {
            $this->request->addItem('wrong data');
        } catch (RuntimeException $e) {
            $this->assertEquals($e->getMessage(), 'Item must be a type of array or implement the OrderItemContract');
        }
    }

    public function testSetCustomer()
    {
        list($id, $name, $email, $phone) = array(1, 'Vasya Pupkin', 'a@b.ru', '123456');
        $customer = compact('id', 'name', 'email', 'phone');

        $this->request->setCustomer($customer);

        $this->assertEquals($this->request->getCustomerId(), $id);
        $this->assertEquals($this->request->getCustomerName(), $name);
        $this->assertEquals($this->request->getCustomerEmail(), $email);
        $this->assertEquals($this->request->getCustomerPhone(), $phone);

        $contractCustomer = $this->getMockBuilder('\\Omnipay\\EgopayRu\\Contracts\\CustomerContract')
            ->setMethods(array(
                'getCustomerId',
                'getCustomerName',
                'getCustomerEmail',
                'getCustomerPhone'
            ))->getMock();

        $this->request->setCustomer($contractCustomer);

        $this->assertEquals($this->request->getCustomerId(), $contractCustomer->getCustomerId());
        $this->assertEquals($this->request->getCustomerName(), $contractCustomer->getCustomerName());
        $this->assertEquals($this->request->getCustomerEmail(), $contractCustomer->getCustomerEmail());
        $this->assertEquals($this->request->getCustomerPhone(), $contractCustomer->getCustomerPhone());

        try {
            $this->request->setCustomer('wrong data');
        } catch (RuntimeException $e) {
            $this->assertEquals($e->getMessage(), 'Customer must be a type of array or implement CustomerContract');
        }
    }

    /**
     * Test setCurrency mode
     */
    public function testSetCurrency()
    {
        $this->request->setCurrency('EUR');

        $this->assertEquals($this->request->getCurrency(), 'EUR');

        $currency = 'BITCOIN';
        $currencies = array('RUB', 'EUR', 'USD');

        try {
            $this->request->setCurrency($currency);
        } catch (RuntimeException $e) {
            $this->assertEquals(
                $e->getMessage(),
                'Currency must be one of [' . implode(',', $currencies) . "], but {$currency} given."
            );
        }
    }

    /**
     * Test setLanguage method
     */
    public function testSetLanguage()
    {
        $this->request->setLanguage('en');

        $this->assertEquals($this->request->getLanguage(), 'en');

        $language = 'jp';
        $languages = array('ru', 'en', 'de', 'cn');

        try {
            $this->request->setLanguage($language);
        } catch (RuntimeException $e) {
            $this->assertEquals(
                $e->getMessage(),
                'Language must be one of ' . implode(', ', $languages) . ", but {$language} given"
            );
        }
    }

    /**
     * Test setRegisterMode method
     */
    public function testSetRegisterMode()
    {
        $this->request->setRegisterMode('online');
        
        $this->assertSame($this->request->getRegisterMode(), 'online');

        $mode = 'some_non_existent_register_mode';

        try {
            $this->request->setRegisterMode($mode);
        } catch (RuntimeException $e) {
            $this->assertSame($e->getMessage(), "No \"{$mode}\" payment mode exists!");
        }
    }
}
