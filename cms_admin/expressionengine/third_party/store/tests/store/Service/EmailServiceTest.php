<?php

namespace Store\Service;

use Mockery as m;
use Store\Test\Factory;
use Store\TestCase;

class EmailServiceTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->email = Factory::build('email');
        $this->order = m::mock('Store\Model\Order');
    }

    public function test_send()
    {
        $this->order->shouldReceive('toTagArray')->once()->andReturn(array());
        $this->ee->email->shouldReceive('EE_initialize')->once();
        $this->ee->email->shouldReceive('to')->once();
        $this->ee->email->shouldReceive('from')->once();
        $this->ee->email->shouldReceive('subject')->once();
        $this->ee->email->shouldReceive('message')->once();
        $this->ee->email->shouldReceive('send')->once();

        $service = m::mock('Store\Service\EmailService', array($this->ee))->makePartial();
        $service->shouldReceive('parse');
        $service->shouldReceive('parse_html');

        $service->send($this->email, $this->order);
    }
}
