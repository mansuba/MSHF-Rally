<?php

namespace Store\Model;

use Store\TestCase;
use Store\Test\Factory;

class StockTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = Factory::build('stock');
    }

    public function test_min_order_qty()
    {
        $this->model->min_order_qty = 5;
        $this->assertSame(5, $this->model->min_order_qty);

        $this->model->min_order_qty = '';
        $this->assertNull($this->model->min_order_qty);
    }

    public function test_stock_level()
    {
        $this->model->stock_level = 5;
        $this->assertSame(5, $this->model->stock_level);

        $this->model->stock_level = '';
        $this->assertNull($this->model->stock_level);
    }

    public function test_opt_values()
    {
        // should default to empty array
        $this->assertSame(array(), $this->model->opt_values);

        // should map mod_ids to opt_ids
        $this->model->setRelation('stock_options', array(
            Factory::build('stockOption', array('product_mod_id' => 10, 'product_opt_id' => 20)),
            Factory::build('stockOption', array('product_mod_id' => 11, 'product_opt_id' => 21)),
        ));

        $this->assertSame(array(10 => 20, 11 => 21), $this->model->opt_values);
    }

    public function test_find_by_modifiers()
    {
        $this->model->save();

        $option = Factory::build('stockOption', array('product_mod_id' => 10, 'product_opt_id' => 20));
        $this->model->stockOptions()->save($option);

        // should find stock where options match
        $this->assertNotEmpty(Stock::findByModifiers($this->model->entry_id, array(10 => 20)));

        // should find stock when passed extra options
        $this->assertNotEmpty(Stock::findByModifiers($this->model->entry_id, array(10 => 20, 11 => 21)));

        // should not find stock where no options match
        $this->assertEmpty(Stock::findByModifiers($this->model->entry_id, array(10 => 21)));
    }

    public function test_to_array()
    {
        $attributes = $this->model->toArray();

        // should have opt_values attribute for store.js
        $this->assertArrayHasKey('opt_values', $attributes);
    }
}
