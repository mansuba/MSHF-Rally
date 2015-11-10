<?php

namespace Store\Model;

use Mockery as m;
use Store\TestCase;
use Store\Test\Factory;

class CollectionTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->item = m::mock('Store\Model\Order');
    }

    public function test_to_tag_array()
    {
        $collection = new Collection(array($this->item));
        $this->item->shouldReceive('toTagArray')->once()->andReturn(array('foo' => 'bar'));

        $attrs = $collection->toTagArray();

        // should not set count or total_results params
        $this->assertCount(1, $attrs);
        $this->assertSame(array(array('foo' => 'bar')), $attrs);
    }

    public function test_to_tag_array_with_prefix()
    {
        $collection = new Collection(array(
            Factory::build('product'),
            Factory::build('product'),
            Factory::build('product'),
        ));

        $attrs = $collection->toTagArray('foo');

        $this->assertCount(3, $attrs);

        $this->assertSame(1, $attrs[0]['foo:count']);
        $this->assertSame(3, $attrs[0]['foo:total_results']);

        $this->assertSame(2, $attrs[1]['foo:count']);
        $this->assertSame(3, $attrs[1]['foo:total_results']);

        $this->assertSame(3, $attrs[2]['foo:count']);
        $this->assertSame(3, $attrs[2]['foo:total_results']);
    }
}
