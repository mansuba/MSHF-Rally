<?php

namespace Store\Service;

use Guzzle\Http\Client;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\CallbackCanCacheStrategy;
use Store\Guzzle\CacheStorage;

class CachedHttpService extends Client
{
    public function __construct()
    {
        parent::__construct();

        $can_cache = new CallbackCanCacheStrategy(
            function(RequestInterface $request) {
                return true;
            },
            function(Response $response) {
                return $response->isSuccessful();
            }
        );

        $storage = new CacheStorage();

        $plugin = new CachePlugin(array(
            'can_cache' => $can_cache,
            'storage' => $storage,
        ));
        $this->addSubscriber($plugin);
    }
}
