<?php
namespace Omnipay\Mollie\Message;

/**
 * Mollie Purchase Request
 *
 * @method \Omnipay\Mollie\Message\PurchaseResponse send()
 */
class PurchaseRequest extends AbstractRequest
{

    public function getMetadata()
    {
        return $this->getParameter('metadata');
    }

    public function setMetadata($value)
    {
        return $this->setParameter('metadata', $value);
    }

    public function getData()
    {
        $this->validate('apiKey', 'amount', 'description', 'returnUrl');

        $data                = [];
        $data['amount']      = $this->getAmount();
        $data['description'] = $this->getDescription();
        $data['redirectUrl'] = $this->getReturnUrl();
        $data['method']      = $this->getPaymentMethod();
        $data['metadata']    = $this->getMetadata();
        if ($this->getTransactionId()) {
            $data['metadata']['transactionId'] = $this->getTransactionId();
        }
        $issuer = $this->getIssuer();
        if ($issuer) {
            $data['issuer'] = $issuer;
        }

        $webhookUrl = $this->getNotifyUrl();
        if (null !== $webhookUrl) {
            $data['webhookUrl'] = $webhookUrl;
        }

        return $data;
    }

    public function sendData($data)
    {
        $httpResponse = $this->sendRequest('POST', '/payments', $data);

        return $this->response = new PurchaseResponse($this, $httpResponse->json());
    }
}
