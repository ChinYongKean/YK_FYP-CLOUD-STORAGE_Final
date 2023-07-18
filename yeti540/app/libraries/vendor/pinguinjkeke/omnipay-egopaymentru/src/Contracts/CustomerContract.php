<?php

namespace Omnipay\EgopayRu\Contracts;

interface CustomerContract
{
    /**
     * Customer (user) id
     * 
     * @return int
     */
    public function getCustomerId();

    /**
     * Customer (user) name
     * 
     * @return string
     */
    public function getCustomerName();

    /**
     * Customer (user) email
     * 
     * @return string
     */
    public function getCustomerEmail();

    /**
     * Customer (user) phone
     * 
     * @return string
     */
    public function getCustomerPhone();
}
