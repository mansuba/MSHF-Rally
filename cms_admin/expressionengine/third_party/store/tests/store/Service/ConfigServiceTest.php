<?php

namespace Store\Service;

use Store\Model\Config;
use Store\TestCase;
use Store\Test\Factory;

class ConfigServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->service = new ConfigService($this->ee);
    }

    protected function add_config_item()
    {
        $this->ee->db->shouldReceive('table_exists')->andReturn(true);

        // create valid config item
        $config = Factory::create('config', array(
            'preference' => 'store_currency_symbol',
            'value' => '#',
        ));
    }

    public function test_settings()
    {
        $this->assertSame('$', $this->service->settings['store_currency_symbol']);
    }

    public function test_items_site_table_missing()
    {
        $this->ee->db->shouldReceive('table_exists')->once()->andReturn(false);

        $items = $this->service->items();

        $this->assertFalse($items['store_site_enabled']);
    }

    public function test_items_site_values_missing()
    {
        $this->ee->db->shouldReceive('table_exists')->once()->andReturn(true);

        $items = $this->service->items();

        $this->assertFalse($items['store_site_enabled']);
    }

    public function test_items_site_enabled()
    {
        $this->add_config_item();

        $items = $this->service->items();

        $this->assertTrue($items['store_site_enabled']);
        $this->assertSame('#', $items['store_currency_symbol']);
    }

    public function test_load_site_disabled()
    {
        $this->ee->db->shouldReceive('table_exists')->once()->andReturn(false);
        $this->ee->config->shouldReceive('set_item')->with('store_site_enabled', false)->once();

        $this->service->load();
    }

    public function test_load_site_enabled()
    {
        $this->add_config_item();

        $this->ee->config->shouldReceive('set_item')->with('store_site_enabled', true)->once();
        $this->ee->config->shouldReceive('set_item')->with('store_currency_symbol', '#')->once();
        $this->ee->config->shouldReceive('set_item');

        $this->service->load();
    }

    public function test_update()
    {
        $this->add_config_item();
        $this->ee->config->shouldReceive('set_item');

        $this->service->update(array('store_currency_symbol' => '&'));

        $item = Config::where('preference', 'store_currency_symbol')->first();
        $this->assertSame($item->value, '&');
    }

    public function test_order_fields()
    {
        $fields = $this->service->order_field_defaults();
        $this->assertArrayHasKey('billing_first_name', $fields);
    }

    public function test_order_field_defaults()
    {
        $fields = $this->service->order_field_defaults();
        $this->assertArrayHasKey('billing_first_name', $fields);
    }

    public function test_is_super_admin_true()
    {
        $this->ee->session->userdata['group_id'] = 1;
        $this->assertTrue($this->service->is_super_admin());
    }

    public function test_is_super_admin_false()
    {
        $this->ee->session->userdata['group_id'] = 5;
        $this->assertFalse($this->service->is_super_admin());
    }

    public function test_security()
    {
        $security = $this->service->security();

        $this->assertInternalType('array', $security['can_access_settings']);
        $this->assertInternalType('array', $security['can_add_payments']);
    }

    public function test_has_privilege()
    {
        $this->ee->session->userdata['group_id'] = 5;

        $this->assertFalse($this->service->has_privilege('can_access_settings'));
    }

    public function test_config_json()
    {
        $json = $this->service->config_json();

        $this->assertContains('"store_currency_symbol":', $json);
        $this->assertContains('"store_currency_decimals":', $json);
        $this->assertContains('"store_currency_thousands_sep":', $json);
        $this->assertContains('"store_currency_dec_point":', $json);
        $this->assertContains('"store_currency_suffix":', $json);
    }

    public function test_load_cp_assets()
    {
        $this->ee->cp->shouldReceive('add_to_head')->once();
        $this->ee->cp->shouldReceive('add_to_foot')->once();

        $this->service->load_cp_assets();
    }

    public function test_asset_url()
    {
        $url = $this->service->asset_url('store.js');

        $this->assertContains('/themes/third_party/store/store.js?v=', $url);
    }
}
