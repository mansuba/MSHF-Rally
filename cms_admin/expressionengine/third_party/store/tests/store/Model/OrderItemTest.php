<?php

namespace Store\Model;

use Store\TestCase;
use Store\Test\Factory;

class OrderItemTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->stock = Factory::build('stock');
        $this->model = Factory::build('orderItem');
        $this->model->setRelation('stock', $this->stock);
    }

    public function test_handling_inc_tax()
    {
        $this->model->handling = 7.0;
        $this->model->handling_tax = 2.0;
        $this->assertSame(9.0, $this->model->handling_inc_tax);
    }

    public function test_item_subtotal_inc_discount()
    {
        $this->model->item_subtotal = 20.0;
        $this->model->item_discount = 5.0;
        $this->assertSame(15.0, $this->model->item_subtotal_inc_discount);
    }

    public function test_modifiers_html()
    {
        $this->model->setAttribute('modifiers', array(
            array('modifier_name' => 'Foo', 'modifier_value' => 'Bar'),
            array('modifier_name' => 'Custom', 'modifier_value' => ''),
            array('modifier_name' => 'Size', 'modifier_value' => 'Large'),
        ));

        $this->assertSame('<strong>Foo</strong>: Bar, <strong>Size</strong>: Large', $this->model->modifiers_html);
    }

    public function test_modifier_html_null()
    {
        $this->assertSame('', $this->model->modifiers_html);
    }

    public function test_recalculate_resets_attributes()
    {
        $this->model->handling_tax = -1;
        $this->model->item_subtotal = -1;
        $this->model->item_tax = -1;
        $this->model->item_total = -1;

        $this->model->price = 2.0;
        $this->model->item_qty = 5;

        $this->model->recalculate();

        // item_qty should not change
        $this->assertSame(5, $this->model->item_qty);

        $this->assertSame(0, $this->model->handling_tax);
        $this->assertSame(0, $this->model->item_tax);
        // totals are price * qty
        $this->assertSame(10.0, $this->model->item_subtotal);
        $this->assertSame(10.0, $this->model->item_total);
    }

    public function test_recalculate_min_order_qty_enforced()
    {
        $this->stock->min_order_qty = 5;
        $this->model->item_qty = 1;
        $this->model->recalculate();
        $this->assertSame(5, $this->model->item_qty);
    }

    public function test_recalculate_min_order_qty_already_met()
    {
        $this->stock->min_order_qty = 5;
        $this->model->item_qty = 6;
        $this->model->recalculate();
        $this->assertSame(6, $this->model->item_qty);
    }

    public function test_recalculate_min_order_qty_ignores_no_minimum()
    {
        $this->stock->min_order_qty = null;
        $this->model->item_qty = -1;
        $this->model->recalculate();
        $this->assertSame(-1, $this->model->item_qty);
    }

    public function test_recalculate_min_order_qty_ignores_zero_qty()
    {
        $this->stock->min_order_qty = 5;
        $this->model->item_qty = 0;
        $this->model->recalculate();
        $this->assertSame(0, $this->model->item_qty);
    }

    public function test_recalculate_cant_order_more_than_available_stock()
    {
        $this->stock->track_stock = true;
        $this->stock->stock_level = 2;
        $this->model->item_qty = 4;
        $this->model->recalculate();
        $this->assertSame(2, $this->model->item_qty);
    }

    public function test_recalculate_available_stock_less_than_min_order_qty()
    {
        $this->stock->track_stock = true;
        $this->stock->stock_level = 2;
        $this->stock->min_order_qty = 3;
        $this->model->item_qty = 3;
        $this->model->recalculate();
        $this->assertSame(0, $this->model->item_qty);
    }
}
