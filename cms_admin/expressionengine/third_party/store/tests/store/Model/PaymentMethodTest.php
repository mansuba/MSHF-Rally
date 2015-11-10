<?php

namespace Store\Model;

use Store\TestCase;

class PaymentMethodTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = new PaymentMethod;
    }

    public function test_set_settings()
    {
        $this->model->settings = array('foo' => 42);

        // should json encode settings
        $attributes = $this->model->getAttributes();
        $this->assertSame('{"foo":42}', $attributes['settings']);
    }

    public function test_get_setttings()
    {
        $this->model->setRawAttributes(array('settings' => '{"foo":42}'));

        // should json decode settings
        $this->assertSame(array('foo' => 42), $this->model->settings);
    }

    public function test_get_settings_null()
    {
        $this->model->setRawAttributes(array('settings' => null));

        // should default to empty array so omnipay doesn't freak out
        $this->assertSame(array(), $this->model->settings);
    }
}
