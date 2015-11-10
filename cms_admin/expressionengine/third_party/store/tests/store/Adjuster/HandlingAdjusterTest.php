<?php

namespace Store\Adjuster;

use Store\Model\Discount;
use Store\TestCase;
use Store\Test\Factory;

class HandlingAdjusterTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->adjuster = new HandlingAdjuster($this->ee);
        $this->item = Factory::build('orderItem');
        $this->order = Factory::build('order');
        $this->order->setRelation('items', array($this->item));
    }

    public function test_adjust_sums_item_handling()
    {
        $this->item->handling = 10.0;
        $this->item->item_qty = 2;

        $this->order->order_handling = -1;
        $this->order->order_handling_tax = -1;
        $this->order->order_handling_total = -1;

        $this->adjuster->adjust($this->order);

        $this->assertSame(20.0, $this->order->order_handling);
        $this->assertSame(0, $this->order->order_handling_tax);
        $this->assertSame(20.0, $this->order->order_handling_total);
    }

    public function test_adjust_returns_adjustment()
    {
        $this->item->handling = 10.0;
        $this->item->item_qty = 2;

        $adjustments = $this->adjuster->adjust($this->order);

        $this->assertSame(1, count($adjustments));
        $this->assertSame('handling', $adjustments[0]->type);
        $this->assertSame(20.0, $adjustments[0]->amount);
    }

    public function test_adjust_returns_no_adjustment_when_no_charge()
    {
        $this->order->order_handling = -1;
        $this->order->order_handling_tax = -1;
        $this->order->order_handling_total = -1;

        $adjustments = $this->adjuster->adjust($this->order);

        $this->assertSame(0, count($adjustments));
        $this->assertSame(0, $this->order->order_handling);
        $this->assertSame(0, $this->order->order_handling_tax);
        $this->assertSame(0, $this->order->order_handling_total);
    }
}
