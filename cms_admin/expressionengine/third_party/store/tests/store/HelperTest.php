<?php

use Mockery as m;
use Store\TestCase;

class HelperTest extends TestCase
{
    public function test_store_cp_url_basic()
    {
        $this->assertSame('addons_modules/show_module_cp?module=store', store_cp_url());
        $this->assertSame('addons_modules/show_module_cp?module=store&amp;sc=orders', store_cp_url('orders'));
        $this->assertSame('addons_modules/show_module_cp?module=store&amp;sc=orders&amp;sm=show', store_cp_url('orders', 'show'));
    }

    public function test_store_cp_url_params()
    {
        $params = array('foo>' => '&bar');

        $this->assertSame('addons_modules/show_module_cp?module=store&amp;sc=orders&amp;foo%3E=%26bar', store_cp_url('orders', $params));
        $this->assertSame('addons_modules/show_module_cp?module=store&amp;sc=orders&amp;foo%3E=%26bar', store_cp_url('orders', null, $params));
        $this->assertSame('addons_modules/show_module_cp?module=store&amp;sc=orders&amp;sm=show&amp;foo%3E=%26bar', store_cp_url('orders', 'show', $params));
    }

    public function test_store_lang_exists()
    {
        ee()->lang->language = array('foo' => 'bar');

        $this->assertTrue(store_lang_exists('foo'));
        $this->assertFalse(store_lang_exists('bar'));
    }

    public function test_store_form_returns_form_builder()
    {
        $model = m::mock('Store\Model\Order');
        $result = store_form($model, 'custom-prefix');

        $this->assertInstanceOf('Store\FormBuilder', $result);
        $this->assertSame($model, $result->model);
        $this->assertSame('custom-prefix', $result->prefix);
    }

    public function test_store_setting_default()
    {
        $this->assertSame('foo', store_setting_default('foo'));
        $this->assertSame('foo', store_setting_default(array('default' => 'foo')));
        $this->assertNull(store_setting_default(array('type' => 'select')));
    }

    public function test_store_select_options_none_selected()
    {
        $options = array(
            'green' => 'Green Shirt',
            'blue' => 'Blue Shirt'
        );

        $html = store_select_options($options);

        $this->assertSame("<option value=\"green\">Green Shirt</option>\n<option value=\"blue\">Blue Shirt</option>", $html);
    }

    public function test_store_select_options_html_entities()
    {
        $options = array('me&you' => 'Big > "Small"');

        $html = store_select_options($options);

        $this->assertSame("<option value=\"me&amp;you\">Big &gt; &quot;Small&quot;</option>", $html);
    }

    public function test_store_select_options_with_selected()
    {
        $options = array(
            'green' => 'Green Shirt',
            'blue' => 'Blue Shirt'
        );

        $html = store_select_options($options, 'blue');

        $this->assertSame("<option value=\"green\">Green Shirt</option>\n<option value=\"blue\" selected>Blue Shirt</option>", $html);
    }

    public function test_store_email_template_name()
    {
        $this->assertSame('Order Confirmation', store_email_template_name('order_confirmation'));
        $this->assertSame('Something Else', store_email_template_name('Something Else'));
    }

    public function test_store_html_elem()
    {
        $html = store_html_elem('div', array('class' => 'red', 'selected' => true, 'disabled' => false), 'some text');

        $this->assertSame('<div class="red" selected>some text</div>', $html);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid tag name: di v
     */
    public function test_store_html_elem_invalid_tag_name()
    {
        store_html_elem('di v', array('class' => 'red'), 'some text');
    }

    public function test_store_html_elem_escaping()
    {
        $html = store_html_elem('div', array('class"' => '&red'), 'some >_< text');

        $this->assertSame('<div class&quot;="&amp;red">some &gt;_&lt; text</div>', $html);
    }

    public function test_store_html_elem_safe_content()
    {
        $html = store_html_elem('div', array('class"' => '&red'), 'some >_< text', true);

        $this->assertSame('<div class&quot;="&amp;red">some >_< text</div>', $html);
    }

    public function test_store_html_elem_void()
    {
        $html = store_html_elem('input', array('class' => 'red'), 'should be ignored');

        $this->assertSame('<input class="red" />', $html);
    }
}
