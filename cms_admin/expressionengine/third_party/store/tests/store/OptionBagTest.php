<?php

namespace Store;

use Store\TestCase;

class OptionBagTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->options = array(
            'simple' => 'foo',
            'advanced' => array('type' => 'text', 'default' => 'bar'),
        );
        $this->bag = new OptionBag($this->options);
    }

    public function test_all()
    {
        $expected = array('simple' => 'foo', 'advanced' => 'bar');
        $this->assertSame($expected, $this->bag->all());
    }

    public function test_keys()
    {
        $this->assertSame(array('simple', 'advanced'), $this->bag->keys());
    }

    public function test_replace()
    {
        $this->bag->replace(array('simple' => 'hi'));
        $expected = array('simple' => 'hi', 'advanced' => 'bar');

        $this->assertSame($expected, $this->bag->all());
    }

    public function test_get()
    {
        $this->assertSame('foo', $this->bag->get('simple'));
    }

    public function test_get_unknown_key()
    {
        $this->assertNull($this->bag->get('unknown'));
    }

    public function test_set()
    {
        $this->bag->set('simple', 'yo');
        $this->assertSame('yo', $this->bag->get('simple'));
    }

    public function test_set_unknown_key()
    {
        $this->bag->set('unknown', 'yo');
        $this->assertNull($this->bag->get('unknown'));
    }

    public function has()
    {
        $this->assertTrue($this->bag->has('simple'));
        $this->assertFalse($this->bag->has('strange'));
    }

    public function test_def_simple()
    {
        $bag = new OptionBag(array('foo' => 'bar'));
        $this->assertSame('bar', $bag->def('foo'));
    }

    public function test_def_advaned()
    {
        $bag = new OptionBag(array('foo' => array('type' => 'text', 'default' => 'bar')));
        $this->assertSame('bar', $bag->def('foo'));
    }

    public function test_def_implicit()
    {
        $bag = new OptionBag(array('foo' => array('type' => 'text')));
        $this->assertNull($bag->def('foo'));
    }

    public function test_def_unknown()
    {
        $bag = new OptionBag(array());
        $this->assertNull($bag->def('unknown'));
    }

    public function test_offset_exists()
    {
        $this->assertTrue(isset($this->bag['simple']));
        $this->assertFalse(isset($this->bag['unknown']));
    }

    public function test_offset_get()
    {
        $this->assertSame('foo', $this->bag['simple']);
    }

    public function test_offset_set()
    {
        $this->bag['simple'] = 'hey';
        $this->assertSame('hey', $this->bag['simple']);
    }

    public function test_offset_unset()
    {
        unset($this->bag['simple']);
        $this->assertNull($this->bag['simple']);
    }
}
