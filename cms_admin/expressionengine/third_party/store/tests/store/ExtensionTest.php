<?php

namespace Store;

use Store\TestCase;

class ExtensionTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->extension = new Extension($this->ee);
    }

    public function test_settings_form()
    {
        $this->ee->functions->shouldReceive('redirect')
            ->with('addons_modules/show_module_cp?module=store&amp;sc=settings')
            ->once();

        $this->extension->settings_form();
    }
}
