<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\Tests\TestCase;

class SoapAbstractRequestTest extends TestCase
{
    public function testBuildSoapClientFault()
    {
        $request = new CancelRequest($this->getHttpClient(), $this->getHttpRequest(), null);
        $request->setEndpoint(null);

        $this->assertNull($request->buildSoapClient());
        $this->assertEquals($request->sendData(array())->getData(), 'SOAP fail');
    }
}
