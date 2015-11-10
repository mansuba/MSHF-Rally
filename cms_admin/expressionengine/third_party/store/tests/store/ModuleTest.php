<?php

namespace Store;

use Store\TestCase;

class ModuleTest extends TestCase
{
    public function test_construct()
    {
        // let's mess with the global state
        $_POST = array('commit' => 'foo');

        $module = new Module;

        $this->assertSame('foo', $_POST['submit']);

        // clean up
        $_POST = array();
    }
}
