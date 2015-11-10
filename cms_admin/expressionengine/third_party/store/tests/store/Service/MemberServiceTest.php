<?php

namespace Store\Service;

use Mockery as m;
use Store\Model\Member;
use Store\TestCase;
use Store\Test\Factory;

class MemberServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->member_register = m::mock('member_register');
        $this->service = new MemberService($this->ee, $this->member_register);
        $this->order = Factory::build('order', array(
            'member_id' => null,
            'order_email' => 'test@example.com',
            'username' => 'new-user',
            'screen_name' => 'user screen name',
            'password_hash' => 'secret hash',
            'password_salt' => 'secret salt',
        ));

        $_POST = array('existing' => 'some existing post data');
    }

    public function tearDown()
    {
        parent::tearDown();

        $_POST = array();
    }

    public function test_register()
    {
        $this->ee->config->shouldReceive('set_item');
        $this->member_register->shouldReceive('register_member');

        $this->service->register($this->order);
    }

    public function test_register_no_password()
    {
        // should ignore orders with no password
        $this->order->password_hash = null;

        $this->service->register($this->order);

        $this->assertNull($this->order->member_id);
    }

    public function test_register_existing_member_id()
    {
        // should ignore orders with existing member id
        $this->order->member_id = 1;

        $this->service->register($this->order);

        $this->assertSame(1, $this->order->member_id);
    }

    public function test_fake_post()
    {
        $this->service->fake_post($this->order);

        $this->assertSame('test@example.com', $_POST['email']);
        $this->assertSame('new-user', $_POST['username']);
        $this->assertSame('user screen name', $_POST['screen_name']);

        // should set a random strong password during registration
        $this->assertNotEmpty($_POST['password']);
        $this->assertSame($_POST['password'], $_POST['password_confirm']);
    }

    public function test_fake_post_username_default()
    {
        // username should default to email address
        $this->order->username = null;

        $this->service->fake_post($this->order);

        $this->assertSame('test@example.com', $_POST['username']);
    }

    public function test_fake_post_screen_name_default()
    {
        // screen name should default to email address
        $this->order->screen_name = null;

        $this->service->fake_post($this->order);

        $this->assertSame('test@example.com', $_POST['screen_name']);
    }

    public function test_restore_post()
    {
        $this->assertArrayHasKey('existing', $_POST);

        // fake post should save existing post data
        $this->service->fake_post($this->order);
        $this->assertArrayNotHasKey('existing', $_POST);

        // restore post should restore old existing post data
        $this->service->restore_post();
        $this->assertArrayHasKey('existing', $_POST);
    }

    public function test_fake_output()
    {
        $existing_output = $this->ee->output;

        $this->service->fake_output();
        $this->assertInstanceOf('Store\StubOutput', $this->ee->output);

        $this->service->restore_output();
        $this->assertSame($existing_output, $this->ee->output);
    }

    public function test_register_member_from_post()
    {
        $this->ee->config->shouldReceive('set_item')->times(3);
        $this->member_register->shouldReceive('register_member')->once();

        $this->service->register_member_from_post();
    }

    public function test_update_member()
    {
        $member = Factory::create('member', array('email' => $this->order->order_email));

        $this->service->update_member($this->order);

        $member = Member::find($member->member_id); // reload from db?

        // should set new password on member
        $this->assertSame('secret hash', $member->password);
        $this->assertSame('secret salt', $member->salt);

        // should set member ID on order
        $this->assertSame($member->member_id, $this->order->member_id);
    }
}
