<?php

namespace Store\Guzzle;

use Mockery as m;
use Store\TestCase;
use Guzzle\Http\Message\RequestFactory;

class CachedHttpServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->store = m::mock('Guzzle\Cache\CacheAdapterInterface');
        $this->storage = new CacheStorage($this->store);
        $this->requestFactory = new RequestFactory;
    }

    public function test_construct_should_load_default_cache()
    {
        $storage = new CacheStorage;
        $this->assertInstanceOf('Store\Service\CacheService', $storage->getCache());
    }

    public function test_get_cache_key_matches_for_same_request()
    {
        $key1 = $this->storage->getCacheKey($this->requestFactory->create('POST', 'http://www.example.com/foo', array('X-Custom' => 'True'), 'message body'));
        $key2 = $this->storage->getCacheKey($this->requestFactory->create('POST', 'http://www.example.com/foo', array('X-Custom' => 'True'), 'message body'));

        $this->assertStringStartsWith('guzzle/', $key1);
        $this->assertEquals($key1, $key2);
    }

    public function test_get_cache_key_different_method()
    {
        $key1 = $this->storage->getCacheKey($this->requestFactory->create('GET', 'http://www.example.com/foo'));
        $key2 = $this->storage->getCacheKey($this->requestFactory->create('POST', 'http://www.example.com/foo'));

        $this->assertStringStartsWith('guzzle/', $key1);
        $this->assertNotEquals($key1, $key2);
    }

    public function test_get_cache_key_different_url()
    {
        $key1 = $this->storage->getCacheKey($this->requestFactory->create('POST', 'http://www.example.com/foo', array('X-Custom' => 'True'), 'message body'));
        $key2 = $this->storage->getCacheKey($this->requestFactory->create('POST', 'http://www.example.com/foo/bar', array('X-Custom' => 'True'), 'message body'));

        $this->assertStringStartsWith('guzzle/', $key1);
        $this->assertNotEquals($key1, $key2);
    }

    public function test_get_cache_key_different_body()
    {
        $key1 = $this->storage->getCacheKey($this->requestFactory->create('POST', 'http://www.example.com/foo', array('X-Custom' => 'True'), 'message body'));
        $key2 = $this->storage->getCacheKey($this->requestFactory->create('POST', 'http://www.example.com/foo', array('X-Custom' => 'True'), 'something else'));

        $this->assertStringStartsWith('guzzle/', $key1);
        $this->assertNotEquals($key1, $key2);
    }
}
