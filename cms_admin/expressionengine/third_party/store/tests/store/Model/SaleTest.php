<?php

namespace Store\Model;

use Store\TestCase;

class SaleTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = new Sale;
    }

    public function test_member_group_ids_attribute()
    {
        $this->model->member_group_ids = array(3, 4, 5);

        $attributes = $this->model->getAttributes();
        $this->assertSame('3|4|5', $attributes['member_group_ids']);
        $this->assertSame(array('3', '4', '5'), $this->model->member_group_ids);
    }

    public function test_entry_ids_attribute()
    {
        $this->model->entry_ids = array(3, 4, 5);

        $attributes = $this->model->getAttributes();
        $this->assertSame('3|4|5', $attributes['entry_ids']);
        $this->assertSame(array('3', '4', '5'), $this->model->entry_ids);
    }

    public function test_category_ids_attribute()
    {
        $this->model->category_ids = array(3, 4, 5);

        $attributes = $this->model->getAttributes();
        $this->assertSame('3|4|5', $attributes['category_ids']);
        $this->assertSame(array('3', '4', '5'), $this->model->category_ids);
    }
}
