<?php

namespace Store;

use Mockery as m;
use Store\TestCase;
use Store\Test\Factory;

class FormBuilderTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->model = Factory::build('order');
        $this->form = new FormBuilder($this->model);
    }

    public function test_construct()
    {
        $form = new FormBuilder;

        $this->assertNull($form->model);
        $this->assertNull($form->prefix);
    }

    public function test_construct_model()
    {
        $order = Factory::build('order');
        $form = new FormBuilder($order);

        $this->assertSame($order, $form->model);
        $this->assertSame('order', $form->prefix);
    }

    public function test_construct_model_prefix()
    {
        $order = Factory::build('order');
        $form = new FormBuilder($order, 'custom_prefix');

        $this->assertSame($order, $form->model);
        $this->assertSame('custom_prefix', $form->prefix);
    }

    public function test_open()
    {
        $out = $this->form->open(array('action' => 'foo'));

        $this->assertStringStartsWith('<form action="foo" method="post">', $out);
    }

    public function test_open_with_default_action()
    {
        $this->ee->store->request = m::mock($this->ee->store->request);
        $this->ee->store->request->shouldReceive('getRequestUri')->once()->andReturn('currentUri');

        $out = $this->form->open();

        $this->assertStringStartsWith('<form action="currentUri"', $out);
    }

    public function test_close()
    {
        $out = $this->form->close();

        $this->assertSame('</form>', $out);
    }

    public function test_label_with_prefix_string()
    {
        ee()->lang->language['store.model_foo'] = 'Label Text';
        $this->form->prefix = 'model';
        $out = $this->form->label('foo');

        $this->assertSame('<label for="model_foo">Label Text</label>', $out);
    }

    public function test_label_with_unprefixed_string()
    {
        ee()->lang->language['store.foo'] = 'Foo Text';
        $this->form->prefix = 'model';
        $out = $this->form->label('foo');

        $this->assertSame('<label for="model_foo">Foo Text</label>', $out);
    }

    public function test_label_with_custom_name()
    {
        ee()->lang->language['custom_name'] = 'Custom Text';
        $this->form->prefix = 'model';
        $out = $this->form->label('foo', 'custom_name');

        $this->assertSame('<label for="model_foo">Custom Text</label>', $out);
    }

    public function test_label_with_subtext()
    {
        ee()->lang->language['store.model_foo'] = 'Label Text';
        ee()->lang->language['store.model_foo_subtext'] = 'Subtext';
        $this->form->prefix = 'model';
        $out = $this->form->label('foo');

        $this->assertSame('<label for="model_foo">Label Text</label><div class="subtext">Subtext</div>', $out);
    }

    public function test_label_with_required()
    {
        ee()->lang->language['store.foo'] = 'Foo Text';
        $this->form->prefix = 'model';
        $out = $this->form->label('foo', null, array('required' => true));

        $this->assertSame('<label for="model_foo">Foo Text <em class="required">*</strong></label>', $out);
    }

    public function test_input()
    {
        $out = $this->form->input('foo');

        $this->assertContains('<input', $out);
        $this->assertContains('type="text"', $out);
    }

    public function test_hidden()
    {
        $out = $this->form->hidden('foo');

        $this->assertContains('<input', $out);
        $this->assertContains('type="hidden"', $out);
    }

    public function test_select()
    {
        $out = $this->form->select('foo', array('color-1' => 'Red', 'color-2' => 'Blue'));
        $this->assertContains('<select ', $out);
        $this->assertContains('[foo]"', $out);
        $this->assertContains('<option value="color-1">Red</option>', $out);
        $this->assertContains('<option value="color-2">Blue</option>', $out);
        $this->assertNotContains('multiple', $out);
        $this->assertNotContains('<input', $out);
    }

    public function test_select_multiple()
    {
        $out = $this->form->select(
            'foo',
            array('color-1' => 'Red', 'color-2' => 'Blue'),
            array('multiple' => true)
        );

        $this->assertContains('<input type="hidden"', $out);
        $this->assertContains('<select ', $out);
        $this->assertContains('[foo][]"', $out);
        $this->assertContains('multiple', $out);
        $this->assertContains('<option value="color-1">Red</option>', $out);
        $this->assertContains('<option value="color-2">Blue</option>', $out);
    }

    public function test_value()
    {
        $this->model->some_attr = 'test';

        $this->assertSame('test', $this->form->value('some_attr'));
    }

    public function test_value_no_model()
    {
        $this->form->model = null;

        $this->assertNull($this->form->value('some_attr'));
    }
}
