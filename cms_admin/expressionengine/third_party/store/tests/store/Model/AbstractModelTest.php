<?php

namespace Store\Model;

use Mockery as m;
use Store\TestCase;

class AbstractModelTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->model = m::mock('Store\Model\AbstractModel')->makePartial();
    }

    public function test_set_boolean_attribute_y()
    {
        $this->model->setBooleanAttribute('foo', 'y');
        $this->assertSame(1, $this->model->getAttribute('foo'));
    }

    public function test_set_boolean_attribute_n()
    {
        $this->model->setBooleanAttribute('foo', 'n');
        $this->assertSame(0, $this->model->getAttribute('foo'));
    }

    public function test_set_boolean_attribute_false()
    {
        $this->model->setBooleanAttribute('foo', false);
        $this->assertSame(0, $this->model->getAttribute('foo'));
    }

    public function test_get_unix_time_attribute_empty()
    {
        $this->model->setAttribute('foo', 0);
        $this->assertNull($this->model->getUnixTimeAttribute('foo'));
    }

    public function test_get_unix_time_attribute_value()
    {
        $this->model->setAttribute('foo', 1373462205);
        ee()->localize->shouldReceive('human_time')->once()->andReturn('formatted date');
        $this->assertSame('formatted date', $this->model->getUnixTimeAttribute('foo'));
    }

    public function test_set_unix_time_attribute_empty()
    {
        $this->model->setUnixTimeAttribute('foo', '');
        $this->assertNull($this->model->getAttribute('foo'));
    }

    public function test_set_unix_time_attribute_value()
    {
        ee()->localize->shouldReceive('string_to_timestamp')->once()->andReturn('unix timestamp');
        $this->model->setUnixTimeAttribute('foo', 'January 3, 2012');
        $this->assertSame('unix timestamp', $this->model->getAttribute('foo'));
    }

    public function test_get_pipe_array_attribute_empty()
    {
        $this->model->setAttribute('foo', '');
        $this->assertSame(array(), $this->model->getPipeArrayAttribute('foo'));
    }

    public function test_get_pipe_array_attribute_values()
    {
        $this->model->setAttribute('foo', '3|4|5');
        $this->assertSame(array('3', '4', '5'), $this->model->getPipeArrayAttribute('foo'));
    }

    public function test_set_pipe_array_attribute_empty()
    {
        $this->model->setPipeArrayAttribute('foo', array());
        $this->assertNull($this->model->getAttribute('foo'));
    }

    public function test_set_pipe_array_attribute_string()
    {
        $this->model->setPipeArrayAttribute('foo', 'already|formatted');
        $this->assertSame('already|formatted', $this->model->getAttribute('foo'));
    }

    public function test_set_pipe_array_attribute_values()
    {
        $this->model->setPipeArrayAttribute('foo', array('red', 'green', 'blue'));
        $this->assertSame('red|green|blue', $this->model->getAttribute('foo'));
    }

    public function test_set_pipe_array_attribute_ignores_empty_values()
    {
        $this->model->setPipeArrayAttribute('foo', array('red', '', 0, '0', 'green', null));
        $this->assertSame('red|0|0|green', $this->model->getAttribute('foo'));
    }
}
