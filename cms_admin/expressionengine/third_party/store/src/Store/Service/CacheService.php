<?php

namespace Store\Service;

use Guzzle\Cache\CacheAdapterInterface;

class CacheService extends AbstractService implements CacheAdapterInterface
{
    public $prefix = '/store/';

    public function contains($id, array $options = null)
    {
        return ee()->cache->get($this->prefix.$id) !== false;
    }

    public function delete($id, array $options = null)
    {
        return ee()->cache->delete($this->prefix.$id);
    }

    public function fetch($id, array $options = null)
    {
        return ee()->cache->get($this->prefix.$id);
    }

    public function save($id, $data, $lifetime = 3600, array $options = null)
    {
        return ee()->cache->save($this->prefix.$id, $data, $lifetime);
    }
}
