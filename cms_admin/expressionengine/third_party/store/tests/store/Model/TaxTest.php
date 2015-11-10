<?php

namespace Store\Model;

use Store\TestCase;

class TaxTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = new Tax;
    }

    public function test_get_rate_percent()
    {
        $this->model->rate = 0.125;

        $this->assertSame('12.5%', $this->model->rate_percent);
    }

    public function test_set_rate_percent()
    {
        $this->model->rate_percent = '12.5%';

        $this->assertSame(0.125, $this->model->rate);
    }
}
