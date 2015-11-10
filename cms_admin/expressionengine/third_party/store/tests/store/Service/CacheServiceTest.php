<?php

namespace Store\Service;

use Store\Model\Cache;
use Store\TestCase;
use Store\Test\Factory;

class CacheServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->cache = new CacheService($this->ee);
    }

    public function test_contains()
    {
        ee()->cache->shouldReceive('get')->with('/store/example')->once()->andReturn('value');
        $this->assertTrue($this->cache->contains('example'));
    }

    public function test_delete()
    {
        ee()->cache->shouldReceive('delete')->with('/store/example')->once()->andReturn(true);
        $this->assertTrue($this->cache->delete('example'));
    }

    public function test_fetch()
    {
        ee()->cache->shouldReceive('get')->with('/store/example')->once()->andReturn('value');
        $this->assertSame('value', $this->cache->fetch('example'));
    }

    public function test_save()
    {
        ee()->cache->shouldReceive('save')->with('/store/example', 'value', 3600)->once()->andReturn(true);
        $this->assertTrue($this->cache->save('example', 'value'));
    }
}
