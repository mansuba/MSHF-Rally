<?php

namespace Store\Adjuster;

use Store\Model\Discount;
use Store\TestCase;
use Store\Test\Factory;

class DiscountAdjusterTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->adjuster = new DiscountAdjuster($this->ee);
        $this->item = Factory::build('orderItem');
        $this->order = Factory::build('order');
        $this->order->setRelation('items', array($this->item));
        $this->discount = Factory::build('discount');
    }

    public function test_reset_discount_totals()
    {
        $this->order->order_discount = 99;
        $this->order->order_shipping_discount = 99;
        $this->order->discount_id = 1;
        $this->item->item_discount = 99;

        $this->adjuster->reset_discount_totals($this->order);

        $this->assertSame(0, $this->order->order_discount);
        $this->assertSame(0, $this->order->order_shipping_discount);
        $this->assertNull($this->order->discount_id);
        $this->assertSame(0, $this->item->item_discount);
    }

    public function test_calculate_items_discount_per_item()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->per_item_discount = 2;
        $this->item->item_qty = 3;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-16.0, $adjustment->amount);
    }

    public function test_calculate_items_discount_percent()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->percent_discount = 50;

        $this->item->item_qty = 5;
        $this->item->price = 20;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-60.0, $adjustment->amount);
    }

    public function test_calculate_items_discount_over_minimum_purchase_qty()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->purchase_qty = 3;
        $this->item->item_qty = 3;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-10.0, $adjustment->amount);
    }

    public function test_calculate_items_discount_under_minimum_purchase_qty()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->purchase_qty = 4;
        $this->item->item_qty = 3;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertNull($adjustment);
    }

    public function test_calculate_items_discount_over_minimum_purcahse_total()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->purchase_total = 100;

        $this->item->item_qty = 5;
        $this->item->price = 20;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-10.0, $adjustment->amount);
    }

    public function test_calculate_items_discount_under_minimum_purchase_total()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->purchase_total = 101;

        $this->item->item_qty = 5;
        $this->item->price = 20;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertNull($adjustment);
    }

    public function test_calculate_updates_item_discount()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->per_item_discount = 2;

        $this->item->item_qty = 3;
        $this->item->item_discount = 5;
        $this->item->item_total = 100;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-16.0, $adjustment->amount);
        $this->assertSame(21.0, $this->item->item_discount);
        $this->assertSame(84.0, $this->item->item_total);
    }

    public function test_calculate_splits_item_discount()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->per_item_discount = 2;

        $item1 = Factory::build('orderItem', array('item_qty' => 1, 'item_total' => 100));
        $item2 = Factory::build('orderItem', array('item_qty' => 2, 'item_total' => 100));
        $this->order->setRelation('items', array($item1, $item2));

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-16.0, $adjustment->amount);

        $this->assertSame(5.33, $item1->item_discount);
        $this->assertSame(94.67, $item1->item_total);

        // the extra cent should be allocated to last item to ensure total is accurate
        $this->assertSame(10.67, $item2->item_discount);
        $this->assertSame(89.33, $item2->item_total);

        $this->assertSame(16.0, $item1->item_discount + $item2->item_discount);
    }

    public function test_calculate_adds_free_shipping()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->free_shipping = true;

        $this->order->order_shipping = 20.0;
        $this->order->order_shipping_total = 20.0;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-30.0, $adjustment->amount);
        $this->assertSame(20.0, $this->order->order_shipping);
        $this->assertSame(20.0, $this->order->order_shipping_discount);
        $this->assertSame(0.0, $this->order->order_shipping_total);
    }

    public function test_calculate_doesnt_apply_duplicate_free_shipping()
    {
        $this->discount->type = 'items';
        $this->discount->base_discount = 10;
        $this->discount->free_shipping = true;

        $this->order->order_shipping = 20.0;
        $this->order->order_shipping_discount = 20.0;
        $this->order->order_shipping_total = 0.0;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-10.0, $adjustment->amount);
        $this->assertSame(20.0, $this->order->order_shipping_discount);
        $this->assertSame(0.0, $this->order->order_shipping_total);
    }

    public function test_calculate_bulk_discount_per_item()
    {
        $this->discount->type = 'bulk';
        $this->discount->base_discount = 10;
        $this->discount->step_qty = 3;
        $this->discount->discount_qty = 2;
        $this->discount->per_item_discount = 2;

        $this->item->item_qty = 5;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-14.0, $adjustment->amount);
    }

    public function test_calculate_bulk_discount_percent()
    {
        $this->discount->type = 'bulk';
        $this->discount->base_discount = 10;
        $this->discount->step_qty = 3;
        $this->discount->discount_qty = 2;
        $this->discount->percent_discount = 50;

        $this->item->item_qty = 5;
        $this->item->price = 100;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-110.0, $adjustment->amount);
    }

    public function test_calculate_bulk_discount_with_repeat()
    {
        $this->discount->type = 'bulk';
        $this->discount->base_discount = 10;
        $this->discount->step_qty = 3;
        $this->discount->discount_qty = 2;
        $this->discount->per_item_discount = 2;

        $this->item->item_qty = 15;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-22.0, $adjustment->amount);
    }

    public function test_calculate_bulk_discount_with_remainder()
    {
        $this->discount->type = 'bulk';
        $this->discount->base_discount = 10;
        $this->discount->step_qty = 3;
        $this->discount->discount_qty = 2;
        $this->discount->per_item_discount = 2;

        $this->item->item_qty = 4;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-12.0, $adjustment->amount);
    }

    public function test_calculate_bulk_discount_updates_item_discount()
    {
        $this->discount->type = 'bulk';
        $this->discount->base_discount = 10;
        $this->discount->step_qty = 3;
        $this->discount->discount_qty = 2;
        $this->discount->per_item_discount = 2;

        $this->item->item_qty = 5;
        $this->item->item_discount = 15;

        $adjustment = $this->adjuster->calculate($this->order, $this->discount);

        $this->assertSame(-14.0, $adjustment->amount);
        $this->assertSame(29.0, $this->item->item_discount);
    }

    public function test_match_item_returns_true_for_matching_items()
    {
        $this->assertTrue($this->adjuster->match_item($this->item, $this->discount));
    }

    public function test_match_item_returns_false_when_entry_ids_dont_match()
    {
        $this->discount->entry_ids = array(1, 2, 3);
        $this->item->entry_id = 4;

        $this->assertFalse($this->adjuster->match_item($this->item, $this->discount));
    }

    public function test_match_item_returns_true_when_entry_ids_match()
    {
        $this->discount->entry_ids = array(1, 2, 3);
        $this->item->entry_id = 3;

        $this->assertTrue($this->adjuster->match_item($this->item, $this->discount));
    }

    public function test_match_item_returns_false_when_category_ids_dont_match()
    {
        $this->discount->category_ids = array(1, 2, 3);
        $this->item->category_ids = array(4);

        $this->assertFalse($this->adjuster->match_item($this->item, $this->discount));
    }

    public function test_match_item_returns_true_when_category_ids_match()
    {
        $this->discount->category_ids = array(1, 2, 3);
        $this->item->category_ids = array(3);

        $this->assertTrue($this->adjuster->match_item($this->item, $this->discount));
    }

    public function test_match_item_returns_false_when_excludes_on_sale()
    {
        $this->discount->exclude_on_sale = true;
        $this->item->on_sale = true;

        $this->assertFalse($this->adjuster->match_item($this->item, $this->discount));
    }
}
