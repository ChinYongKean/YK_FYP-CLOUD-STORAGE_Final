<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\EgopayRu\GatewayMockValues;

class RejectResponseTest extends AbstractResponseTest
{
    /**
     * Response class name
     *
     * @return string
     */
    public function getResponseClassName()
    {
        return 'RejectResponse';
    }

    /**
     * Response data returned when response succeeded
     *
     * @return array
     */
    public function getSuccessResponseData()
    {
        return GatewayMockValues::getRejectSuccess($this->shopId, $this->orderId);
    }

    /**
     * Response data returned when response failed
     *
     * @return array
     */
    public function getFailResponseData()
    {
        return GatewayMockValues::getRejectFail();
    }
}
