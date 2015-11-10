<?php

namespace Store;

use Guzzle\Http\Client as HttpClient;
use Guzzle\Plugin\Mock\MockPlugin;
use Mockery as m;
use Store\Model\AbstractModel;
use Store\Model\Order;

class TestCase extends \PHPUnit_Framework_TestCase
{
    private $httpClient;

    public function setUp()
    {
        $this->ee = $this->createMockEE();
        $this->ee->store = new Container($this->ee);
        $this->ee->store->db = AbstractModel::resolveConnection('default');

        // run all tests inside a transaction
        $this->ee->store->db->getPdo()->beginTransaction();

        $GLOBALS['mock_ee'] = $this->ee;
    }

    public function tearDown()
    {
        if ( ! $this->ee->store->db->getPdo()->rollBack()) {
            $this->fail('Failed to roll back PDO transaction');
        }

        unset($GLOBALS['mock_ee']);
    }

    public function buildOrder()
    {
        $order = new Order;

        return $order;
    }

    public function getHttpClient()
    {
        if (null === $this->httpClient) {
            $this->httpClient = new HttpClient;
        }

        return $this->httpClient;
    }

    public function setMockHttpResponse($path)
    {
        $mock = new MockPlugin(null, true);
        $mock->addResponse(dirname(__DIR__).'/fixtures/'.$path);
        $this->getHttpClient()->getEventDispatcher()->removeSubscriber($mock);
        $this->getHttpClient()->getEventDispatcher()->addSubscriber($mock);

        return $mock;
    }

    public function createMockEE()
    {
        // mock global EE instance
        $ee = m::mock('ee_instance');

        // mock common EE libraries
        $ee->cache = m::mock('ee_cache');
        $ee->config = m::mock('ee_config');
        $ee->cp = m::mock('ee_cp');
        $ee->db = m::mock('ee_db');
        $ee->email = m::mock('ee_email');
        $ee->extensions = m::mock('ee_extensions', array('active_hook' => false));
        $ee->extensions->last_call = false;
        $ee->functions = m::mock('ee_functions');
        $ee->input = m::mock('ee_input');
        $ee->lang = m::mock('ee_lang', array('loadfile' => null));
        $ee->lang->language = array();
        $ee->load = m::mock('ee_load', array('helper' => null, 'library' => null, 'model' => null));
        $ee->localize = m::mock('ee_localize');
        $ee->output = m::mock('ee_output');
        $ee->security = m::mock('ee_security');
        $ee->session = m::mock('ee_session');
        $ee->TMPL = m::mock('ee_tmpl');

        return $ee;
    }
}
