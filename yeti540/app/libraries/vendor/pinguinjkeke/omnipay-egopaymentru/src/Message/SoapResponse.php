<?php

namespace Omnipay\EgopayRu\Message;

use Omnipay\Common\Message\AbstractResponse;

class SoapResponse extends AbstractResponse
{
    /**
     * Constructor
     *
     * @param SoapAbstractRequest $request the initiating request.
     * @param mixed $data
     */
    public function __construct(SoapAbstractRequest $request, $data)
    {
        parent::__construct($request, $data);

        $this->data = is_object($data)
            ? json_decode(json_encode($data->retval), true)
            : $data;
    }

    /**
     * Is the response successful?
     * In most cases if response is an array then it's successful
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        return is_array($this->data) &&
            array_key_exists('error', $this->data) &&
            array_key_exists('code', $this->data['error']) &&
            $this->data['error']['code'] === 'ok';
    }
}
