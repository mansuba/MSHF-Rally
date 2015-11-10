<?php

namespace Store\Adjuster;

use Store\Model\Tax;
use Store\TestCase;
use Store\Test\Factory;

class TaxAdjusterTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->adjuster = new TaxAdjuster($this->ee);
        $this->order = Factory::build('order');
        $this->tax = Factory::build('tax');
    }

    public function test_calculate_free_order()
    {
        $this->order->setRelation('items', array());
        $this->assertNull($this->adjuster->calculate($this->order, $this->tax));
    }

    public function test_calculate_exclusive_tax()
    {
        $this->tax->rate = 0.1;
        $this->tax->included = 0;
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_subtotal' => 100)),
        ));

        $adjustment = $this->adjuster->calculate($this->order, $this->tax);

        $this->assertSame($this->tax->name, $adjustment->name);
        $this->assertSame('tax', $adjustment->type);
        $this->assertSame($this->tax->rate, $adjustment->rate);
        $this->assertSame(10.0, $adjustment->amount);
        $this->assertSame(0, $adjustment->taxable);
        $this->assertSame(0, $adjustment->included);
    }

    public function test_calculate_inclusive_tax()
    {
        $this->tax->rate = 0.1;
        $this->tax->included = 1;
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_subtotal' => 100)),
        ));

        $adjustment = $this->adjuster->calculate($this->order, $this->tax);

        $this->assertSame($this->tax->name, $adjustment->name);
        $this->assertSame('tax', $adjustment->type);
        $this->assertSame($this->tax->rate, $adjustment->rate);
        $this->assertSame(9.09, $adjustment->amount);
        $this->assertSame(0, $adjustment->taxable);
        $this->assertSame(1, $adjustment->included);
    }

    public function test_calculate_total_tax_no_categories()
    {
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_subtotal' => 100)),
            Factory::build('orderItem', array('item_subtotal' => 200)),
        ));
        $this->order->order_shipping = 150;
        $this->tax->rate = 0.1;

        // should not include shipping if apply_to_shipping is false
        $this->tax->apply_to_shipping = 0;
        $this->assertSame(30.0, $this->adjuster->calculate_total_tax($this->order, $this->tax));

        // should include shipping if apply_to_shipping is true
        $this->tax->apply_to_shipping = 1;
        $this->assertSame(45.0, $this->adjuster->calculate_total_tax($this->order, $this->tax));
    }

    public function test_calculate_total_tax_with_categories()
    {
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_subtotal' => 100, 'category_ids' => array(1, 2))),
            Factory::build('orderItem', array('item_subtotal' => 200, 'category_ids' => array(1, 3))),
            Factory::build('orderItem', array('item_subtotal' => 300, 'category_ids' => array(2, 3))),
        ));
        $this->tax->rate = 0.1;

        // should only include items which match tax category
        $this->tax->setRelation('categories', array(
            Factory::build('category', array('cat_id' => 1)),
        ));
        $this->assertSame(30.0, $this->adjuster->calculate_total_tax($this->order, $this->tax));

        // should include items matching any tax category
        $this->tax->setRelation('categories', array(
            Factory::build('category', array('cat_id' => 1)),
            Factory::build('category', array('cat_id' => 2)),
        ));
        $this->assertSame(60.0, $this->adjuster->calculate_total_tax($this->order, $this->tax));
    }

    public function test_calculate_total_tax_exclusive_tax_updates_item_tax_and_total()
    {
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_subtotal' => 100.0, 'item_tax' => 10.0, 'item_total' => 110.0)),
        ));

        $this->tax->rate = 0.1;

        $this->adjuster->calculate_total_tax($this->order, $this->tax);

        $this->assertSame(20.0, $this->order->items[0]->item_tax);
        $this->assertSame(120.0, $this->order->items[0]->item_total);
    }

    public function test_calculate_total_tax_inclusive_tax_updates_item_tax_and_not_total()
    {
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_subtotal' => 100.0, 'item_tax' => 10.0, 'item_total' => 100.0)),
        ));

        $this->tax->included = true;
        $this->tax->rate = 0.1;

        $this->adjuster->calculate_total_tax($this->order, $this->tax);

        $this->assertSame(19.09, $this->order->items[0]->item_tax);
        $this->assertSame(100.0, $this->order->items[0]->item_total);
    }

    public function test_calculate_total_tax_includes_item_discount()
    {
        $this->order->setRelation('items', array(
            Factory::build('orderItem', array('item_subtotal' => 100.0, 'item_discount' => 40.0, 'item_tax' => 0, 'item_total' => 60.0)),
        ));

        $this->tax->rate = 0.1;

        $this->adjuster->calculate_total_tax($this->order, $this->tax);

        $this->assertSame(6.0, $this->order->items[0]->item_tax);
        $this->assertSame(66.0, $this->order->items[0]->item_total);
    }

    public function test_calculate_total_tax_exclusive_tax_updates_shipping_tax_and_total()
    {
        $this->order->order_shipping = 100;
        $this->order->order_shipping_tax = 10;
        $this->order->order_shipping_total = 110;

        $this->tax->apply_to_shipping = true;
        $this->tax->rate = 0.1;

        $this->adjuster->calculate_total_tax($this->order, $this->tax);

        $this->assertSame(20.0, $this->order->order_shipping_tax);
        $this->assertSame(120.0, $this->order->order_shipping_total);
    }

    public function test_calculate_total_tax_inclusive_tax_updates_shipping_tax_and_not_total()
    {
        $this->order->order_shipping = 100.0;
        $this->order->order_shipping_tax = 10.0;
        $this->order->order_shipping_total = 100.0;

        $this->tax->apply_to_shipping = true;
        $this->tax->included = true;
        $this->tax->rate = 0.1;

        $this->adjuster->calculate_total_tax($this->order, $this->tax);

        $this->assertSame(19.09, $this->order->order_shipping_tax);
        $this->assertSame(100.0, $this->order->order_shipping_total);
    }

    public function test_calculate_total_tax_includes_shipping_discount()
    {
        $this->order->order_shipping = 100;
        $this->order->order_shipping_discount = 20;

        $this->tax->apply_to_shipping = true;
        $this->tax->rate = 0.1;

        $this->adjuster->calculate_total_tax($this->order, $this->tax);

        $this->assertSame(8.0, $this->order->order_shipping_tax);
    }

    public function test_calculate_total_tax_exclusive_tax_updates_handling_tax_and_total()
    {
        $this->order->order_handling = 100;
        $this->order->order_handling_tax = 10;
        $this->order->order_handling_total = 110;

        $this->tax->apply_to_shipping = true;
        $this->tax->rate = 0.1;

        $this->adjuster->calculate_total_tax($this->order, $this->tax);

        $this->assertSame(20.0, $this->order->order_handling_tax);
        $this->assertSame(120.0, $this->order->order_handling_total);
    }

    public function test_calculate_total_tax_inclusive_tax_updates_handling_tax_and_not_total()
    {
        $this->order->order_handling = 100.0;
        $this->order->order_handling_tax = 10.0;
        $this->order->order_handling_total = 100.0;

        $this->tax->apply_to_shipping = true;
        $this->tax->included = true;
        $this->tax->rate = 0.1;

        $this->adjuster->calculate_total_tax($this->order, $this->tax);

        $this->assertSame(19.09, $this->order->order_handling_tax);
        $this->assertSame(100.0, $this->order->order_handling_total);
    }

    public function test_calculate_tax_inclusive()
    {
        $this->tax->included = true;
        $this->tax->rate = 0.1;

        $this->assertSame(9.09, $this->adjuster->calculate_tax(100.0, $this->tax));
    }

    public function test_calculate_tax_exclusive()
    {
        $this->tax->included = false;
        $this->tax->rate = 0.1;

        $this->assertSame(10.0, $this->adjuster->calculate_tax(100.0, $this->tax));
    }

    public function test_is_item_taxable_no_categories()
    {
        $item = Factory::build('orderItem');

        $this->assertTrue($this->adjuster->is_item_taxable($item, $this->tax));
    }

    public function test_is_item_taxable_matched_categories()
    {
        $item = Factory::build('orderItem', array('item_subtotal' => 100, 'category_ids' => array(1, 5)));
        $this->tax->setRelation('categories', array(
            Factory::build('category', array('cat_id' => 5)),
        ));

        $this->assertTrue($this->adjuster->is_item_taxable($item, $this->tax));
    }

    public function test_is_item_taxable_unmatched_categories()
    {
        $item = Factory::build('orderItem', array('item_subtotal' => 100, 'category_ids' => array(1, 5)));
        $this->tax->setRelation('categories', array(
            Factory::build('category', array('cat_id' => 6)),
        ));

        $this->assertFalse($this->adjuster->is_item_taxable($item, $this->tax));
    }
}
