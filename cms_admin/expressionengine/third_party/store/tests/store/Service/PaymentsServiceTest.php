<?php

namespace Store\Service;

use Mockery as m;
use Store\Model\Transaction;
use Store\TestCase;
use Store\Test\Factory;

class PaymentsServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->service = new PaymentsService($this->ee);
        $this->order = Factory::create('order');
    }

    public function test_new_transaction()
    {
        $transaction = $this->service->new_transaction($this->order);

        $this->assertInstanceOf('Store\\Model\\Transaction', $transaction);
        $this->assertSame($this->order->site_id, $transaction->site_id);
        $this->assertSame($this->order->id, $transaction->order_id);
        $this->assertSame(time(), $transaction->date);
        $this->assertSame('pending', $transaction->status);
        $this->assertFalse($transaction->exists);
    }

    public function test_get_payment_gateways_includes_known_gateways()
    {
        $gateways = $this->service->get_payment_gateways();

        $this->assertContains('Stripe', $gateways);
        $this->assertContains('PayPal_Express', $gateways);
    }

    public function test_find_payment_method()
    {
        Factory::create('paymentMethod', array('class' => 'Dummy'));

        $method = $this->service->find_payment_method('dummy');

        $this->assertInstanceOf('Store\\Model\\PaymentMethod', $method);
    }

    public function test_find_payment_method_missing()
    {
        $method = $this->service->find_payment_method('dummy');

        $this->assertNull($method);
    }

    public function test_find_payment_method_invalid()
    {
        Factory::create('paymentMethod', array('class' => 'Invalid'));

        $method = $this->service->find_payment_method('invalid');

        $this->assertNull($method);
    }

    public function test_load_payment_method()
    {
        Factory::create('paymentMethod', array('class' => 'Dummy'));

        $gateway = $this->service->load_payment_method('dummy');

        $this->assertInstanceOf('Omnipay\\Common\\GatewayInterface', $gateway);
    }

    /**
     * @expectedException Store\Exception\CartException
     * @expectedExceptionMessage Invalid payment method.
     */
    public function test_load_payment_method_missing()
    {
        $this->service->load_payment_method('dummy');
    }

    public function test_build_payment_request()
    {
        $transaction = Factory::create('transaction', array(
            'order_id' => $this->order->id,
            'amount' => '2.01',
        ));

        $this->ee->store->store = m::mock('Store\\Service\\StoreService');
        $this->ee->store->store->shouldReceive('get_action_url')
            ->with('act_payment_return')
            ->andReturn('https://example.com/index.php?ACT=123');
        $this->ee->input->shouldReceive('ip_address')->andReturn('127.0.0.1');

        $data = $this->service->build_payment_request($transaction);

        $this->assertSame('2.01', $data['amount']);
        $this->assertSame('USD', $data['currency']);
        $this->assertSame($transaction->id, $data['transactionId']);
        $this->assertSame('Order #'.$this->order->id, $data['description']);
        $this->assertSame($transaction->reference, $data['transactionReference']);
        $this->assertSame('https://example.com/index.php?ACT=123&H='.$transaction->hash, $data['returnUrl']);
        $this->assertSame($this->order->cancelUrl, $data['cancelUrl']);
        $this->assertSame('127.0.0.1', $data['clientIp']);
        $this->assertSame(1, $data['noShipping']);
        $this->assertSame(0, $data['allowNote']);
        $this->assertSame(1, $data['addressOverride']);
    }

    public function test_build_return_url()
    {
        $transaction = Factory::build('transaction', array('hash' => 'abcdef'));
        $this->ee->store->store = m::mock('Store\\Service\\StoreService');
        $this->ee->store->store->shouldReceive('get_action_url')
            ->with('act_payment_return')
            ->andReturn('https://example.com/index.php?ACT=123');

        $url = $this->service->build_return_url($transaction);

        $this->assertSame('https://example.com/index.php?ACT=123&H=abcdef', $url);
    }

    public function test_build_return_url_no_query_string()
    {
        $transaction = Factory::build('transaction', array('hash' => 'abcdef', 'payment_method' => 'PaymentExpress_PxPay'));
        $this->ee->store->store = m::mock('Store\\Service\\StoreService');
        $this->ee->store->store->shouldReceive('create_url')
            ->with('payment_return/abcdef')
            ->andReturn('https://example.com/payment_return/abcdef');

        $url = $this->service->build_return_url($transaction);

        $this->assertSame('https://example.com/payment_return/abcdef', $url);
    }

    public function test_build_payment_credit_card_defaults_to_order_attributes()
    {
        $card = $this->service->build_payment_credit_card($this->order, array());

        $this->assertSame($this->order->billing_first_name, $card->getBillingFirstName());
        $this->assertSame($this->order->billing_last_name, $card->getBillingLastName());
        $this->assertSame($this->order->billing_address1, $card->getBillingAddress1());
        $this->assertSame($this->order->billing_address2, $card->getBillingAddress2());
        $this->assertSame($this->order->billing_city, $card->getBillingCity());
        $this->assertSame($this->order->billing_postcode, $card->getBillingPostcode());
        $this->assertSame($this->order->billing_state, $card->getBillingState());
        $this->assertSame($this->order->billing_country, $card->getBillingCountry());
        $this->assertSame($this->order->billing_phone, $card->getBillingPhone());
        $this->assertSame($this->order->shipping_first_name, $card->getShippingFirstName());
        $this->assertSame($this->order->shipping_last_name, $card->getShippingLastName());
        $this->assertSame($this->order->shipping_address1, $card->getShippingAddress1());
        $this->assertSame($this->order->shipping_address2, $card->getShippingAddress2());
        $this->assertSame($this->order->shipping_city, $card->getShippingCity());
        $this->assertSame($this->order->shipping_postcode, $card->getShippingPostcode());
        $this->assertSame($this->order->shipping_state, $card->getShippingState());
        $this->assertSame($this->order->shipping_country, $card->getShippingCountry());
        $this->assertSame($this->order->shipping_phone, $card->getShippingPhone());
        $this->assertSame($this->order->order_email, $card->getEmail());
    }

    public function test_build_payment_credit_card_maps_legacy_attrs()
    {
        $card = $this->service->build_payment_credit_card($this->order, array(
            'card_no' => '1234',
            'card_name' => 'adrian',
            'exp_month' => 6,
            'exp_year' => 2012,
            'start_month' => 2,
            'start_year' => 2005,
            'csc' => '123',
            'company' => 'Bills Corp',
        ));

        $this->assertSame('1234', $card->getnumber());
        $this->assertSame('adrian', $card->getName());
        $this->assertSame(6, $card->getExpiryMonth());
        $this->assertSame(2012, $card->getExpiryYear());
        $this->assertSame(2, $card->getStartMonth());
        $this->assertSame(2005, $card->getStartYear());
        $this->assertSame('123', $card->getCvv());
        $this->assertSame('Bills Corp', $card->getCompany());
    }

    public function test_build_payment_credit_card_attrs_overwrite_order_details()
    {
        $card = $this->service->build_payment_credit_card($this->order, array(
            'name' => 'Someone Else',
            'number' => '4321',
            'billing_address1' => '123 Different St',
        ));

        $this->assertSame('Someone', $card->getFirstName());
        $this->assertSame('Else', $card->getLastName());
        $this->assertSame('4321', $card->getNumber());
        $this->assertSame('123 Different St', $card->getBillingAddress1());
        $this->assertSame($this->order->billing_country, $card->getBillingCountry());
    }

    public function test_send_payment_request_success()
    {
        $request = m::mock('Omnipay\\Common\\Message\\RequestInterface');
        $response = m::mock('Omnipay\\Common\\Message\\ResponseInterface', array(
            'isSuccessful' => true,
            'getTransactionReference' => 'gateway-ref',
            'getMessage' => 'gateway-msg',
        ));
        $request->shouldReceive('send')->once()->andReturn($response);
        $this->ee->functions->shouldReceive('redirect')->with($this->order->parsed_return_url);

        $transaction = Factory::build('transaction', array('order_id' => $this->order->id));
        $this->service->send_payment_request($request, $transaction);

        $this->assertSame('success', $transaction->status);
        $this->assertSame('gateway-msg', $transaction->message);
    }

    public function test_send_payment_request_redirect()
    {
        $request = m::mock('Omnipay\\Common\\Message\\RequestInterface');
        $response = m::mock('Omnipay\\Common\\Message\\ResponseInterface', array(
            'isSuccessful' => false,
            'isRedirect' => true,
            'getTransactionReference' => 'gateway-ref',
            'getMessage' => 'gateway-msg',
        ));
        $request->shouldReceive('send')->once()->andReturn($response);
        $response->shouldReceive('redirect')->once();

        $transaction = Factory::build('transaction', array('order_id' => $this->order->id));
        $this->service->send_payment_request($request, $transaction);

        $this->assertSame('redirect', $transaction->status);
        $this->assertSame('gateway-msg', $transaction->message);
    }

    public function test_send_payment_request_failed()
    {
        $request = m::mock('Omnipay\\Common\\Message\\RequestInterface');
        $response = m::mock('Omnipay\\Common\\Message\\ResponseInterface', array(
            'isSuccessful' => false,
            'isRedirect' => false,
            'getTransactionReference' => 'gateway-ref',
            'getMessage' => 'gateway-msg',
        ));
        $request->shouldReceive('send')->once()->andReturn($response);
        $this->ee->session->shouldReceive('set_flashdata')->once()->with('store_payment_error', 'gateway-msg');
        $this->ee->functions->shouldReceive('redirect')->once()->with($this->order->cancel_url);

        $transaction = Factory::build('transaction', array('order_id' => $this->order->id));
        $this->service->send_payment_request($request, $transaction);

        $this->assertSame('failed', $transaction->status);
        $this->assertSame('gateway-msg', $transaction->message);
    }

    public function test_send_payment_request_error()
    {
        $request = m::mock('Omnipay\\Common\\Message\\RequestInterface');
        $request->shouldReceive('send')->once()->andThrow('Exception', 'exception-msg');
        $this->ee->session->shouldReceive('set_flashdata')->once()->with('store_payment_error', lang('store.payment.communication_error'));
        $this->ee->functions->shouldReceive('redirect')->once()->with($this->order->cancel_url);

        $transaction = Factory::build('transaction', array('order_id' => $this->order->id));
        $this->service->send_payment_request($request, $transaction);

        $this->assertSame('failed', $transaction->status);
        $this->assertSame(lang('store.payment.communication_error'), $transaction->message);
    }

    public function test_update_order_paid_total_should_mark_as_paid()
    {
        $this->order->order_paid = 0;
        $this->order->order_total = 100;
        $this->order->order_paid_date = null;
        $this->order->order_completed_date = time();
        $this->order->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::PURCHASE,
            'amount' => 100,
        )));

        $this->service->update_order_paid_total($this->order);

        $this->assertSame('100.0000', $this->order->order_paid);
        $this->assertSame(time(), $this->order->order_paid_date);
    }

    public function test_update_order_paid_total_should_mark_as_complete()
    {
        $this->order->order_paid = 0;
        $this->order->order_total = 100;
        $this->order->order_paid_date = null;
        $this->order->order_completed_date = null;
        $this->order->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::PURCHASE,
            'amount' => 100,
        )));

        $this->order = m::mock($this->order);
        $this->order->shouldReceive('markAsComplete')->once();

        $this->service->update_order_paid_total($this->order);
    }

    public function test_update_order_paid_total_should_mark_as_complete_authorized()
    {
        $this->order->order_paid = 0;
        $this->order->order_total = 100;
        $this->order->order_paid_date = null;
        $this->order->order_completed_date = null;
        $this->order->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::AUTHORIZE,
            'amount' => 100,
        )));

        $this->order = m::mock($this->order);
        $this->order->shouldReceive('markAsComplete')->once();

        $this->service->update_order_paid_total($this->order);

        $this->assertSame(0, $this->order->order_paid);
        $this->assertNull($this->order->order_paid_date);
    }
}
