<?php

namespace Store\Guzzle;

use Guzzle\Http\Message\RequestInterface;
use Guzzle\Plugin\Cache\DefaultCacheStorage;

/**
 * Custom cache storage implementation
 *
 * Overrides default Guzzle cache to take into account
 * request body when caching requests.
 */
class CacheStorage extends DefaultCacheStorage
{
    public function __construct($cache = null)
    {
        $cache = $cache ?: ee()->store->cache;

        parent::__construct($cache, 'guzzle/');
    }

    /**
     * Provides access to the internal cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Hash a request into a string that returns cache metadata
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    public function getCacheKey(RequestInterface $request)
    {
        $method = $request->getMethod();
        $url = $request->getUrl();

        $body = null;
        if (method_exists($request, 'getBody') && $request->getBody()) {
            $body = ':'.$request->getBody()->getContentMd5();
        }

        return $this->keyPrefix.md5("$method $url").$body;
    }
}
