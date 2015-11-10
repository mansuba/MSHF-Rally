<?php

namespace Store\Model;

use Store\Model\State;
use Store\TestCase;
use Store\Test\Factory;

class StateTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = Factory::build('state');
    }

    public function test_fake_delete_attribute()
    {
        $this->model->fill(array('delete' => true));

        $this->assertTrue($this->model->delete);

        $this->model->delete = false;

        $this->assertFalse($this->model->delete);
    }
}
