<?php

namespace Store\Model;

use Store\Model\Collection;
use Store\TestCase;
use Store\Test\Factory;

class OrderTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = Factory::build('order');
        $this->model->setRelation('items', new Collection);
    }

    public function test_order_subtotal_inc_tax()
    {
        $this->model->order_subtotal = 100;
        $this->model->order_subtotal_tax = 20;
        $this->model->save();
        $this->assertSame(120, $this->model->order_subtotal_inc_tax);
    }

    public function test_order_subtotal_inc_discount()
    {
        $this->model->order_subtotal = 100;
        $this->model->order_discount = 20;
        $this->model->save();
        $this->assertSame(80, $this->model->order_subtotal_inc_discount);
    }

    public function test_order_subtotal_inc_discount_tax()
    {
        $this->model->order_subtotal_tax = 50;
        $this->model->order_discount_tax = 10;
        $this->model->save();
        $this->assertSame(40, $this->model->order_subtotal_inc_discount_tax);
    }

    public function test_order_subtotal_inc_discount_inc_tax()
    {
        $this->model->order_subtotal = 200;
        $this->model->order_subtotal_tax = 30;
        $this->model->order_discount = 50;
        $this->model->order_discount_tax = 10;
        $this->model->save();
        $this->assertSame(170, $this->model->order_subtotal_inc_discount_inc_tax);
    }

    public function test_order_discount_inc_tax()
    {
        $this->model->order_discount = 100;
        $this->model->order_discount_tax = 10;
        $this->model->save();
        $this->assertSame(110, $this->model->order_discount_inc_tax);
    }

    public function test_order_shipping_inc_discount()
    {
        $this->model->order_shipping = 20.0;
        $this->model->order_shipping_discount = 5.0;
        $this->model->save();
        $this->assertSame(15.0, $this->model->order_shipping_inc_discount);
    }

    public function test_order_shipping_inc_tax()
    {
        $this->model->order_shipping_total = 50;
        $this->model->save();
        $this->assertSame(50, $this->model->order_shipping_inc_tax);
    }

    public function test_order_handling_inc_tax()
    {
        $this->model->order_handling_total = 50;
        $this->model->save();
        $this->assertSame(50, $this->model->order_handling_inc_tax);
    }

    public function test_order_subtotal_inc_shipping()
    {
        $this->model->order_subtotal = 100;
        $this->model->order_shipping = 20;
        $this->model->save();
        $this->assertSame(120, $this->model->order_subtotal_inc_shipping);
    }

    public function test_order_subtotal_inc_shipping_tax()
    {
        $this->model->order_subtotal_tax = 10;
        $this->model->order_shipping_tax = 20;
        $this->model->save();
        $this->assertSame(30, $this->model->order_subtotal_inc_shipping_tax);
    }

    public function test_order_subtotal_inc_shipping_inc_tax()
    {
        $this->model->order_subtotal = 130;
        $this->model->order_subtotal_tax = 20;
        $this->model->order_shipping_total = 30;
        $this->model->save();
        $this->assertSame(180, $this->model->order_subtotal_inc_shipping_inc_tax);
    }

    public function test_order_total_ex_tax()
    {
        $this->model->order_total = 100;
        $this->model->order_tax = 30;
        $this->model->save();
        $this->assertSame(70, $this->model->order_total_ex_tax);
    }

    public function test_order_owing()
    {
        $this->model->order_total = 100;
        $this->model->order_paid = 40;
        $this->model->save();
        $this->assertSame(60, $this->model->order_owing);
    }

    public function test_order_items_total()
    {
        $items = array();
        $items[] = Factory::build('orderItem', array('item_total' => '10.00'));
        $items[] = Factory::build('orderItem', array('item_total' => '20.00'));
        $this->model->setRelation('items', $items);

        $this->assertSame(30.0, $this->model->order_items_total);
    }

    public function test_order_adjustments_total()
    {
        $this->model->setRelation('adjustments', array(
            Factory::build('orderAdjustment', array('amount' => '10.00', 'included' => 0)),
            Factory::build('orderAdjustment', array('amount' => '20.00', 'included' => 1)),
        ));

        $this->assertSame(10.0, $this->model->order_adjustments_total);
    }

    public function test_order_you_save()
    {
        $items = array();
        $items[] = Factory::build('orderItem', array('price' => '20.00', 'regular_price' => '30.00', 'item_qty' => 2));
        $items[] = Factory::build('orderItem', array('price' => '5.00', 'regular_price' => '10.00', 'item_qty' => 1));
        $this->model->setRelation('items', $items);

        $this->assertSame(25.0, $this->model->order_you_save);
    }

    public function test_count_items_by_id()
    {
        $items = array();
        $items[] = Factory::build('orderItem', array('entry_id' => 10, 'item_qty' => 5));
        $items[] = Factory::build('orderItem', array('entry_id' => 10, 'item_qty' => 2));
        $items[] = Factory::build('orderItem', array('entry_id' => 11, 'item_qty' => 3));
        $this->model->setRelation('items', $items);

        $this->assertSame(7, $this->model->countItemsById(10));
        $this->assertSame(3, $this->model->countItemsById(11));
    }

    public function test_get_total_paid_should_include_purchases()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::PURCHASE,
            'amount' => 100,
        )));

        $this->assertSame('100.0000', $this->model->getTotalPaid());
    }

    public function test_get_total_paid_should_include_refunds()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::PURCHASE,
            'amount' => 100,
        )));
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::REFUND,
            'amount' => 50,
        )));

        $this->assertSame('50.0000', $this->model->getTotalPaid());
    }

    public function test_get_total_paid_should_include_captures()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::CAPTURE,
            'amount' => 100,
        )));

        $this->assertSame('100.0000', $this->model->getTotalPaid());
    }

    public function test_get_total_paid_should_not_include_authorizations()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::AUTHORIZE,
            'amount' => 100,
        )));

        $this->assertSame(0, $this->model->getTotalPaid());
    }

    public function test_get_total_paid_should_include_failed_transactions()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::PURCHASE,
            'status' => Transaction::FAILED,
            'amount' => 100,
        )));

        $this->assertSame(0, $this->model->getTotalPaid());
    }

    public function test_get_total_authorized_should_include_purchases()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::PURCHASE,
            'amount' => 100,
        )));

        $this->assertSame('100.0000', $this->model->getTotalAuthorized());
    }

    public function test_get_total_authorized_should_include_refunds()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::PURCHASE,
            'amount' => 100,
        )));
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::REFUND,
            'amount' => 50,
        )));

        $this->assertSame('50.0000', $this->model->getTotalAuthorized());
    }

    public function test_get_total_authorized_should_include_captures()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::CAPTURE,
            'amount' => 100,
        )));

        $this->assertSame('100.0000', $this->model->getTotalAuthorized());
    }

    public function test_get_total_authorized_should_include_authorizations()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::AUTHORIZE,
            'amount' => 100,
        )));

        $this->assertSame('100.0000', $this->model->getTotalAuthorized());
    }

    public function test_get_total_authorized_should_include_failed_transactions()
    {
        $this->model->save();
        $this->model->transactions()->save(Factory::build('transaction', array(
            'type' => Transaction::PURCHASE,
            'status' => Transaction::FAILED,
            'amount' => 100,
        )));

        $this->assertSame(0, $this->model->getTotalAuthorized());
    }

    public function test_to_tag_array_no_discount()
    {
        $attributes = $this->model->toTagArray();

        $this->assertNull($attributes['discount:id']);
        $this->assertNull($attributes['discount:name']);
        $this->assertNull($attributes['discount:start_date']);
        $this->assertNull($attributes['discount:end_date']);
        $this->assertNull($attributes['discount:free_shipping']);

        $this->assertNull($attributes['promo_code_desc']);
        $this->assertNull($attributes['promo_code_description']);
        $this->assertNull($attributes['promo_code_type']);
        $this->assertNull($attributes['promo_code_value']);
        $this->assertNull($attributes['promo_code_free_shipping']);
    }

    public function test_to_tag_array_with_discount()
    {
        $discount = Factory::build('discount', array(
            'name' => 'cheapcheap',
            'start_date' => 100,
            'end_date' => 200,
            'free_shipping' => true,
        ));
        $this->model->setRelation('discount', $discount);
        $this->model->discount_id = 5;

        $attributes = $this->model->toTagArray();

        $this->assertSame(5, $attributes['discount:id']);
        $this->assertSame('cheapcheap', $attributes['discount:name']);
        $this->assertSame(100, $attributes['discount:start_date']);
        $this->assertSame(200, $attributes['discount:end_date']);
        $this->assertTrue($attributes['discount:free_shipping']);

        $this->assertSame('cheapcheap', $attributes['promo_code_desc']);
        $this->assertSame('cheapcheap', $attributes['promo_code_description']);
        $this->assertTrue($attributes['promo_code_free_shipping']);
    }
}
