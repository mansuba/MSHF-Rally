<?php

namespace Store;

use Store\TestCase;

class ContainerTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->container = new Container($this->ee);
    }

    public function test_magic_get()
    {
        // should dynamically create service instances
        $this->assertFalse(isset($this->container->orders));
        $this->assertInstanceOf('Store\Service\OrdersService', $this->container->orders);
        $this->assertTrue(isset($this->container->orders));
    }
}
