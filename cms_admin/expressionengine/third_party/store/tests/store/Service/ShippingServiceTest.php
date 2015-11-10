<?php

namespace Store\Service;

use Mockery as m;
use Store\TestCase;
use Store\Test\Factory;

class ShippingServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->service = new ShippingService($this->ee);
        $this->orderItem = Factory::build('orderItem', array('item_qty' => 1));
        $this->order = Factory::build('order');
        $this->order->setRelation('items', array($this->orderItem));
        $this->shipping_rule = Factory::build('shippingRule');
    }

    public function test_test_shipping_rule()
    {
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_test_shipping_rule_disabled()
    {
        $this->shipping_rule->enabled = false;
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_test_shipping_rule_country()
    {
        $this->shipping_rule->country_code = 'NZ';

        $this->order->shipping_country = 'NZ';
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->order->shipping_country = 'AU';
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_test_shipping_rule_state()
    {
        $this->shipping_rule->state_code = 'CA';

        $this->order->shipping_state = 'CA';
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->order->shipping_state = 'CO';
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_test_shipping_rule_postcode()
    {
        $this->shipping_rule->postcode = '12345';
        $this->order->shipping_postcode = '12345';
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->order->shipping_postcode = '12340';
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_test_shipping_rule_postcode_wildcards()
    {
        $this->order->shipping_postcode = '12345';
        $this->shipping_rule->postcode = '12345*';
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->shipping_rule->postcode = '12*';
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->shipping_rule->postcode = '10*';
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->shipping_rule->postcode = '12345?';
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->shipping_rule->postcode = '1234?';
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->shipping_rule->postcode = '12?';
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_test_shipping_rule_order_qty()
    {
        $this->shipping_rule->min_order_qty = 5;
        $this->shipping_rule->max_order_qty = 8;

        $this->orderItem->item_qty = 4;
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->item_qty = 5;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->item_qty = 7;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->item_qty = 8;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->item_qty = 9;
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_test_shipping_rule_order_total()
    {
        $this->shipping_rule->min_order_total = 4.00;
        $this->shipping_rule->max_order_total = 5.00;

        $this->orderItem->item_subtotal = 3.99;
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->item_subtotal = 4.00;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->item_subtotal = 4.99;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->item_subtotal = 5.00;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->item_subtotal = 5.01;
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_test_shipping_rule_weight()
    {
        $this->shipping_rule->min_weight = 2.00;
        $this->shipping_rule->max_weight = 3.00;

        $this->orderItem->weight = 1.99;
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->weight = 2.00;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->weight = 2.99;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->weight = 3.00;
        $this->assertTrue($this->service->test_shipping_rule($this->order, $this->shipping_rule));

        $this->orderItem->weight = 3.01;
        $this->assertFalse($this->service->test_shipping_rule($this->order, $this->shipping_rule));
    }

    public function test_calculate_shipping_rule_base_rate()
    {
        $this->shipping_rule->base_rate = 15.00;

        $amount = $this->service->calculate_shipping_rule($this->order, $this->shipping_rule);
        $this->assertSame(15.00, $amount);
    }

    public function test_calculate_shipping_rule_min_rate()
    {
        $this->shipping_rule->base_rate = 15.00;
        $this->shipping_rule->min_rate = 20.00;

        $amount = $this->service->calculate_shipping_rule($this->order, $this->shipping_rule);
        $this->assertSame(20.00, $amount);
    }

    public function test_calculate_shipping_rule_max_rate()
    {
        $this->shipping_rule->base_rate = 15.00;
        $this->shipping_rule->max_rate = 10.00;

        $amount = $this->service->calculate_shipping_rule($this->order, $this->shipping_rule);
        $this->assertSame(10.00, $amount);
    }

    public function test_calculate_shipping_free_shipping()
    {
        $this->shipping_rule->base_rate = 15.00;

        // all order items have free shipping, base rate doesn't apply
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_qty' => 2, 'weight' => '3.00', 'item_subtotal' => '10.00', 'free_shipping' => 1)),
        ));

        $amount = $this->service->calculate_shipping_rule($this->order, $this->shipping_rule);
        $this->assertSame(0.0, $amount);
    }

    public function test_calculate_shipping_rule_per_item_rate()
    {
        $this->shipping_rule->base_rate = 15.00;
        $this->shipping_rule->per_item_rate = 10.00;
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_qty' => 2, 'weight' => '3.00', 'item_subtotal' => '10.00')),
            Factory::build('orderItem', array('item_qty' => 2, 'weight' => '3.00', 'item_subtotal' => '10.00', 'free_shipping' => 1)),
        ));

        $amount = $this->service->calculate_shipping_rule($this->order, $this->shipping_rule);
        $this->assertSame(35.00, $amount);
    }

    public function test_calculate_shipping_rule_per_weight_rate()
    {
        $this->shipping_rule->base_rate = 15.00;
        $this->shipping_rule->per_weight_rate = 10.00;
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_qty' => 2, 'weight' => '3.00', 'item_subtotal' => '10.00')),
            Factory::build('orderItem', array('item_qty' => 2, 'weight' => '3.00', 'item_subtotal' => '10.00', 'free_shipping' => 1)),
        ));

        $amount = $this->service->calculate_shipping_rule($this->order, $this->shipping_rule);
        $this->assertSame(75.00, $amount);
    }

    public function test_calculate_shipping_rule_percent_rate()
    {
        $this->shipping_rule->base_rate = 15.00;
        $this->shipping_rule->percent_rate = 50.00;
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_qty' => 2, 'weight' => '3.00', 'item_subtotal' => '10.00')),
            Factory::build('orderItem', array('item_qty' => 2, 'weight' => '3.00', 'item_subtotal' => '10.00', 'free_shipping' => 1)),
        ));

        $amount = $this->service->calculate_shipping_rule($this->order, $this->shipping_rule);
        $this->assertSame(20.00, $amount);
    }
}
