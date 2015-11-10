<?php

namespace Omnipay\Moneris\Message;

use SimpleXML;

use Omnipay\Common\Message\AbstractResponse;


/**
 * Moneris Purchase Response
 */
class PurchaseResponse extends AbstractResponse
{
	
	
    public function isSuccessful()
    {

		$xml = simplexml_load_string($this->data);
		
		$os = array("000","001","002","003","004","005","006","007","008","009","023","024","025","026","027","028","029");
		if (in_array($xml->receipt[0]->ResponseCode, $os)) {
		    return TRUE;
		} else {
			return FALSE;
		}
		
    }

	public function isRedirect()
	{
		return FALSE;
	}
	
	public function getCardDetails()
	{
		return $this->getRequest()->cardInfo();
	}

	public function getMessage()
    {
		$xml = simplexml_load_string($this->data);
	
        return (string) $xml->receipt[0]->Message;
    }

	
}