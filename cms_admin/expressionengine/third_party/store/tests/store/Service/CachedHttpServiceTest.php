<?php

namespace Store\Service;

use Store\Model\Cache;
use Store\TestCase;

class CachedHttpServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->service = new CachedHttpService($this->ee);
    }

    public function test_has_cache_plugin()
    {
        $listeners = $this->service->getEventDispatcher()->getListeners('request.sent');

        $this->assertInstanceOf('Guzzle\Plugin\Cache\CachePlugin', $listeners[0][0]);
    }
}
