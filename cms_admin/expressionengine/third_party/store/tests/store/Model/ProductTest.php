<?php

namespace Store\Model;

use Store\TestCase;
use Store\Test\Factory;

class ProductTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = Factory::build('product');
    }

    public function test_total_stock()
    {
        // should be 0 by default
        $this->model->setRelation('stock', array());
        $this->assertSame(0, $this->model->total_stock);

        // should sum stock level of all stock items
        $this->model->setRelation('stock', array(
            Factory::build('stock', array('stock_level' => 1)),
            Factory::build('stock', array('stock_level' => 3)),
        ));
        $this->assertSame(4, $this->model->total_stock);
    }

    public function test_track_stock()
    {
        // should be false by default
        $this->model->setRelation('stock', array());
        $this->assertFalse($this->model->track_stock);

        // should be true if any stock items have stock tracking enabled
        $this->model->setRelation('stock', array(
            Factory::build('stock', array('track_stock' => false)),
            Factory::build('stock', array('track_stock' => true)),
            Factory::build('stock', array('track_stock' => false)),
        ));
        $this->assertTrue($this->model->track_stock);
    }

    public function test_get_price()
    {
        // should default to regular price if sale price not set
        $this->model->setRawAttributes(array('price' => '123'));
        $this->assertSame('123', $this->model->price);

        // should return sale price if set
        $this->model->setSalePriceAttribute('999');
        $this->assertSame('999', $this->model->price);
    }

    public function test_set_price()
    {
        $this->model->price = '321';

        // should set both regular price and sale price
        $this->assertSame('321', $this->model->price);
        $this->assertSame('321', $this->model->regular_price);
        $this->assertSame('321', $this->model->sale_price);
    }

    public function test_regular_price()
    {
        // should default to null if not set
        $this->model->setRawAttributes(array());
        $this->assertNull($this->model->regular_price);

        // should return price if available
        $this->model->setRawAttributes(array('price' => '123'));
        $this->assertSame('123', $this->model->regular_price);
    }

    public function test_sale_price()
    {
        // should default to regular price if not set
        $this->model->setRawAttributes(array('price' => '123'));
        $this->assertSame('123', $this->model->sale_price);

        // should display sale price if set
        $this->model->setSalePriceAttribute('999');
        $this->assertSame('999', $this->model->sale_price);
    }

    public function test_on_sale()
    {
        // should be false if price equals sale price
        $this->model->price = 100;
        $this->model->sale_price = 100;
        $this->assertFalse($this->model->on_sale);

        // should be false if price is lower than sale price
        $this->model->price = 100;
        $this->model->sale_price = 200;
        $this->assertFalse($this->model->on_sale);

        // should be true if price is greater than sale price
        $this->model->price = 200;
        $this->model->sale_price = 100;
        $this->assertTrue($this->model->on_sale);
    }

    public function test_you_save()
    {
        $this->model->price = '80.0000';
        $this->model->sale_price = '60.5000';

        $this->assertSame(19.5, $this->model->you_save);
    }

    public function test_you_save_percent()
    {
        $this->model->price = '80.0000';
        $this->model->sale_price = '60.5000';

        $this->assertSame(24, $this->model->you_save_percent);
    }

    public function test_you_save_percent_zero()
    {
        $this->model->price = '0.0000';
        $this->model->sale_price = '60.5000';

        $this->assertSame(0, $this->model->you_save_percent);
    }

    public function test_to_tag_array_modifiers()
    {
        $this->model->save();
        $this->model->modifiers()->save(Factory::build('productModifier'));
        $this->model->modifiers; // load modifiers
        $attributes = $this->model->toTagArray();

        $this->assertSame(1, count($attributes['modifiers']));
        $this->assertFalse($attributes['no_modifiers']);
    }

    public function test_to_tag_array_no_modifiers()
    {
        $attributes = $this->model->toTagArray();

        $this->assertArrayNotHasKey('modifiers', $attributes);
        $this->assertTrue($attributes['no_modifiers']);
    }
}
