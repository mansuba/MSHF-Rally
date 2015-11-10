<?php

namespace Store\Adjuster;

use Store\Model\Discount;
use Store\TestCase;
use Store\Test\Factory;

class ShippingAdjusterTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->adjuster = new ShippingAdjuster($this->ee);
        $this->item = Factory::build('orderItem');
        $this->order = Factory::build('order');
        $this->order->setRelation('items', array($this->item));

        $this->method = Factory::create('shippingMethod');
        $this->rule = Factory::create('shippingRule', array('shipping_method_id' => $this->method->id));

        $this->order->shipping_method = $this->method->id;
    }

    public function test_adjust_sets_order_shipping_and_total()
    {
        $this->order->order_shipping = -1;
        $this->order->order_shipping_discount = -1;
        $this->order->order_shipping_tax = -1;
        $this->order->order_shipping_total = -1;

        $this->rule->base_rate = 10.0;
        $this->rule->save();

        $this->adjuster->adjust($this->order);

        $this->assertSame(10.0, $this->order->order_shipping);
        $this->assertSame(0, $this->order->order_shipping_discount);
        $this->assertSame(0, $this->order->order_shipping_tax);
        $this->assertSame(10.0, $this->order->order_shipping_total);
    }

    public function test_adjust_ignores_invalid_shipping_method()
    {
        $this->order->shipping_method = 'not-a-method-id';

        $this->order->order_shipping = -1;
        $this->order->order_shipping_discount = -1;
        $this->order->order_shipping_tax = -1;
        $this->order->order_shipping_total = -1;

        $this->adjuster->adjust($this->order);

        $this->assertSame(0, $this->order->order_shipping);
        $this->assertSame(0, $this->order->order_shipping_discount);
        $this->assertSame(0, $this->order->order_shipping_tax);
        $this->assertSame(0, $this->order->order_shipping_total);
    }
}
