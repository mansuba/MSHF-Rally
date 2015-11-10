<?php

namespace Store\Service;

use Mockery as m;
use Store\Model\Product;
use Store\TestCase;
use Store\Test\Factory;

class ProductsServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->service = new ProductsService($this->ee);
        $this->product = Factory::build('product');
    }

    public function test_delete_all()
    {
        $this->product->save();
        $this->assertSame(1, Product::count());

        $this->service->delete_all($this->product->entry_id);
        $this->assertSame(0, Product::count());
    }

    public function test_delete_all_array()
    {
        $this->product->save();
        $this->assertSame(1, Product::count());

        $this->service->delete_all(array(123, $this->product->entry_id));
        $this->assertSame(0, Product::count());
    }

    public function test_apply_sales_no_sales()
    {
        $this->ee->session->userdata = array('group_id' => 0);
        $this->product->price = 100.0;

        $this->service->apply_sales($this->product);

        $this->assertSame(100.0, $this->product->price);
        $this->assertFalse($this->product->on_sale);
    }

    public function test_apply_sales_sets_option_sale_prices()
    {
        $this->ee->session->userdata = array('group_id' => 0);
        $this->product->setRelation('modifiers', array(
            Factory::build('productModifier')
        ));
        $this->product->modifiers[0]->setRelation('options', array(
            Factory::build('productOption', array('opt_price_mod' => 5.0))
        ));

        $this->assertNull($this->product->modifiers[0]->options[0]->sale_price_mod);

        $this->service->apply_sales($this->product);
        $this->assertSame(5.0, $this->product->modifiers[0]->options[0]->sale_price_mod);
    }

    public function test_apply_sales_with_per_item_discount()
    {
        $this->ee->session->userdata = array('group_id' => 0);
        $this->product->price = 100.0;

        Factory::create('sale', array('per_item_discount' => 5.0));

        $this->service->apply_sales($this->product);

        $this->assertSame(95.0, $this->product->price);
        $this->assertSame(100.0, $this->product->regular_price);
        $this->assertTrue($this->product->on_sale);
    }

    public function test_apply_sales_with_percent_discount()
    {
        $this->ee->session->userdata = array('group_id' => 0);
        $this->product->price = 50.0;

        Factory::create('sale', array('percent_discount' => 10.0));

        $this->service->apply_sales($this->product);

        $this->assertSame(45.0, $this->product->price);
        $this->assertSame(50.0, $this->product->regular_price);
        $this->assertTrue($this->product->on_sale);
    }

    public function test_apply_sales_with_percent_discount_modifiers()
    {
        $this->ee->session->userdata = array('group_id' => 0);
        $this->product->price = 50.0;
        $this->product->setRelation('modifiers', array(
            Factory::build('productModifier')
        ));
        $this->product->modifiers[0]->setRelation('options', array(
            Factory::build('productOption', array('opt_price_mod' => 10.0))
        ));

        Factory::create('sale', array('percent_discount' => 10.0));

        $this->service->apply_sales($this->product);

        $this->assertSame(9.0, $this->product->modifiers[0]->options[0]->sale_price_mod);
        $this->assertSame(10.0, $this->product->modifiers[0]->options[0]->opt_price_mod);
    }

    public function test_sale_applies_to_product_no_restrictions()
    {
        $sale = Factory::build('sale');

        $this->assertTrue($this->service->sale_applies_to_product($sale, $this->product, -1));
    }

    public function test_sale_applies_to_product_with_member_group()
    {
        $sale = Factory::build('sale', array('member_group_ids' => array(1, 5)));

        $this->assertTrue($this->service->sale_applies_to_product($sale, $this->product, 5));
        $this->assertFalse($this->service->sale_applies_to_product($sale, $this->product, 3));
    }

    public function test_sale_applies_to_product_with_entry_id()
    {
        $sale = Factory::build('sale', array('entry_ids' => array(1, 5)));

        $this->product->entry_id = 5;
        $this->assertTrue($this->service->sale_applies_to_product($sale, $this->product, -1));

        $this->product->entry_id = 3;
        $this->assertFalse($this->service->sale_applies_to_product($sale, $this->product, -1));
    }

    public function test_sale_applies_to_product_with_category_id_match()
    {
        $sale = Factory::build('sale', array('category_ids' => array(1, 5)));
        $this->product->save();
        $this->ee->store->db->table('category_posts')->insert(array(
            array('entry_id' => $this->product->entry_id, 'cat_id' => 5),
            array('entry_id' => $this->product->entry_id, 'cat_id' => 6),
        ));

        $this->assertTrue($this->service->sale_applies_to_product($sale, $this->product, -1));
    }

    public function test_sale_applies_to_product_with_category_id_no_match()
    {
        $sale = Factory::build('sale', array('category_ids' => array(1, 5)));
        $this->product->save();
        $this->ee->store->db->table('category_posts')->insert(array(
            array('entry_id' => $this->product->entry_id, 'cat_id' => 3),
            array('entry_id' => $this->product->entry_id, 'cat_id' => 6),
        ));

        $this->assertFalse($this->service->sale_applies_to_product($sale, $this->product, -1));
    }
}
