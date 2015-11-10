<?php

namespace Store;

use Store\TestCase;
use Store\Test\Factory;

class FormValidationTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->validation = new FormValidation;
    }

    public function test_valid_payment_method()
    {
        Factory::create('paymentMethod', array('class' => 'Dummy'));

        $this->assertTrue($this->validation->valid_payment_method('dummy'));
    }

    public function test_valid_payment_method_false()
    {
        $this->assertFalse($this->validation->valid_payment_method('invalid'));
    }
}
