<?php

namespace Omnipay\Moneris\Message;

use DOMDocument;
use SimpleXMLElement;


use Omnipay\Common\Message\AbstractRequest;


/**
 * Moneris Purchase Request
 */
class PurchaseRequest extends AbstractRequest
{
	protected $liveEndpoint = 'https://www3.moneris.com:443/gateway2/servlet/MpgRequest';
	protected $testEndpoint = 'https://esqa.moneris.com:443/gateway2/servlet/MpgRequest';
	public $cardData = '';
	
	var $responseData;

	public function getHppId()
	{
		return $this->getParameter('hpp_id');
	}

	public function setHppId($value)
	{
		return $this->setParameter('hpp_id', $value);
	}

	public function getHppKey()
	{
		return $this->getParameter('hpp_key');
	}

	public function getOrderID()
	{
		return $this->getParameter('description');
	}

	public function setHppKey($value)
	{
		return $this->setParameter('hpp_key', $value);
	}

	public function getEndpoint()
	{
		return $this->getTestMode() ? $this->testEndpoint : $this->liveEndpoint;
	}	
	
	public function getData()
    {
		$this->validate('amount');
		$this->cardData = $this->getCard();
		/*
		<?xml version="1.0"?>
		<request>
			<store_id>store5</store_id>
			<api_token>yesguy</api_token>
			<purchase>
				<order_id>ord-090814-0:39:21</order_id>
				<cust_id>my cust id</cust_id>
				<amount>1.00</amount>
				<pan>4242424242424242</pan>
				<expdate>0812</expdate>
				<crypt_type>7</crypt_type>
				<dynamic_descriptor/>
				<cust_info>
					<email>Joe@widgets.com</email>
					<instructions>Make it fast</instructions>
					<billing>
						<first_name>Cedric</first_name>
						<last_name>Benson</last_name>
						<company_name>Chicago Bears</company_name>
						<address>334 Michigan Ave</address>
						<city>Chicago</city>
						<province>Illinois</province>
						<postal_code>M1M1M1</postal_code>
						<country>United States</country>
						<phone_number>453-989-9876</phone_number>
						<fax>453-989-9877</fax>
						<tax1>1.01</tax1>
						<tax2>1.02</tax2>
						<tax3>1.03</tax3>
						<shipping_cost>9.95</shipping_cost>
					</billing>
					<shipping>
						<first_name>Cedric</first_name>
						<last_name>Benson</last_name>
						<company_name>Chicago Bears</company_name>
						<address>334 Michigan Ave</address>
						<city>Chicago</city>
						<province>Illinois</province>
						<postal_code>M1M1M1</postal_code>
						<country>United States</country>
						<phone_number>453-989-9876</phone_number>
						<fax>453-989-9877</fax>
						<tax1>1.01</tax1>
						<tax2>1.02</tax2>
						<tax3>1.03</tax3>
						<shipping_cost>9.95</shipping_cost>
					</shipping>
					<item>
						<name>Guy Lafleur Retro Jersey</name>
						<quantity>1</quantity>
						<product_code>JRSCDA344</product_code>
						<extended_amount>129.99</extended_amount>
					</item>
					<item>
						<name>Patrick Roy Signed Koho Stick</name>
						<quantity>1</quantity>
						<product_code>JPREEA344</product_code>
						<extended_amount>59.99</extended_amount>
					</item>
				</cust_info>
			</purchase>
		</request>
		*/
		$card = $this->getCard();
		
		
		$data = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><request/>');

		$data->addChild('store_id', $this->getHppId());
		$data->addChild('api_token', $this->getHppKey());
		
        $order_no = str_replace('Order #','',$this->getOrderID());
        
		$purchase = $data->addChild('purchase');
		$purchase->addChild('order_id', 'SR-ORD'.$order_no.'-'.date("dmy-G:i:s"));
        $purchase->addChild('cust_id', $card->getBillingFirstName()." ".$card->getBillingLastName());
		$purchase->addChild('amount', $this->getAmount());
		$purchase->addChild('pan', $this->getCard()->getNumber());
		$purchase->addChild('expdate', $this->getCard()->getExpiryDate('my'));
		$purchase->addChild('crypt_type', '7');        
		$purchase->addChild('dynamic_descriptor','Sinai Rally');
		
		$cust_info = $purchase->addChild('cust_info');
		$cust_info->addChild('email',$card->getEmail());
        $cust_info->addChild('instructions', $card->getBillingFirstName()." ".$card->getBillingLastName());
		
        /* Billing Info */
        
		$billing = $cust_info->addChild('billing');
		$billing->addChild('first_name', $card->getBillingFirstName());
		$billing->addChild('last_name', $card->getBillingLastName());
		$billing->addChild('company_name', $card->getBillingCompany());
		$billing->addChild('address', trim(
            $card->getBillingAddress1()." \n".
            $card->getBillingAddress2()
        ));
		$billing->addChild('city', $card->getBillingCity());
		$billing->addChild('province', $card->getBillingState());
		$billing->addChild('postal_code', $card->getBillingPostcode());
		$billing->addChild('country', $card->getBillingCountry());
		$billing->addChild('phone_number', $card->getBillingPhone());
        $billing->addChild('fax','000-000-0000');
        $billing->addChild('tax1', '0');
        $billing->addChild('tax2', '0');
        $billing->addChild('tax3', '0');
        $billing->addChild('shipping_cost', '0');
        
        /* Shipping info */
        
        $shipping = $cust_info->addChild('shipping');
		$shipping->addChild('first_name', $card->getBillingFirstName());
		$shipping->addChild('last_name', $card->getBillingLastName());
		$shipping->addChild('company_name', $card->getBillingCompany());
		$shipping->addChild('address', trim(
            $card->getBillingAddress1()." \n".
            $card->getBillingAddress2()
        ));
		$shipping->addChild('city', $card->getBillingCity());
		$shipping->addChild('province', $card->getBillingState());
		$shipping->addChild('postal_code', $card->getBillingPostcode());
		$shipping->addChild('country', $card->getBillingCountry());
		$shipping->addChild('phone_number', $card->getBillingPhone());
        $shipping->addChild('fax','000-000-0000');
        $shipping->addChild('tax1', '0');
        $shipping->addChild('tax2', '0');
        $shipping->addChild('tax3', '0');
        $shipping->addChild('shipping_cost', '0');
		
		
		$items = $this->getItems();
        if ($items) {
            foreach ($items as $n => $item) {
				$cart = $cust_info->addChild('item');
				$cart->addChild('name', $item->getName());
                $cart->addChild('quantity', '1');
                $cart->addChild('product_code', 'Donation');
                $cart->addChild('extended_amount', $this->getAmount());
            }
        }
		
		
        return $data;
    }

    public function sendData($data)
    {
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this->getEndpoint());
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,$data->saveXML());
		curl_setopt($ch,CURLOPT_TIMEOUT,'60');
		curl_setopt($ch,CURLOPT_USERAGENT,'PHP - 2.5.6');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

		$serverResponse=curl_exec ($ch);

		curl_close ($ch);

        return $this->response = new PurchaseResponse($this, $serverResponse);
    }

	public function cardInfo() {
		return $this->getCard();
	}

}