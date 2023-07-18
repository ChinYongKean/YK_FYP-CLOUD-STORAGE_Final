<?php

namespace Omnipay\EgopayRu\Message;

use Mockery;
use Omnipay\Tests\TestCase;
use stdClass;

class SoapResponseTest extends TestCase
{
    /**
     * Test SoapResponse constructor
     */
    public function testConstruct()
    {
        $request = Mockery::mock('\\Omnipay\\EgopayRu\\Message\\SoapAbstractRequest');

        $std = new stdClass;
        $std->retval = array();
        
        $response = new SoapResponse($request, $std);
        
        $this->assertTrue(is_array($response->getData()));

        $response = new SoapResponse($request, 'hello world');

        $this->assertTrue(is_string($response->getData()));
    }
}
