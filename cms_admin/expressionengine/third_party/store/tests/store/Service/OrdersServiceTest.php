<?php

namespace Store\Service;

use Mockery as m;
use Store\TestCase;
use Store\Test\Factory;

class OrdersServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->service = new OrdersService($this->ee);
        $this->order = Factory::create('order');
        $this->tax = Factory::build('tax', array(
            'country_code' => $this->order->shipping_country,
            'state_code' => $this->order->shipping_state,
        ));
    }

    public function test_get_adjusters()
    {
        $adjusters = $this->service->get_adjusters();

        $this->assertInstanceOf('\Store\Adjuster\ShippingAdjuster', $adjusters[10]);
        $this->assertInstanceOf('\Store\Adjuster\HandlingAdjuster', $adjusters[15]);
        $this->assertInstanceOf('\Store\Adjuster\DiscountAdjuster', $adjusters[20]);
        $this->assertInstanceOf('\Store\Adjuster\TaxAdjuster', $adjusters[30]);
    }

    public function test_get_adjusters_hook()
    {
        $this->ee->extensions->shouldReceive('active_hook')
            ->with('store_order_adjusters')->once()
            ->andReturn(true);

        $this->ee->extensions->shouldReceive('call')
            ->with('store_order_adjusters', m::type('array'))
            ->andReturn(array(5 => 'adjust'));

        $this->assertSame(array(5 => 'adjust'), $this->service->get_adjusters());
    }

    public function test_get_order_taxes_match()
    {
        $this->tax->save();

        $taxes = $this->service->get_order_taxes($this->order);

        $this->assertSame(1, count($taxes));
        $this->assertSame($this->tax->id, $taxes[0]->id);
    }

    public function test_get_order_taxes_disabled()
    {
        $this->tax->enabled = 0;
        $this->tax->save();

        $taxes = $this->service->get_order_taxes($this->order);

        $this->assertSame(0, count($taxes));
    }

    public function test_get_order_taxes_different_site_id()
    {
        $this->order->site_id = 9;
        $this->tax->save();

        $taxes = $this->service->get_order_taxes($this->order);

        $this->assertSame(0, count($taxes));
    }

    public function test_get_order_taxes_different_country()
    {
        $this->order->shipping_country = 'XX';
        $this->tax->save();

        $taxes = $this->service->get_order_taxes($this->order);

        $this->assertSame(0, count($taxes));
    }

    public function test_get_order_taxes_empty_country()
    {
        $this->tax->country_code = '';
        $this->tax->save();

        $taxes = $this->service->get_order_taxes($this->order);

        $this->assertSame(1, count($taxes));
    }

    public function test_get_order_taxes_different_state()
    {
        $this->order->shipping_state = 'XX';
        $this->tax->save();

        $taxes = $this->service->get_order_taxes($this->order);

        $this->assertSame(0, count($taxes));
    }

    public function test_get_order_taxes_empty_state()
    {
        $this->tax->state_code = '';
        $this->tax->save();

        $taxes = $this->service->get_order_taxes($this->order);

        $this->assertSame(1, count($taxes));
    }

    public function test_order_statuses()
    {
        $status = Factory::create('status', array('name' => 'foo'));

        $statuses = $this->service->order_statuses();

        $this->assertArrayHasKey('foo', $statuses);
        $this->assertSame($status->id, $statuses['foo']->id);
    }
}
