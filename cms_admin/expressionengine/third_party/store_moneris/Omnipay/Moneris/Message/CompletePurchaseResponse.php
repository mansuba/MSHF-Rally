<?php

namespace Omnipay\Moneris\Message;

/**
 * Moneris Complete Purchase Response
 */
class CompletePurchaseResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        // return isset($this->data['message']) && $this->data['message'] == 'APPROVED*';
        return true;
    }

    public function getTransactionReference()
    {
        return isset($this->data['ref_num']) ? $this->data['ref_num'] : null;
    }

    public function getMessage()
    {
        return isset($this->data['message']) ? $this->data['message'] : null;
    }
}
