<?php

namespace Omnipay\CoinPayments\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * Coinbase Response
 */
class APIResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return isset($this->data['result']) && 'ok' === $this->data['error'];
    }

    public function getMessage()
    {
        if (isset($this->data['error']) && $this->data['error'] !== 'ok') {
            return $this->data['error'];
        } 
    }

    public function getTransactionReference()
    {
        if (isset($this->data['result'])) {
			
			foreach($this->data['result'] as $key => $value){
				
			}
			
            return $this->data['result'];
        }
    }
}