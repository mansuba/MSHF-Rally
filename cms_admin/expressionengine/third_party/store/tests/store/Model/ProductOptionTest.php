<?php

namespace Store\Model;

use Store\TestCase;
use Store\Test\Factory;

class ProductOptionTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = Factory::build('productOption');
        $this->model->modifier = Factory::build('productModifier');
        $this->model->modifier->product = Factory::build('product');
    }

    public function test_sale_price_mod()
    {
        $this->model->sale_price_mod = 99;
        $this->assertSame(99, $this->model->sale_price_mod);
        $this->assertArrayNotHasKey('sale_price_mod', $this->model->getAttributes());
    }

    public function test_to_array_regular_price()
    {
        $this->model->opt_name = 'Green';
        $this->model->opt_price_mod = 10.0;

        $attributes = $this->model->toArray();

        $this->assertSame('Green', $attributes['opt_name']);
        $this->assertSame(10.0, $attributes['opt_price_mod']);
        $this->assertSame(10.0, $attributes['regular_price_mod']);
        $this->assertSame(10.0, $attributes['sale_price_mod']);
        $this->assertArrayNotHasKey('modifier', $attributes);
    }

    public function test_to_array_sale_price()
    {
        $this->model->opt_price_mod = 10.0;
        $this->model->sale_price_mod = 5.0;

        $attributes = $this->model->toArray();

        $this->assertSame(5.0, $attributes['opt_price_mod']);
        $this->assertSame(5.0, $attributes['sale_price_mod']);
        $this->assertSame(10.0, $attributes['regular_price_mod']);
    }

    public function test_to_tag_array_no_stock_options()
    {
        // with no stock options, all option attributes should be false
        $attributes = $this->model->toTagArray();
        $this->assertFalse($attributes['option_sku']);
        $this->assertFalse($attributes['option_track_stock']);
        $this->assertFalse($attributes['option_stock_level']);
        $this->assertFalse($attributes['option_min_order_qty']);
    }

    public function test_to_tag_array_one_stock_option()
    {
        // should display attributes if only one stock option present
        $option = Factory::build('stockOption', array('sku' => 'some-sku'));
        $option->stock = Factory::build('stock', array(
            'track_stock' => 'ts-val',
            'stock_level' => 'sl-val',
            'min_order_qty' => 'moq-val',
        ));
        $this->model->setRelation('stock_options', array($option));

        $attributes = $this->model->toTagArray();
        $this->assertSame('some-sku', $attributes['option_sku']);
        $this->assertSame('ts-val', $attributes['option_track_stock']);
        $this->assertSame('sl-val', $attributes['option_stock_level']);
        $this->assertSame('moq-val', $attributes['option_min_order_qty']);
    }

    public function test_to_tag_array_many_stock_option()
    {
        $this->model->setRelation('stock_options', array(
            Factory::build('stockOption'),
            Factory::build('stockOption'),
        ));

        // with many stock options, all option attributes should be false
        $attributes = $this->model->toTagArray();
        $this->assertFalse($attributes['option_sku']);
        $this->assertFalse($attributes['option_track_stock']);
        $this->assertFalse($attributes['option_stock_level']);
        $this->assertFalse($attributes['option_min_order_qty']);
    }

    public function test_to_tag_array_regular_price()
    {
        $this->model->product_opt_id = 5;
        $this->model->opt_name = 'Green';
        $this->model->opt_price_mod = 10.0;
        $this->model->modifier->product->price = 100.0;

        $attributes = $this->model->toTagArray();

        $this->assertSame(5, $attributes['option_id']);
        $this->assertSame('Green', $attributes['option_name']);

        $this->assertSame('$10.00', $attributes['price_mod']);
        $this->assertSame(10.0, $attributes['price_mod_val']);
        $this->assertSame('$10.00', $attributes['regular_price_mod']);
        $this->assertSame(10.0, $attributes['regular_price_mod_val']);
        $this->assertSame('$10.00', $attributes['sale_price_mod']);
        $this->assertSame(10.0, $attributes['sale_price_mod_val']);

        $this->assertSame('$110.00', $attributes['price_inc_mod']);
        $this->assertSame(110.0, $attributes['price_inc_mod_val']);
        $this->assertSame('$110.00', $attributes['regular_price_inc_mod']);
        $this->assertSame(110.0, $attributes['regular_price_inc_mod_val']);
        $this->assertSame('$110.00', $attributes['sale_price_inc_mod']);
        $this->assertSame(110.0, $attributes['sale_price_inc_mod_val']);
    }

    public function test_to_tag_array_sale_price()
    {
        $this->model->product_opt_id = 5;
        $this->model->opt_name = 'Green';
        $this->model->opt_price_mod = 10.0;
        $this->model->sale_price_mod = 5.0;
        $this->model->modifier->product->price = 100.0;
        $this->model->modifier->product->sale_price = 80.0;

        $attributes = $this->model->toTagArray();

        $this->assertSame(5, $attributes['option_id']);
        $this->assertSame('Green', $attributes['option_name']);

        $this->assertSame('$5.00', $attributes['price_mod']);
        $this->assertSame(5.0, $attributes['price_mod_val']);
        $this->assertSame('$10.00', $attributes['regular_price_mod']);
        $this->assertSame(10.0, $attributes['regular_price_mod_val']);
        $this->assertSame('$5.00', $attributes['sale_price_mod']);
        $this->assertSame(5.0, $attributes['sale_price_mod_val']);

        $this->assertSame('$85.00', $attributes['price_inc_mod']);
        $this->assertSame(85.0, $attributes['price_inc_mod_val']);
        $this->assertSame('$110.00', $attributes['regular_price_inc_mod']);
        $this->assertSame(110.0, $attributes['regular_price_inc_mod_val']);
        $this->assertSame('$85.00', $attributes['sale_price_inc_mod']);
        $this->assertSame(85.0, $attributes['sale_price_inc_mod_val']);
    }
}
